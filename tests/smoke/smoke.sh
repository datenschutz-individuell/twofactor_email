#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Everything about the app that can be checked without eyes: routes, status codes,
# the mail, and a full login with the code from it. Runs against a disposable
# Nextcloud built from the packaged app.
#
# By default it tests BOTH ends of the supported server range, taken from
# appinfo/info.xml. That is not thoroughness for its own sake: a route that was
# exempt from the two-factor gate on the newest server only, and dead on the oldest,
# shipped in 3.4.0 and was found exactly this way.
#
#   ./smoke.sh                   # min and max supported Nextcloud version
#   NC_TAG=33-apache ./smoke.sh  # one specific server version
#   SLOW=0 ./smoke.sh            # skip the successful resend, saving 65 s per version
#   KEEP=1 ./smoke.sh            # leave the instance running afterwards
#
# Exit code: number of failed checks, so CI can gate on it.
#
# Not covered, and deliberately so: how it looks. Layout, dark mode and translations
# still need a pair of eyes.
set -uo pipefail
cd "$(dirname "$0")"

# ROOT can be set to run these scripts from outside a checkout (used while developing
# them); inside the repository the default is right.
: "${ROOT:=$(git rev-parse --show-toplevel)}"
: "${APP_TARBALL:=$ROOT/build/artifacts/twofactor_email.tar.gz}"
: "${HTTP_PORT:=8080}"
: "${MAIL_PORT:=8025}"
# On by default: the successful resend is the half of that route which a cooldown
# rejection cannot prove, and 65 s per version is a fair price for it.
: "${SLOW:=1}"
: "${KEEP:=0}"
BASE="http://localhost:$HTTP_PORT"
MAILBOX="http://localhost:$MAIL_PORT"
PW=admin

pass=0
fail=0
skip=0
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

green() { printf '\033[32mpass\033[0m'; }
red() { printf '\033[31mFAIL\033[0m'; }
ok() { printf '  %s %s\n' "$(green)" "$1"; pass=$((pass + 1)); }
bad() { printf '  %s %s\n' "$(red)" "$1"; fail=$((fail + 1)); }
note() { printf '  \033[33mskip\033[0m %s\n' "$1"; skip=$((skip + 1)); }
is() { [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1: got $2, expected $3"; }
body_has() { grep -q -- "$2" "$TMP/body" && ok "$1" || bad "$1: '$2' not in the response"; }

occ() { docker compose exec -T -u www-data nextcloud php occ --no-ansi "$@" 2>&1; }
tfa_enabled() { occ twofactorauth:state admin | grep -q '^Two-factor authentication is enabled'; }

# The request token is in every rendered page.
page_token() { curl -s -b "$TMP/jar" -c "$TMP/jar" "$1" | grep -oP 'data-requesttoken="\K[^"]+' | head -1; }

# Response body goes to $TMP/body, the status code is returned.
#
# Two things here are not optional, and both cost hours to find:
#   * The token goes in a HEADER. It is base64 and often contains a '+', which PHP
#     turns into a space while decoding a form body — so as a form field the same
#     command works about one time in three.
#   * -X POST, because without -d curl would send a GET and the route answers 405.
# Origin is required too: Nextcloud rejects a POST without a trusted Origin before it
# ever looks at the credentials.
post() {
	local url=$1 tok=$2
	shift 2
	local args=() kv
	for kv in "$@"; do args+=(-d "$kv"); done
	# ${args[@]+…} because two callers pass no data at all (resend, admin/reset) and
	# expanding an empty array under `set -u` aborts on bash < 4.4 — macOS still ships
	# 3.2 as /bin/bash.
	curl -s -X POST -b "$TMP/jar" -c "$TMP/jar" \
		-H "Origin: $BASE" -H "requesttoken: $tok" \
		-o "$TMP/body" -w '%{http_code}' ${args[@]+"${args[@]}"} "$url"
}

mails() { curl -s "$MAILBOX/api/v1/messages?limit=20"; }
mail_count() { mails | python3 -c 'import json,sys; print(json.load(sys.stdin).get("messages_count", 0))'; }
newest_code() {
	local id
	id=$(mails | python3 -c 'import json,sys; m=json.load(sys.stdin)["messages"]; print(m[0]["ID"] if m else "")')
	[ -n "$id" ] || return 1
	curl -s "$MAILBOX/api/v1/message/$id" | python3 -c '
import json, re, sys
text = json.load(sys.stdin).get("Text", "")
found = re.findall(r"\b[0-9]{4,16}\b", text)
print(found[0] if found else "")'
}

run_checks() {
	rm -f "$TMP/jar"

	echo "-- version"
	local want got
	want=$(tar xzOf "$APP_TARBALL" twofactor_email/appinfo/info.xml | grep -oP '<version>\K[^<]+')
	got=$(occ app:list | grep -A1 '^  - twofactor_email' | grep -oP 'twofactor_email: \K.*' | tr -d ' \r')
	is "installed version matches the package" "$got" "$want"

	# The app keeps this state in Nextcloud's own two-factor registry, so occ can set
	# it. (The 2.x branch used a user setting of its own — not interchangeable.)
	occ twofactorauth:enable admin email >/dev/null
	tfa_enabled && ok "provider switched on for the user" || bad "provider not registered as enabled"

	echo "-- login and challenge"
	local tok redirect status
	tok=$(page_token "$BASE/login")
	[ -n "$tok" ] && ok "got a request token from /login" || bad "no request token on /login"
	redirect=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $tok" \
		-o /dev/null -w '%{redirect_url}' \
		-d "user=admin" -d "password=$PW" -d "timezone=Europe/Berlin" "$BASE/login")
	is "login leads to the email challenge" "${redirect#$BASE}" "/login/challenge/email"

	status=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -o "$TMP/challenge.html" -w '%{http_code}' \
		"$BASE/login/challenge/email")
	is "challenge page" "$status" "200"
	grep -q twofactor_email "$TMP/challenge.html" \
		&& ok "challenge page loads the app assets" || bad "challenge page has no app assets"
	is "exactly one mail was sent" "$(mail_count)" "1"

	echo "-- challenge/resend"
	local ctok
	ctok=$(grep -oP 'data-requesttoken="\K[^"]+' "$TMP/challenge.html" | head -1)
	# What matters is not "rejected" but that the CONTROLLER answers. 429 with
	# too-soon comes from the app; a 303 would mean the two-factor gate blocked the
	# request before it got there — which is exactly how this route was broken on the
	# oldest supported server.
	status=$(post "$BASE/apps/twofactor_email/challenge/resend" "$ctok")
	is "resend reaches the controller (not a redirect)" "$status" "429"
	body_has "cooldown reported as too-soon" '"error":"too-soon"'
	body_has "response names retryAfter" 'retryAfter'

	if [ "$SLOW" = 1 ]; then
		echo "     waiting 65 s for the cooldown to pass"
		sleep 65
		status=$(post "$BASE/apps/twofactor_email/challenge/resend" "$ctok")
		is "resend after the cooldown" "$status" "200"
		body_has "response reports the mail was sent" '"status":"sent"'
		is "a second mail arrived" "$(mail_count)" "2"
	else
		note "successful resend not checked (SLOW=0 was set)"
	fi

	echo "-- submitting the code"
	local code wrong ocs
	code=$(newest_code)
	[ -n "$code" ] && ok "read the code from the mail ($code)" || bad "no code in the mail"
	wrong=$(printf '%0*d' "${#code}" 0)
	post "$BASE/login/challenge/email" "$ctok" "challenge=$wrong" >/dev/null
	# Ask the session, not the URL: the redirect target is
	# /login/selectchallenge?redirect_url=/apps/dashboard/ — it CONTAINS the dashboard
	# path without being logged in, which makes a naive URL check pass wrongly.
	ocs=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H 'OCS-APIRequest: true' -o /dev/null \
		-w '%{http_code}' "$BASE/ocs/v2.php/cloud/user?format=json")
	[ "$ocs" = "200" ] && bad "a wrong code was accepted" \
		|| ok "a wrong code does not log the user in (OCS $ocs)"

	ctok=$(page_token "$BASE/login/challenge/email")
	redirect=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $ctok" \
		-o /dev/null -w '%{redirect_url}' -d "challenge=$code" "$BASE/login/challenge/email")
	is "the right code leads to the dashboard" "${redirect#$BASE}" "/apps/dashboard/"
	status=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H 'OCS-APIRequest: true' -o "$TMP/body" \
		-w '%{http_code}' "$BASE/ocs/v2.php/cloud/user?format=json")
	is "session is logged in (OCS)" "$status" "200"

	echo "-- admin routes"
	local atok
	atok=$(page_token "$BASE/settings/admin/security")
	status=$(post "$BASE/apps/twofactor_email/admin/save" "$atok" \
		"codeLength=99" "codeValidMinutes=10" "eMailTemplate=x" "eMailSubject=x" "resendMinutes=1")
	is "admin/save rejects invalid values" "$status" "400"
	body_has "response carries the error list" '"errors"'
	status=$(post "$BASE/apps/twofactor_email/admin/save" "$atok" \
		"codeLength=8" "codeValidMinutes=20" "eMailTemplate=Code: {code}" "eMailSubject=Test" "resendMinutes=2")
	is "admin/save stores valid values" "$status" "200"
	body_has "the stored code length comes back" '"codeLength":8'
	status=$(post "$BASE/apps/twofactor_email/admin/reset" "$atok")
	is "admin/reset" "$status" "200"
	body_has "reset restores the defaults" '"codeLength":6'

	echo "-- state/save"
	# No 403 to expect here: Nextcloud counts the login that just happened as the
	# password confirmation for 30 minutes. A 403 would only show up on an older
	# session, which a smoke test cannot sensibly wait for.
	local stok
	stok=$(page_token "$BASE/settings/user/security")
	status=$(post "$BASE/apps/twofactor_email/state/save" "$stok" "state=false")
	is "state/save switches the provider off" "$status" "200"
	tfa_enabled && bad "registry still reports it as enabled" || ok "registry reports it as off"
	status=$(post "$BASE/apps/twofactor_email/state/save" "$stok" "state=true")
	is "state/save switches it on again" "$status" "200"
	tfa_enabled && ok "registry reports it as enabled" || bad "registry still reports it as off"

	echo "-- assets"
	local broken=0 count=0 url code_
	while read -r url; do
		[ -n "$url" ] || continue
		count=$((count + 1))
		code_=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -o /dev/null -w '%{http_code}' "$BASE$url")
		[ "$code_" = "200" ] || { bad "asset $url -> $code_"; broken=$((broken + 1)); }
	done < <(grep -oP '(src|href)="\K/(dist|apps/twofactor_email)[^"]+' "$TMP/challenge.html" \
		| sed 's/&amp;/\&/g' | sort -u)
	# The count has to be checked too: if the extraction ever matches nothing, the loop
	# body never runs and a bare "$broken = 0" would report a pass for having tested
	# nothing at all. That false pass was observed while building this.
	if [ "$count" = 0 ]; then
		bad "no assets found on the challenge page — the page or the extraction changed"
	elif [ "$broken" = 0 ]; then
		ok "all $count assets of the challenge page were served"
	fi

	echo "-- server log"
	if docker compose exec -T nextcloud sh -c 'cat /var/www/html/data/nextcloud.log' 2>/dev/null \
		| grep twofactor_email | grep -qE '"level":[34]'; then
		bad "the app logged an error:"
		docker compose exec -T nextcloud sh -c 'cat /var/www/html/data/nextcloud.log' 2>/dev/null \
			| grep twofactor_email | grep -E '"level":[34]' | head -3 | cut -c1-160 | sed 's/^/       /'
	else
		ok "no app errors in the server log"
	fi
}

# Which server versions? Default: both ends of the declared range, because that is
# where the differences bite.
if [ -n "${NC_TAG:-}" ]; then
	TAGS=("$NC_TAG")
else
	spec=$(grep -oP '<nextcloud[^>]*' "$ROOT/appinfo/info.xml")
	min=$(printf '%s' "$spec" | grep -oP 'min-version="\K[0-9]+')
	max=$(printf '%s' "$spec" | grep -oP 'max-version="\K[0-9]+')
	if [ "$min" = "$max" ]; then TAGS=("$min-apache"); else TAGS=("$min-apache" "$max-apache"); fi
fi

echo "package: $APP_TARBALL"
echo "servers: ${TAGS[*]}"

# compose needs these in the environment of EVERY call, not just of setup.sh —
# otherwise `docker compose exec` fails, every occ call comes back empty and the run
# reports a dozen misleading failures instead of one clear one.
export APP_TARBALL HTTP_PORT MAIL_PORT ROOT
export APP_DIR="$PWD/app/twofactor_email"

for tag in "${TAGS[@]}"; do
	echo
	echo "=========== Nextcloud $tag ==========="
	export NC_TAG="$tag"
	if ! ./setup.sh >"$TMP/setup.log" 2>&1; then
		echo "  setup failed:"
		tail -15 "$TMP/setup.log" | sed 's/^/    /'
		fail=$((fail + 1))
		docker compose down -v >/dev/null 2>&1
		rm -rf app
		continue
	fi
	grep -E 'versionstring|twofactor_email' "$TMP/setup.log" | sed 's/^/  /'

	# Fail fast and loudly: if occ does not answer, every following check would fail
	# for the same reason and bury it.
	if ! occ status | grep -q 'installed: true'; then
		echo "  occ does not answer on this instance — skipping the checks:"
		occ status 2>&1 | head -5 | sed 's/^/    /'
		fail=$((fail + 1))
		docker compose down -v >/dev/null 2>&1
		rm -rf app
		continue
	fi

	before=$fail
	run_checks
	# Dump the log while the containers still exist. Doing this afterwards — or from a
	# following CI step — cannot work: the teardown below has already removed them.
	if [ "$fail" -gt "$before" ]; then
		echo "-- server log (last 40 lines, this instance failed)"
		docker compose exec -T nextcloud sh -c 'tail -n 40 /var/www/html/data/nextcloud.log' \
			2>/dev/null | cut -c1-200 | sed 's/^/     /' || echo "     (log not readable)"
		echo "-- container log (last 20 lines)"
		docker compose logs --tail 20 nextcloud 2>/dev/null | sed 's/^/     /'
	fi
	if [ "$KEEP" = 1 ]; then
		echo "  instance left running: $BASE (admin/$PW) · $MAILBOX"
		break
	fi
	docker compose down -v >/dev/null 2>&1
	rm -rf app
done

echo
printf 'passed: %d   failed: %d   skipped: %d\n' "$pass" "$fail" "$skip"
echo 'Not covered: appearance, dark mode, translations — use a browser for those.'
exit "$fail"

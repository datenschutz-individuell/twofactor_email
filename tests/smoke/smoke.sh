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
#   APP_DIR=$(git rev-parse --show-toplevel) ./smoke.sh   # test a directory, no krankerl
#   COMPOSE_PROJECT_NAME=tfe-2 HTTP_PORT=8081 MAIL_PORT=8026 ./smoke.sh   # second instance
#
# Exit code: number of failed checks, so CI can gate on it.
#
# Not covered, and deliberately so: how it looks. Layout, dark mode and translations
# still need a pair of eyes.
set -uo pipefail

# An APP_DIR from the caller is relative to the CALLER's directory, so it has to be made
# absolute before the cd below changes what it means — and compose needs an absolute path
# for a bind mount anyway.
if [ -n "${APP_DIR:-}" ]; then
	app_dir_abs=$(cd "$APP_DIR" 2>/dev/null && pwd) || {
		echo "APP_DIR is not a directory: $APP_DIR" >&2
		exit 1
	}
	APP_DIR=$app_dir_abs
fi
cd "$(dirname "$0")"

# ROOT can be set to run these scripts from outside a checkout (used while developing
# them); inside the repository the default is right.
: "${ROOT:=$(git rev-parse --show-toplevel)}"
: "${APP_TARBALL:=$ROOT/build/artifacts/twofactor_email.tar.gz}"
: "${HTTP_PORT:=8080}"
: "${MAIL_PORT:=8025}"
# An APP_DIR from the caller means "mount this directory as it is": then there is no
# package, so the version comparison has nothing to compare. Decided once here and never
# changed again — setup.sh gets the mode as an explicit UNPACK value rather than
# inferring it from a path, and our own compose calls read tests/smoke/.env.
if [ -n "${APP_DIR:-}" ]; then
	PACKAGED=0
	export APP_DIR UNPACK=0
else
	PACKAGED=1
	# Exported explicitly, not left to whatever the caller happens to have in the
	# environment: UNPACK=0 without APP_DIR would make setup.sh mount the default path
	# right after teardown deleted it.
	export UNPACK=1
fi
: "${COMPOSE_PROJECT_NAME:=tfe-smoke}"
export COMPOSE_PROJECT_NAME
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

ok() { printf '  \033[32mpass\033[0m %s\n' "$1"; pass=$((pass + 1)); }
bad() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fail=$((fail + 1)); }
note() { printf '  \033[33mskip\033[0m %s\n' "$1"; skip=$((skip + 1)); }
is() { [ "$2" = "$3" ] && ok "$1 ($2)" || bad "$1: got $2, expected $3"; }
body_has() { grep -q -- "$2" "$TMP/body" && ok "$1" || bad "$1: '$2' not in the response"; }

occ() { docker compose exec -T -u www-data nextcloud php occ --no-ansi "$@" 2>&1; }
tfa_enabled() { occ twofactorauth:state admin | grep -q '^Two-factor authentication is enabled'; }

# An expired code for one user, without driving a login. The value is not a real
# hash: nothing reads it back, the cleanup only asks whether one is stored.
seed_code() {
	occ user:setting "$1" twofactor_email code 'not-a-real-hash' >/dev/null
	occ user:setting "$1" twofactor_email code_created_at 0 >/dev/null
	# Every key a real code writes, or "nothing left behind" would also be true of a
	# key that was never there.
	occ user:setting "$1" twofactor_email code_address_hash 'not-a-real-hash' >/dev/null
}

# How many of the keys a stored code consists of are present. Every key a code
# writes belongs in here: a key missing from the pattern would let "no code behind"
# pass while that key is still there.
code_keys() { occ user:setting "$1" twofactor_email | grep -cE '^ +- code(_created_at|_address_hash)?: '; }

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

server_log() { docker compose exec -T nextcloud sh -c 'cat /var/www/html/data/nextcloud.log' 2>/dev/null; }

mails() { curl -s "$MAILBOX/api/v1/messages?limit=20"; }
# One key, no fallback: a rename must fail loudly rather than read as "no mail was sent",
# and the image is pinned by digest so a rename cannot arrive unnoticed. (mailpit's
# `total` is not a synonym — it counts the whole mailbox, not the current query.)
mail_count() { mails | python3 -c 'import json,sys; print(json.load(sys.stdin)["messages_count"])'; }
newest_recipient() {
	mails | python3 -c '
import json, sys
m = json.load(sys.stdin)["messages"]
print(",".join(t["Address"] for t in m[0]["To"]) if m else "")'
}
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
	if [ "$PACKAGED" = 1 ]; then
		want=$(tar xzOf "$APP_TARBALL" twofactor_email/appinfo/info.xml | grep -oP '<version>\K[^<]+')
		got=$(occ app:list | grep -A1 '^  - twofactor_email' | grep -oP 'twofactor_email: \K.*' | tr -d ' \r')
		is "installed version matches the package" "$got" "$want"
	else
		note "version not compared — a directory is mounted, there is no package"
	fi

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

	# The admin settings are an IDelegatedSettings. No HTTP route reaches that
	# side of the class: the server builds the delegation list on its own and
	# asks each setting for its name and priority. If it ever expected a method
	# the class does not have, this is where it would show.
	# The needle is the JSON key, not just the class name: a PHP error names the
	# class too, so grepping for the name alone would pass on the very failure
	# this check exists to catch.
	echo "-- delegated admin settings"
	is "the settings class is offered for delegation" \
		"$(occ admin-delegation:show --output=json \
			| grep -cF '"className":"OCA\\TwoFactorEMail\\Settings\\AdminSettings"')" "1"

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

	# No HTTP route reaches either command, so nothing else in this run touches them.
	# The user id is digits only because Nextcloud allows that and PHP then hands the
	# id back as an int; the unit tests pin that conversion at class level, this pins
	# the two commands end to end.
	echo "-- occ cleanup commands"
	local digits=12345
	docker compose exec -T -u www-data -e OC_PASS=smoke-pw-12345 nextcloud \
		php occ --no-ansi user:add --password-from-env "$digits" >"$TMP/adduser" 2>&1
	grep -q 'created successfully' "$TMP/adduser" \
		&& ok "a user whose id is digits only exists" \
		|| bad "creating the user $digits: $(tr '\n' ' ' <"$TMP/adduser")"
	occ twofactor_email:delete-codes --all >/dev/null
	seed_code "$digits"
	is "every key of the seeded code is stored" "$(code_keys "$digits")" "3"
	is "cleanup removes the expired code" \
		"$(occ twofactor_email:cleanup | grep -c 'Removed 1 expired code')" "1"
	# The display name proves the listing came from a user that exists. Without it,
	# "no keys left" would also hold for a user that was never created at all.
	is "the user is still there" \
		"$(occ user:setting "$digits" twofactor_email | grep -c 'display_name')" "1"
	is "cleanup leaves no code behind" "$(code_keys "$digits")" "0"
	seed_code "$digits"
	is "delete-codes removes the code of that id" \
		"$(occ twofactor_email:delete-codes "$digits" | grep -c 'Deleted the stored code')" "1"
	is "delete-codes leaves no code behind" "$(code_keys "$digits")" "0"

	echo "-- assets"
	local broken=0 count=0 url code_
	while read -r url; do
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

	# A code is mailed to the address in force at the time. Once that address changes,
	# the mailbox holding the code may belong to someone else, so the code has to stop
	# being accepted (lib/Service/CodeStorage.php). This runs after every check that
	# needs a logged-in session, because it ends with one that deliberately is not.
	# The section "submitting the code" is the control: the same sequence without the
	# address change does log in, so the change is what makes the difference here.
	echo "-- a code the address change invalidated"
	rm -f "$TMP/jar"
	local ntok ncode before submitted
	# The mailbox already holds mail from the sections above, so "there is a code" would
	# also be true of the one already consumed there. Count first and demand one more:
	# without that, a login that never reached the challenge would read the stale code,
	# get it rejected for being stale, and this section would report a pass for nothing.
	before=$(mail_count)
	# Remember what the address is now instead of assuming setup.sh's value: with
	# KEEP=1 someone may have pointed the account at their own mailbox for a manual
	# test, and a later section reads this address back and restores whatever it
	# finds — so writing a guess here would make the change permanent.
	local address_before primary_before
	address_before=$(occ user:setting admin settings email | tr -d ' \r')
	# A notification address left behind by the section below, or by a KEEP=1 session,
	# would make the change here move nothing at all — and the failure would read as the
	# app accepting a code it should have dropped. It is remembered for the same reason
	# the address is: a later section reads it back, and a KEEP=1 instance keeps it.
	primary_before=$(occ user:setting admin settings primary_email --default-value= | tr -d ' \r')
	occ user:setting --delete admin settings primary_email >/dev/null 2>&1
	ntok=$(page_token "$BASE/login")
	curl -s -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $ntok" \
		-o /dev/null -d "user=admin" -d "password=$PW" -d "timezone=Europe/Berlin" "$BASE/login"
	# Loading the challenge page is what issues the code, so the token has to come from
	# that same page: the login page above was fetched before the code existed.
	ntok=$(page_token "$BASE/login/challenge/email")
	is "a fresh code was mailed" "$(mail_count)" "$((before + 1))"
	ncode=$(newest_code)
	[ -n "$ncode" ] && ok "read the fresh code from the mail ($ncode)" || bad "no code in the mail"
	occ user:setting admin settings email smoke-changed@example.org >/dev/null
	# The status matters as much as the outcome: a 303 back to the challenge means the
	# controller rejected the code, while a 303 from the two-factor gate or a CSRF
	# rejection would leave the session logged out too — and read as a pass.
	submitted=$(curl -s -X POST -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $ntok" \
		-o /dev/null -w '%{http_code} %{redirect_url}' -d "challenge=$ncode" "$BASE/login/challenge/email")
	is "the submission reached the challenge controller" "$submitted" "303 $BASE/login/challenge/email"
	ocs=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H 'OCS-APIRequest: true' -o /dev/null \
		-w '%{http_code}' "$BASE/ocs/v2.php/cloud/user?format=json")
	[ "$ocs" = "200" ] && bad "the code was still accepted after the address changed" \
		|| ok "the code was rejected after the address changed (OCS $ocs)"
	# Put the address back: a later run of these checks starts from the same instance.
	case $address_before in *@*) occ user:setting admin settings email "$address_before" >/dev/null ;;
		*) bad "could not read the address back, leaving it as it is: $address_before" ;;
	esac

	# The same question for the address change Nextcloud announces to nobody:
	# picking a notification address writes settings/primary_email and fires no
	# event, while getEMailAddress() prefers exactly that value. No listener can
	# see it, so this is what the code being bound to its address is for
	# (lib/Service/CodeStorage.php). Two things have to follow from it: the code
	# that went to the old mailbox stops working, and a fresh one goes out to the
	# new one without the user having to ask.
	echo "-- an address change that fires no event"
	rm -f "$TMP/jar"
	before=$(mail_count)
	ntok=$(page_token "$BASE/login")
	curl -s -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $ntok" \
		-o /dev/null -d "user=admin" -d "password=$PW" -d "timezone=Europe/Berlin" "$BASE/login"
	ntok=$(page_token "$BASE/login/challenge/email")
	is "a code was mailed to the account address" "$(mail_count)" "$((before + 1))"
	ncode=$(newest_code)
	# Without this an unreadable mail would submit an empty code, which is refused just
	# the same — and the binding under test would never be exercised.
	[ -n "$ncode" ] && ok "read the code for the old mailbox ($ncode)" || bad "no code in the mail"
	occ user:setting admin settings primary_email smoke-primary@example.org >/dev/null
	# Submit before reloading the page, or this proves nothing: a reload issues a fresh
	# code, and the old one would then be refused by a plain hash mismatch — which a
	# build without any binding would do just as well.
	submitted=$(curl -s -X POST -b "$TMP/jar" -c "$TMP/jar" -H "Origin: $BASE" -H "requesttoken: $ntok" \
		-o /dev/null -w '%{http_code} %{redirect_url}' -d "challenge=$ncode" "$BASE/login/challenge/email")
	is "the submission reached the challenge controller" "$submitted" "303 $BASE/login/challenge/email"
	ocs=$(curl -s -b "$TMP/jar" -c "$TMP/jar" -H 'OCS-APIRequest: true' -o /dev/null \
		-w '%{http_code}' "$BASE/ocs/v2.php/cloud/user?format=json")
	[ "$ocs" = "200" ] && bad "the code for the old mailbox was still accepted" \
		|| ok "the code for the old mailbox was rejected (OCS $ocs)"
	# And the page hands out a code the user can actually receive: at the new address,
	# without anyone asking for it.
	page_token "$BASE/login/challenge/email" >/dev/null
	is "a fresh code went out although no event fired" "$(mail_count)" "$((before + 2))"
	is "it went to the new notification address" "$(newest_recipient)" "smoke-primary@example.org"
	# Only an address goes back: occ writes its errors to stdout, so a diagnostic line
	# would otherwise be stored as the address the account delivers to.
	case $primary_before in
		*@*) occ user:setting admin settings primary_email "$primary_before" >/dev/null ;;
		'') occ user:setting --delete admin settings primary_email >/dev/null ;;
		*) occ user:setting --delete admin settings primary_email >/dev/null
			bad "could not read the notification address back, leaving none: $primary_before" ;;
	esac

	echo "-- server log"
	server_log >"$TMP/nclog"
	if grep twofactor_email "$TMP/nclog" | grep -qE '"level":[34]'; then
		bad "the app logged an error:"
		grep twofactor_email "$TMP/nclog" | grep -E '"level":[34]' | head -3 | cut -c1-160 | sed 's/^/       /'
	else
		ok "no app errors in the server log"
	fi

	# Nextcloud does not throw when it cannot deliver: it catches the transport error and
	# returns the addresses it refused, so an app that ignores that return value stores a
	# code and reports it as sent while nothing went out (#202). An address without an "@"
	# is the one refusal a smoke test can provoke without breaking the mail setup for
	# everything else. This deliberately logs an error, so it runs after the log check
	# above — and last of all, because it ends logged out: there is no form to submit.
	echo "-- a recipient the mailer refuses"
	rm -f "$TMP/jar"
	local rtok mails_before address primary
	# A code still stored would be reused instead of a new one being sent, and then there
	# would be no send left to fail.
	occ twofactor_email:delete-codes admin >/dev/null
	# occ writes its errors to stdout here, so an unset address would come back as a
	# sentence — and be written back as the address at the end of the section.
	address=$(occ user:setting admin settings email | tr -d ' \r')
	case $address in *@*) ;; *) address=admin@example.org ;; esac
	# The app is given whichever address the account resolves to, and that is
	# primary_email whenever one is set. On an instance that has one, the code would
	# be mailed normally and every check below would read as a regression.
	# --default-value is what keeps an unset key from returning a sentence.
	primary=$(occ user:setting admin settings primary_email --default-value= | tr -d ' \r')
	if [ -n "$primary" ]; then
		occ user:setting --delete admin settings primary_email >/dev/null
	fi
	occ user:setting admin settings email no-at-sign >/dev/null
	mails_before=$(mail_count)
	rtok=$(page_token "$BASE/login")
	post "$BASE/login" "$rtok" "user=admin" "password=$PW" "timezone=Europe/Berlin" >/dev/null
	curl -s -b "$TMP/jar" -c "$TMP/jar" -o "$TMP/refused.html" "$BASE/login/challenge/email"
	# Without this the two checks below would also pass on a page that is not the
	# challenge page at all — the login failing would read as "no code was offered".
	grep -q 'twofactor_email-challenge-icon' "$TMP/refused.html" \
		&& ok "the challenge page was reached" || bad "the login did not reach the challenge page"
	grep -q 'could not be sent' "$TMP/refused.html" \
		&& ok "the challenge page reports the failure" \
		|| bad "the challenge page does not report the failure (the instance has to be English)"
	grep -q 'twofactor_email-challenge-form' "$TMP/refused.html" \
		&& bad "the page still asks for a code that was never sent" \
		|| ok "no code entry is offered"
	is "nothing was mailed" "$(mail_count)" "$mails_before"
	server_log >"$TMP/nclog"
	grep -qF 'Failed to send 2FA challenge email due to a mailer error' "$TMP/nclog" \
		&& ok "the app logged the failure" || bad "the app logged nothing about the failure"

	# A send that fails stores no code, and the challenge page has no rate limit of its
	# own, so every reload would open another connection to the mail server. The app
	# caps that at ten in five minutes through Nextcloud's limiter, and a whole run
	# stays inside that window: the sections above spend at most six of the ten, so
	# twelve reloads reach the cap, and then the log has to show the app refusing by
	# itself instead of another mailer error. Count the sends again when adding a
	# section that issues a code — that budget is what makes this check provable.
	# A second budget runs alongside: solveChallenge() carries UserRateLimit(5, 100), and
	# the run submits a code four times. A fifth submission would be refused by that limit,
	# and the failure would read as "the submission did not reach the controller".
	echo "-- the reload cap"
	local i
	for i in $(seq 12); do
		curl -s -b "$TMP/jar" -c "$TMP/jar" -o "$TMP/refused.html" "$BASE/login/challenge/email"
	done
	grep -q 'could not be sent' "$TMP/refused.html" \
		&& ok "a capped reload still reports the failure" \
		|| bad "a capped reload does not report the failure"
	server_log >"$TMP/nclog"
	grep -qF 'the account reached the send rate limit' "$TMP/nclog" \
		&& ok "the cap stopped further attempts" \
		|| bad "reloading the challenge page kept asking the mail server"
	is "still nothing was mailed" "$(mail_count)" "$mails_before"
	# Put the address back, so a later section or run finds the mailbox setup.sh chose.
	occ user:setting admin settings email "$address" >/dev/null
	if [ -n "$primary" ]; then
		occ user:setting admin settings primary_email "$primary" >/dev/null
	fi
	is "the address was restored" "$(occ user:setting admin settings email | tr -d ' \r')" "$address"
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

# Refuse to test a package that cannot contain the current work. krankerl packages the
# COMMITTED state, so a stale package and uncommitted changes both mean "you are testing
# something else" — and that has happened here: a smoke test once passed against a
# package built before the change it was meant to prove.
preflight_package() {
	# Everything the package is made of: the files that are shipped as they are (appinfo,
	# lib, templates, css, js, l10n, img) and the sources they are built from (src and the
	# manifests including their lock files — a changed lock file means a changed vendor/
	# or a changed build). Anything left out here can change without the package looking
	# stale, and translations change often enough for that to matter.
	# css and js are deliberately absent: both are gitignored build output with no tracked
	# files, so git can never report them — listing them would only claim coverage.
	local paths='lib src templates appinfo l10n img'
	paths="$paths composer.json composer.lock package.json package-lock.json"
	# These two decide what the package CONTAINS, so a change to them dates a package
	# just as much as a change to the code does.
	paths="$paths krankerl.toml .nextcloudignore"

	# Checked BEFORE building: krankerl packages the committed state, so building now
	# would spend a minute producing a package that still cannot contain these changes,
	# only to be rejected afterwards.
	local dirty
	# shellcheck disable=SC2086
	dirty=$(git -C "$ROOT" status --porcelain -- $paths)
	if [ -n "$dirty" ]; then
		echo "Uncommitted changes to the app:"
		printf '%s\n' "$dirty" | sed 's/^/    /'
		cat <<TXT

krankerl packages the committed state, so the above cannot be in any package. Pick one:

  git commit …
      commit them, then run again
  APP_DIR=$ROOT $0
      test the working tree instead of the package
TXT
		exit 1
	fi


	if [ ! -f "$APP_TARBALL" ]; then
		echo "No package at $APP_TARBALL. Pick one:"
		echo "  krankerl package     — build it, then run again"
		echo "  APP_DIR=$ROOT $0"
		exit 1
	fi

	local source_ts package_ts
	# The last commit that touched the app itself — a docs-only commit must not make a
	# perfectly good package look stale. Defaults to 0: an empty result would otherwise
	# make the comparison below an error, and an erroring check is no check.
	# shellcheck disable=SC2086
	source_ts=$(git -C "$ROOT" log -1 --format=%ct -- $paths)
	package_ts=$(stat -c %Y "$APP_TARBALL")
	if [ "$package_ts" -lt "${source_ts:-0}" ]; then
		echo "The package is older than the app it should contain:"
		printf '  package %s\n' "$(date -d "@$package_ts" '+%F %T')"
		printf '  sources %s (last commit touching the app)\n' "$(date -d "@$source_ts" '+%F %T')"
		cat <<TXT

Pick one:

  krankerl package
      rebuild, then run again
  APP_DIR=$ROOT $0
      test the working tree instead of the package
TXT
		exit 1
	fi
}

# Never take over an instance that is already up: compose would recreate the container
# of whoever is using it. This happened — a test run recreated a colleague's manual
# instance because both used the same project name and ports.
# Filtered by the project label, which is authoritative because COMPOSE_PROJECT_NAME is
# set above rather than derived from the directory name — compose lowercases and strips
# characters, so a guessed name silently stops matching.
running=$(docker ps --quiet --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME" | wc -l)
if [ "$running" != 0 ]; then
	cat >&2 <<TXT
An instance of this project is already running ($running container(s)).
Starting now would recreate it and pull it away from whoever is using it.

  COMPOSE_PROJECT_NAME=$COMPOSE_PROJECT_NAME docker compose down -v
                                               stop and remove it, then run again
                                               (the project is named explicitly so this
                                               cannot hit a different instance)
  COMPOSE_PROJECT_NAME=tfe-2 HTTP_PORT=8081 MAIL_PORT=8026 $0
                                               run a second, independent instance
TXT
	exit 1
fi

if [ "$PACKAGED" = 1 ]; then
	preflight_package
	echo "package: $APP_TARBALL"
else
	echo "directory: $APP_DIR  (mounted as it is, not a package)"
fi
echo "servers: ${TAGS[*]}"

# compose needs these in the environment of EVERY call, not just of setup.sh —
# otherwise `docker compose exec` fails, every occ call comes back empty and the run
# reports a dozen misleading failures instead of one clear one.
export APP_TARBALL HTTP_PORT MAIL_PORT ROOT
# In directory mode the caller's APP_DIR is what compose mounts, for every version. In
# packaged mode it must stay UNSET while setup.sh runs: setup.sh unpacks the tarball and
# sets it itself. Handing it a value beforehand made it take the "mount a directory"
# branch and gave compose a path that did not exist yet, so the container never started.
if [ "$PACKAGED" = 0 ]; then export APP_DIR; fi

# A failed teardown leaves the containers and the data volume behind, so the next version
# installs into a foreign data directory. That must be loud, not swallowed.
teardown() {
	local out
	# Nothing of this project around? Then there is nothing to tear down, and calling
	# compose would only fail: on a fresh checkout .env does not exist yet, so a setup
	# that died before writing it would produce a bogus failure here.
	if [ -z "$(docker ps -aq --filter "label=com.docker.compose.project=$COMPOSE_PROJECT_NAME")" ]; then
		rm -rf app
		return 0
	fi
	if ! out=$(docker compose down -v 2>&1); then
		bad "teardown failed — remove it by hand: docker compose down -v"
		printf '%s\n' "$out" | tail -5 | sed 's/^/       /'
	fi
	rm -rf app
}

for tag in "${TAGS[@]}"; do
	echo
	echo "=========== Nextcloud $tag ==========="
	export NC_TAG="$tag"
	if ! ./setup.sh >"$TMP/setup.log" 2>&1; then
		echo "  setup failed:"
		tail -15 "$TMP/setup.log" | sed 's/^/    /'
		fail=$((fail + 1))
		teardown
		continue
	fi
	grep -E 'versionstring|twofactor_email' "$TMP/setup.log" | sed 's/^/  /'

	# Fail fast and loudly: if occ does not answer, every following check would fail
	# for the same reason and bury it.
	if ! occ status | grep -q 'installed: true'; then
		echo "  occ does not answer on this instance — skipping the checks:"
		occ status 2>&1 | head -5 | sed 's/^/    /'
		fail=$((fail + 1))
		teardown
		continue
	fi

	before=$fail
	run_checks
	# Dump the log while the containers still exist. Doing this afterwards — or from a
	# following CI step — cannot work: the teardown below has already removed them.
	if [ "$fail" -gt "$before" ]; then
		echo "-- server log (last 40 lines, this instance failed)"
		server_log | tail -n 40 | cut -c1-200 | sed 's/^/     /' || echo "     (log not readable)"
		echo "-- container log (last 20 lines)"
		docker compose logs --tail 20 nextcloud 2>/dev/null | sed 's/^/     /'
	fi
	if [ "$KEEP" = 1 ]; then
		echo "  instance left running: $BASE (admin/$PW) · $MAILBOX"
		# Only one instance can be kept, so the run stops here. Say so, otherwise the
		# summary below looks like a full pass over the whole version range.
		if [ "$tag" != "${TAGS[$((${#TAGS[@]} - 1))]}" ]; then
			note "the remaining server version(s) were not tested (KEEP=1 stops after the first)"
		fi
		break
	fi
	teardown
done

echo
printf 'passed: %d   failed: %d   skipped: %d\n' "$pass" "$fail" "$skip"
echo 'Not covered: appearance, dark mode, translations — use a browser for those.'
exit "$fail"

#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Bring up a disposable Nextcloud with the built app installed and ready to test.
# Use this when you want to click around; use smoke.sh to run the checks.
#
#   NC_TAG=34-apache ./setup.sh                       # unpack the built package
#   UNPACK=0 APP_DIR=/path/to/app ./setup.sh          # mount a directory as it is
#
# Removing it again:  docker compose down -v
# (This works with an empty environment because setup.sh writes the values compose needs
# into tests/smoke/.env, which compose reads for every subcommand.)
set -euo pipefail

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

: "${NC_TAG:?set NC_TAG, e.g. 34-apache}"
: "${ROOT:=$(git rev-parse --show-toplevel)}"
: "${APP_TARBALL:=$ROOT/build/artifacts/twofactor_email.tar.gz}"
: "${HTTP_PORT:=8080}"
: "${MAIL_PORT:=8025}"
# Whether to extract the package into APP_DIR. A caller who names a directory means
# "mount this", so that flips the default — but the value stays explicit and overridable,
# which is what matters: smoke.sh always states it, and guessing the mode from the path
# alone is what once made this script mount a directory that had just been deleted.
if [ -n "${APP_DIR:-}" ]; then : "${UNPACK:=0}"; else : "${UNPACK:=1}"; fi
: "${APP_DIR:=$PWD/app/twofactor_email}"
# The project name is set rather than derived, so the "already running" check in
# smoke.sh can filter by it instead of reproducing compose's naming rules.
: "${COMPOSE_PROJECT_NAME:=tfe-smoke}"
export COMPOSE_PROJECT_NAME

docker info >/dev/null 2>&1 || {
	echo "The Docker daemon is not reachable." >&2
	echo "After a kernel update without a reboot it refuses to start: the modules of" >&2
	echo "the running kernel are gone, so overlayfs cannot be loaded. Reboot." >&2
	exit 1
}

if [ "$UNPACK" = 1 ]; then
	[ -f "$APP_TARBALL" ] || {
		echo "No package at $APP_TARBALL — run 'krankerl package', or mount a directory" >&2
		echo "instead: UNPACK=0 APP_DIR=<path> $0" >&2
		exit 1
	}
	# Always unpack afresh, otherwise a second run silently tests the previous build.
	# Note that krankerl packages the committed state, so an uncommitted fix is not in
	# here — a mistake that is easy to make and hard to see.
	rm -rf app
	mkdir -p app
	tar xzf "$APP_TARBALL" -C app
else
	echo "mounting the directory as it is: $APP_DIR"
fi

# Everything compose needs, in the file compose reads by itself. That is what makes a
# later `docker compose down -v` (or logs, ps, exec) work from this directory with an
# empty environment — for the scripts and for a human. Before this existed, every
# invocation had to carry NC_TAG and APP_DIR, and a teardown that forgot them failed
# silently and left the data volume behind.
# COMPOSE_PROJECT_NAME belongs in here too: without it a bare `docker compose` in this
# directory derives the project from the directory name and quietly targets a different
# project than the one that is up — it then removes nothing and says nothing.
printf 'COMPOSE_PROJECT_NAME=%s\nNC_TAG=%s\nAPP_DIR=%s\nHTTP_PORT=%s\nMAIL_PORT=%s\n' \
	"$COMPOSE_PROJECT_NAME" "$NC_TAG" "$APP_DIR" "$HTTP_PORT" "$MAIL_PORT" > .env

docker compose up -d --quiet-pull

occ() { docker compose exec -T -u www-data nextcloud php occ --no-ansi "$@"; }

printf 'waiting for the installation to finish '
for _ in $(seq 1 60); do
	if occ status 2>/dev/null | grep -q 'installed: true'; then echo ' done'; break; fi
	printf '.'
	sleep 5
done
occ status | sed 's/^/  /'

# The mail settings are declared in compose.yaml; the image turns them into config by
# itself. Check that they arrived, because the file doing it (config/smtp.config.php)
# belongs to the image, not to us: if a future version drops or renames it, the only
# symptom would be a challenge email that never arrives — which reads like a bug in
# the app.
# The port is checked as well as the host. Host, sender and domain are that file's own
# precondition, so losing one of them fails the host check anyway. The port is not: it
# falls back to 25 when SMTP_PORT is missing, which leaves the host right and the mail
# catcher silent.
# The two expected values repeat what compose.yaml declares. Keep the pair in step —
# changing the port there without changing it here aborts every run.
expected_host=mailpit
expected_port=1025
# `|| true` because a key that is not set makes occ exit 1, which pipefail would turn
# into a silent abort of this script. An empty value is a result here, not a crash.
setting() { occ config:system:get "$1" | tail -n 1 | tr -d '\r' || true; }
host=$(setting mail_smtphost)
port=$(setting mail_smtpport)
if [ "$host" != "$expected_host" ] || [ "$port" != "$expected_port" ]; then
	echo "The mail settings from compose.yaml did not arrive:" >&2
	echo "  mail_smtphost: '$host' (expected '$expected_host')" >&2
	echo "  mail_smtpport: '$port' (expected '$expected_port')" >&2
	echo "Both empty means occ could not answer at all — see 'docker compose logs" >&2
	echo "nextcloud'. Otherwise check whether nextcloud:$NC_TAG still ships" >&2
	echo "config/smtp.config.php, which is where these values come from." >&2
	echo "The instance is up; remove it with 'docker compose down -v'." >&2
	exit 1
fi

# Right after the installation a write can fail with "database is locked": the
# installer's own background work still holds the SQLite file, and `occ status`
# reporting "installed" does not mean it has let go. Retry instead of hoping.
# This used to be hidden: the five occ calls that configured the mail settings stood
# here and took about a second each, which was enough for the lock to clear. Moving
# them into compose.yaml removed that accidental delay and turned the race into a
# failing CI run — on one of the two servers only, as races go.
# Only writes need this. `config:system:get` above reads config.php, not the database.
# Retrying is announced, because a run that needed nine attempts otherwise looks exactly
# like a clean one — and then nobody notices that the budget below is getting tight.
occ_write() {
	local out attempt=1
	while :; do
		if out=$(occ "$@" 2>&1); then
			[ -n "$out" ] && echo "$out" | sed 's/^/  /'
			return 0
		fi
		# Only the lock is worth waiting for. A command that is broken for another
		# reason — bad info.xml, a dependency the server does not have — has to say so
		# now instead of after ten sleeps.
		case $out in
		*"database is locked"*) ;;
		*)
			echo "$out" >&2
			return 1
			;;
		esac
		if [ "$attempt" -ge 10 ]; then
			echo "Still locked after $attempt attempts:" >&2
			echo "$out" >&2
			return 1
		fi
		echo "  database locked, retrying ($attempt)"
		attempt=$((attempt + 1))
		sleep 3
	done
}

# Without an address on the account the provider cannot be switched on.
occ_write user:setting admin settings email admin@example.org
occ_write app:enable twofactor_email

# Wait for HTTP too, not only for occ. `occ status` goes through docker exec, so it
# reports "installed" while Apache may still be unable to serve a request. Whoever runs
# next then talks to a server that is not there yet, and the first failure reads
# "no request token on /login" followed by empty responses — which looks like the login
# page changed rather than like a race. That is what happened to the CI on 2026-08-05:
# the same commit passed in one run and failed in the next, on one server version only.
# The budget is a deadline, not a number of attempts: a single attempt has to be allowed
# to take long enough for the FIRST render of a fresh instance, which builds the asset
# caches, while the total still cannot run away. A connection refusal — the case this
# waits for — comes back instantly, so the per-attempt limit only ever applies to a
# response that is already being generated. Aborting those would be actively harmful:
# PHP's ignore_user_abort defaults to 0, so each abandoned probe kills a render halfway
# through, and the caches it was building are what the asset checks read later.
# `--noproxy` because an http_proxy in the environment would otherwise be asked to
# resolve localhost.
printf 'waiting for the server to answer '
ready=0
code=none
deadline=$((SECONDS + 120))
while [ "$SECONDS" -lt "$deadline" ]; do
	code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 --noproxy localhost \
		"http://localhost:$HTTP_PORT/login" || true)
	case $code in
	200 | 303)
		ready=1
		break
		;;
	4??)
		# Answered, and not with something that becomes 200 by waiting: an untrusted
		# domain is 400, a missing rewrite 404. Report it now instead of after the
		# full budget.
		echo ''
		echo "The server answers on http://localhost:$HTTP_PORT/login with $code." >&2
		echo "That will not change by waiting. 400 usually means the domain is not" >&2
		echo "trusted; 404 means the request never reached Nextcloud." >&2
		echo "Remove the instance with 'docker compose down -v'." >&2
		exit 1
		;;
	esac
	printf '.'
	sleep 2
done
if [ "$ready" = 1 ]; then
	echo ' done'
else
	echo ''
	echo "The server does not answer on http://localhost:$HTTP_PORT (last status: $code)." >&2
	echo "The instance is up, so look at 'docker compose logs nextcloud'." >&2
	echo "If your docker daemon is not local (DOCKER_HOST), the published port is not" >&2
	echo "on localhost and this check cannot work — that is a limitation, not a fault." >&2
	echo "Remove the instance with 'docker compose down -v'." >&2
	exit 1
fi

cat <<TXT

Nextcloud   http://localhost:$HTTP_PORT   (admin / admin)
Mailbox     http://localhost:$MAIL_PORT

Switch the provider on for the user without opening the browser:
  docker compose exec -T -u www-data nextcloud php occ twofactorauth:enable admin email

Then log in: the code is waiting in the mailbox.
Remove everything again:  COMPOSE_PROJECT_NAME=$COMPOSE_PROJECT_NAME docker compose down -v
(The project is named explicitly because .env holds the values of the LAST setup run —
with a second instance around, a bare 'down' could remove the wrong one.)
TXT

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
if [ "$(occ config:system:get mail_smtphost | tail -n 1 | tr -d '\r')" != mailpit ]; then
	echo "The image did not apply the mail settings from compose.yaml." >&2
	echo "Check whether nextcloud:$NC_TAG still ships config/smtp.config.php." >&2
	exit 1
fi

# Without an address on the account the provider cannot be switched on.
occ user:setting admin settings email admin@example.org

occ app:enable twofactor_email | sed 's/^/  /'

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

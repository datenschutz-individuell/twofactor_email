#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Bring up a disposable Nextcloud with the built app installed and ready to test.
# Use this when you want to click around; use smoke.sh to run the checks.
#
#   NC_TAG=34-apache ./setup.sh
#   NC_TAG=33-apache APP_TARBALL=/path/to/twofactor_email.tar.gz ./setup.sh
#
# Removing it again:  NC_TAG=34-apache docker compose down -v
set -euo pipefail
cd "$(dirname "$0")"

: "${NC_TAG:?set NC_TAG, e.g. 34-apache}"
: "${ROOT:=$(git rev-parse --show-toplevel)}"
: "${APP_TARBALL:=$ROOT/build/artifacts/twofactor_email.tar.gz}"
: "${HTTP_PORT:=8080}"
: "${MAIL_PORT:=8025}"
export NC_TAG HTTP_PORT MAIL_PORT

docker info >/dev/null 2>&1 || {
	echo "The Docker daemon is not reachable." >&2
	echo "After a kernel update without a reboot it refuses to start: the modules of" >&2
	echo "the running kernel are gone, so overlayfs cannot be loaded. Reboot." >&2
	exit 1
}

[ -f "$APP_TARBALL" ] || {
	echo "No package at $APP_TARBALL — run 'krankerl package' first." >&2
	exit 1
}

# Always unpack afresh, otherwise a second run silently tests the previous build.
# Note that krankerl packages the committed state, so an uncommitted fix is not in
# here — a mistake that is easy to make and hard to see.
rm -rf app
mkdir -p app
tar xzf "$APP_TARBALL" -C app
export APP_DIR="$PWD/app/twofactor_email"

docker compose up -d --quiet-pull

occ() { docker compose exec -T -u www-data nextcloud php occ --no-ansi "$@"; }

printf 'waiting for the installation to finish '
for _ in $(seq 1 60); do
	if occ status 2>/dev/null | grep -q 'installed: true'; then echo ' done'; break; fi
	printf '.'
	sleep 5
done
occ status | sed 's/^/  /'

# Point Nextcloud at the mail catcher and give the admin an address: without one the
# provider cannot be switched on.
occ config:system:set mail_smtpmode --value=smtp >/dev/null
occ config:system:set mail_smtphost --value=mailpit >/dev/null
occ config:system:set mail_smtpport --value=1025 >/dev/null
occ config:system:set mail_from_address --value=nextcloud >/dev/null
occ config:system:set mail_domain --value=example.org >/dev/null
occ user:setting admin settings email admin@example.org

occ app:enable twofactor_email | sed 's/^/  /'

cat <<TXT

Nextcloud   http://localhost:$HTTP_PORT   (admin / admin)
Mailbox     http://localhost:$MAIL_PORT

Switch the provider on for the user without opening the browser:
  docker compose exec -T -u www-data nextcloud php occ twofactorauth:enable admin email

Then log in: the code is waiting in the mailbox.
Remove everything again:  NC_TAG=$NC_TAG docker compose down -v
TXT

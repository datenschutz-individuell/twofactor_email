<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Smoke test against a real Nextcloud

The unit tests cover the classes. What they cannot cover is whether the app still
works *inside* a Nextcloud — whether the routes are reachable, whether the mail goes
out, whether a login with a code from that mail actually gets you in, and whether any
of that differs between the oldest and the newest supported server.

These scripts start a disposable Nextcloud from the official image, install **the
built package** into it, and check exactly that.

## Requirements

Docker with the compose plugin, `python3`, `curl`, and a built package
(`krankerl package`) — or a directory to mount, see below. Nothing leaves your machine.

The scripts use GNU `grep -oP`, `stat -c` and `date -d`, so they need a GNU userland:
Linux, or macOS with GNU coreutils and grep ahead of the stock ones in `PATH`.

## Use

```bash
cd tests/smoke
./smoke.sh                   # both ends of the supported server range
NC_TAG=33-apache ./smoke.sh  # one specific server version
SLOW=0 ./smoke.sh            # skip the successful resend, saving 65 s per version
KEEP=1 ./smoke.sh            # leave the instance up to look at it
NC_TAG=34-apache ./setup.sh   # just an instance, no checks
COMPOSE_PROJECT_NAME=tfe-2 HTTP_PORT=8081 MAIL_PORT=8026 ./smoke.sh   # a second instance
```

`setup.sh` writes `tests/smoke/.env` (gitignored) with the values compose needs, which is
why a later `docker compose down -v`, `logs` or `exec` works in that directory without
setting anything. It records the **last** setup run, so with two instances around name the
project when tearing one down:
`COMPOSE_PROJECT_NAME=tfe-smoke docker compose down -v`.

## Without krankerl

`krankerl` is only needed to build the package. If it does not work for you — it has
been known to fail with `reference 'refs/remotes/origin/master' not found`, which comes
from libgit2 inside it, not from your repository — mount your working tree instead:

```bash
composer install -o          # only the autoloader is needed at runtime
npm ci && npm run build      # produces js/ and css/
APP_DIR="$(git rev-parse --show-toplevel)" ./smoke.sh
```

Naming a directory switches the mode: nothing is unpacked, that directory is mounted.
`setup.sh` takes the same route, and `UNPACK` states it explicitly if you ever need to
override the default (`UNPACK=0` mount, `UNPACK=1` unpack the package).

Everything is checked as usual, except the comparison of the installed version against
the package: there is no package. The trade-off is stated in the output, and it is
real — a file missing from the release because of `.nextcloudignore` cannot show up
this way. Use the packaged run before a release, this one while developing.

The exit code is the number of failed checks. Ports and package can be overridden
with `HTTP_PORT`, `MAIL_PORT` and `APP_TARBALL`.

A full run takes about six minutes per server version. Most of the extra time is one
deliberate 65-second wait: the resend cooldown has to pass before the *successful*
resend can be checked, and a rejected resend only proves half of that route. `SLOW=0`
drops it when you are iterating.

## Switching the provider on without a browser

The app keeps the per-user state in Nextcloud's own two-factor registry, so `occ` can
do it:

```bash
docker compose exec -T -u www-data nextcloud php occ twofactorauth:enable admin email
```

`setup.sh` prints this too. Note that the 2.x branch used a user setting of its own
instead — `occ user:setting … twofactor_email verified true` does nothing here.

## Why it may refuse to start

The script compares the package against the state of the app and stops if they do not
match — either because something is uncommitted:

```
Uncommitted changes to the app:
    M src/LoginChallenge.js
```

or because the package predates the last commit that touched the app:

```
The package is older than the app it should contain:
  package 2026-07-26 22:41:03
  sources 2026-07-27 23:05:11 (last commit touching the app)
```

This is not pedantry. `krankerl package` packages the **committed** state, so a stale
package or an uncommitted change means the run proves something other than what you are
working on — which has happened here: a smoke test once passed against a package built
before the change it was meant to verify. Rebuild with `krankerl package`, or
test the working tree instead with `APP_DIR=…`.

## What it checks

Login and challenge, the mail, `challenge/resend` including its cooldown, a wrong and
then the right code, `admin/save` with its validation path, `admin/reset`,
`state/save` in both directions with the registry state, every asset the challenge
page pulls, and the server log.

**Why both server versions by default:** in 3.4.0 the resend endpoint was dead on
Nextcloud 33 while working on 34. The exemption from the two-factor gate is read from
the docblock on 33 and from the attribute on 34, and the attribute is `@since 34`. The
manual browser pass ran on a 34 instance, so nothing looked wrong. One version is not
a test of a version *range*.

## What it does not check

**Appearance.** Layout, dark mode, translations, whether a dialog is comprehensible.
That still needs a person with a browser — `KEEP=1` leaves an instance running for it.

## Things that cost hours, written down so they cost you none

- **The request token goes in a header, not in a form field.** It is base64 and often
  contains a `+`, which PHP turns into a space while decoding a form body. As a form
  field the same request therefore works about one time in three, which looks like a
  flaky server rather than a broken client.
- **`curl` needs an `Origin` header for `POST /login`.** Nextcloud rejects the request
  before it looks at the password, and answers with a redirect to
  `?direct=1&user=…` plus a misleading `Logging out` in the log. It looks exactly like
  a wrong password.
- **`curl` sends a GET when no `-d` is given**, so a POST-only route answers 405 and
  it reads like the route is missing.
- **After a wrong code the redirect target contains the dashboard path**
  (`/login/selectchallenge?redirect_url=/apps/dashboard/`). Checking the URL for
  "dashboard" therefore reports a success that never happened — ask the session
  instead.
- **`krankerl package` packages the committed state.** An uncommitted fix is not in the
  package, and the test will keep proving the old behaviour.
- **"The token expired" on the first login attempt.** Nextcloud's login form is only
  valid for five minutes (`login_form_timeout`). Opening the page while the instance is
  still installing and submitting afterwards therefore fails once; reloading is enough.
  `occ config:system:set login_form_timeout --value=3600 --type=integer` if you want to
  take your time.
- **The admin password cannot be changed to a weak one afterwards.**
  `password_policy` is active in the image; it does not apply during installation,
  which is why `admin/admin` works at all. Throw the instance away instead.

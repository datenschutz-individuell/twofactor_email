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
(`krankerl package`). Nothing else, and nothing leaves your machine.

## Use

```bash
cd tests/smoke
./smoke.sh                   # both ends of the supported server range
NC_TAG=33-apache ./smoke.sh  # one specific server version
SLOW=1 ./smoke.sh            # also the successful resend (adds a 65 s wait)
KEEP=1 ./smoke.sh            # leave the instance up to look at it
./setup.sh                   # just an instance, no checks (needs NC_TAG)
```

The exit code is the number of failed checks. Ports and package can be overridden
with `HTTP_PORT`, `MAIL_PORT` and `APP_TARBALL`.

## Switching the provider on without a browser

The app keeps the per-user state in Nextcloud's own two-factor registry, so `occ` can
do it:

```bash
docker compose exec -T -u www-data nextcloud php occ twofactorauth:enable admin email
```

`setup.sh` prints this too. Note that the 2.x branch used a user setting of its own
instead — `occ user:setting … twofactor_email verified true` does nothing here.

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
- **The admin password cannot be changed to a weak one afterwards.**
  `password_policy` is active in the image; it does not apply during installation,
  which is why `admin/admin` works at all. Throw the instance away instead.

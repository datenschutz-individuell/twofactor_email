# Two-Factor Email Provider for Nextcloud

[Nextcloud](https://nextcloud.com/) supports web logins with a second factor
([two-factor authentication](https://en.wikipedia.org/wiki/Multi-factor_authentication#Factors),
2FA). To support a certain type of 2FA, a "2FA provider" (server-)app must be
installed. 2FA kicks in after the primary authentication stage (typically
username and password) were successful. This provider challenges the user to
enter a randomly generated authentication code (aka one-time password, OTP,
currently 6 digits). It sends that code to the user's primary email address and
expects the user to enter it on an additional 2nd step web login page.

## Installation, activation and usage

As with any 2FA provider, two-factor email must be installed from the
[Nextcloud app store](https://apps.nextcloud.com/apps/twofactor_email) and
enabled by a Nextcloud server admin. Additionally, the Nextcloud must have a
working email server configured.

The user may set up any of the installed providers or even multiple. This
provider uses email to send the code and thus can only be enabled if an email
address is set in 'Personal info'. Mind that a user may not be able to log in
if that email address is invalid (or email server setup of the Nextcloud is
not working properly).

Admins with console access may enable and disable this provider for specified
users via OCC command. Admins may also enforce 2FA for all users (or specific
groups) via Admin Settings. This is a Nextcloud feature and not specific to
this provider. If enforced, users with no 2FA are prompted to enable any
installed provider (that supports AtLogin setup – this provider supports it
since v3). If the admin installs this provider and enforces 2FA, it should be
ensured that each user does have a valid email address.

Mind that, once a user enabled any 2FA provider, they can no longer use their
password in applications that don't support the web based 2FA login flow. For
such applications, the user needs to create and use
[app passwords](https://docs.nextcloud.com/server/stable/user_manual/en/session_management.html#managing-devices)
(to be found at the bottom of Personal Settings/Security).

## State of the app

This version 3.x.x ("v3") is the successor of the deprecated [twofactor_email](https://github.com/nursoda/twofactor_email/)
app 2.x.x ("v2"). v2 will remain in the [Nextcloud App Store](https://apps.nextcloud.com/apps/twofactor_email) alongside v3 as
long as upcoming security issues may be fixed with reasonable effort. After
that, or after all supported Nextcloud versions may use v3, it will be pulled
from the App Store. v3 is based on [twofactor_totp](https://github.com/nextcloud/twofactor_totp/) but has been refactored.
v2 is installable on NC ≤33, v3 on NC ≥32.

The code is stable now. There are plans to further enhancements. See open tasks in the
[roadmap](https://github.com/datenschutz-individuell/twofactor_email/issues/7). It keeps the status of whether this provider is enabled for a specific
user or not when migrating from v2 to v3. However, from 3.1.1 onwards, v2 codes are no
longer migrated to v3 since most of them were obsolete. Mind that the look and some
behaviour changed or was enhanced.

## Contributions welcome

This app is a community effort. Any offers to help are welcome, whether it's
code enhancements, refactoring, better test coverage, new features, security
audits, translations or good documentation, examples, etc.

Prior to creating a PR, please discuss your idea in the [ideas collection issue](https://github.com/datenschutz-individuell/twofactor_email/issues/8).
Make sure your PR sticks to ONE change (so that we may review it cleanly), and
that it doesn't break existing functionality. We will do our best to timely
review and comment PRs.

This app takes advantage of the transifex Nextcloud community. If the app is
not yet available in your language, please consider to create a transifex
account and join the [Nextcloud translators community](https://explore.transifex.com/nextcloud/).

If you have any questions, please contact [the current maintainers](https://github.com/datenschutz-individuell/CONTRIBUTORS.md).

## Building yourself

To build the app, check out the repo and use `krankerl package` or follow these
steps:

* `composer i --no-dev`
* `npm ci`
* `npm run build` or `npm run dev` [more info](https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/npm.html)

<small>[krankerl](https://github.com/ChristophWurst/krankerl/) is the tool proposed by Nextcloud to build apps.</small>

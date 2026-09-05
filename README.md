# Two-Factor Email Provider for Nextcloud

[Nextcloud](https://nextcloud.com/) can ask for a second factor after the password
([two-factor authentication](https://en.wikipedia.org/wiki/Multi-factor_authentication#Factors),
2FA). Each kind of second factor comes from a provider app that a server admin
installs. This one emails a one-time code (OTP) — six digits by default — and asks for
it on a second login page.

## Installation and setup

An admin installs **Two-Factor Email** from the
[Nextcloud app store](https://apps.nextcloud.com/apps/twofactor_email) and enables it.
The server needs a working mail setup — the code travels by email.

A user then switches it on under *Personal settings › Security*, which needs an email
address in *Personal info*: that is where the code goes. If the address is wrong, or
the server cannot send mail, the user cannot log in. An admin can switch it on for
someone instead, from the console: `occ twofactorauth:enable <uid> email`.

Nextcloud can also enforce a second factor for everyone or per group, though never one
particular method. Email is a low-friction choice there: the user confirms one code and
is done, with no device to enrol. Check first that every account has a working address —
a user whose address does not work cannot log in.

Any second factor stops desktop and mobile clients from signing in with the normal
password. Each of them needs an
[app password](https://docs.nextcloud.com/server/stable/user_manual/en/session_management.html#managing-devices)
instead, created under *Personal settings › Security*.

## Versions

Every Nextcloud version that Nextcloud itself still supports is served by a line of
this app that gets security fixes. An older Nextcloud keeps the last line that ran on
it, for as long as maintaining it stays reasonable — that is an offer, not a promise.
New features go first into the line built for the newest released Nextcloud, which is
**not** this one; an older 3.x line gets them where that is easy.

| Line | Use it on | Security fixes | New features |
|---|---|---|---|
| 3.5 | Nextcloud 33–35 | yes | yes |
| **3.3** (this branch) | Nextcloud 32 | while reasonable | best effort |
| [2.8](https://github.com/nursoda/twofactor_email/) | Nextcloud 30–31 | while reasonable | no |

Version 3 is a refactored successor of version 2 and started out from
[twofactor_totp](https://github.com/nextcloud/twofactor_totp/). An upgrade from 2 to 3
keeps the provider switched on for each user; stored codes are not carried over, as
they expire within minutes anyway.

## Contributions welcome

This app is a community effort. Help of any kind is welcome — code, tests,
documentation, translations, bug reports and ideas.

[CONTRIBUTING.md](CONTRIBUTING.md) explains how to get started;
[CONTRIBUTORS.md](CONTRIBUTORS.md) lists who to ask.

## Building yourself

To build the app, check out the repo and use `krankerl package` or follow these
steps:

* `composer i --no-dev`
* `npm ci`
* `npm run build`
  or `npm run dev` [more info](https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/npm.html)

<small>[krankerl](https://github.com/ChristophWurst/krankerl/) is the tool proposed by Nextcloud to build apps.</small>

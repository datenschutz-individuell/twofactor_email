# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/2.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 3.3.0 (2026-07-16)

### Added

- Admin settings: show and change all app settings via occ
- Admins can delete the stored codes of one or all users via occ
- Notify the user if an admin (de)activated this 2FA provider or the primary email address was deleted
- Expired codes are cleaned up daily by a background job

### Changed

- Clearer message when no email address is set

### Fixed

- Wrong and repeated activity entries for accounts without an email address
- Admin and automatic changes are no longer attributed to the affected user in the activity stream
- Activity and notification texts could not be translated
- Saving an unchanged on/off state created duplicate activity entries
- Outdated notifications are dismissed when the provider state changes again
- The instance logo returns to the challenge email after upgrades from 3.1.x (#109)

### Security

- Harden the challenge email subject against header injection (defense in depth)
- Keep 2FA enabled instead of dropping to password-only when a user's only email address is removed

## 3.2.0 (2026-07-05)

### Added

- Admin settings: allow setting a custom challenge email subject
- Login: users can request a new code after a short cooldown
- Admin settings: set the resend cooldown (how soon a new code may be requested)

### Changed

- Admin settings: links in the challenge email body are now rendered as such

## 3.1.2 (2026-06-17)

### Security

- Update dependencies to fix esbuild and form-data advisories

### Changed

- updated dependencies fix an optical glitch in the personal settings toggle

## 3.1.1 (2026-06-01)

### Fixed

- When updating the app from v2 to v3, authentication codes are no longer migrated, see
  https://github.com/datenschutz-individuell/twofactor_email/issues/69#issuecomment-4588492017

## 3.1.0 (2026-05-31)

### Added

- Allow admin to modify app settings via web interface
- New translations from transifex: tr

## 3.0.10 (2026-05-13)

### Changed

- First non-beta release of v3. Please note that there still are translations
  missing and that there still are tasks that you may want to help out with,
  see https://github.com/datenschutz-individuell/twofactor_email/issues/7

## 3.0.9-beta.2 (2026-05-10)

### Added

- Support for Nextcloud 34
- New translations: de, de_DE, en_GB, et_EE, ga, lt_LT, pl, pt_BR, ru, sv, uz, zh_HK, zh_TW – a BIG "Thank you!" to all
  translators on transifex!

## 3.0.8-beta.1 (2026-04-22)

### Added

- Enabled translations via transifex

## 3.0.7-beta.1 (2026-04-14)

### Security

- Update dependencies to fix Axios security issue

## 3.0.6-beta.1 (2026-02-03)

### Changed

- Only send a new code if no valid code is still stored (replaces send limit)

## 3.0.5-beta.4 (2026-01-31)

### Added

- Limit sending emails

### Changed

- Hardening: Store code as hash

## 3.0.5-beta.2 (2026-01-26)

### Fixed

- Styling of personal settings

## 3.0.5-beta.1 (2026-01-26)

### Added

- Support for Nextcloud 33
- Support for PHP 8.5

## 3.0.4-beta.1 (2025-08-29)

### Added

- Support for Nextcloud 32

### Fixed

- Migration errors when updating from v2 to v3

### Removed

- Support for Nextcloud <=31

## 3.0.3-beta.2 (2025-08-19)

### Added

- If twofactor_email version 2.x was installed before, user settings are now migrated
- Support for enabling the provider for users via OCC command
- Support for Nextcloud 32 (but broken)

### Changed

- twofactor_email versions 3.0.0-dev - 3.0.2-dev used their own database table.
  This table is dropped. When updating from these dev versions, pending codes are lost.

### Removed

- Support for Nextcloud <=30
- Support for PHP 8.0

## 3.0.2-dev (2024-12-05)

### Added

- Support for Nextcloud 29-31 (tested against 30.0.3)
- Support for PHP 8.4

### Removed

- Support for Nextcloud <=28

## 3.0.0-dev (2024-04-21)

### Added

- Users can set up the two-factor email provider at login if 2FA is enforced

### Changed

- Complete rewrite, based on twofactor_totp 11.0.0-dev and twofactor_email 2.7.4

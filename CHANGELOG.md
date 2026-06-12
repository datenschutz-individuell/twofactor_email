# Changelog

Notable changes in [changelog format](https://keepachangelog.com/en/1.0.0/), project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)

## Unreleased

### Added

- All parts of the challenge email are now configurable in the admin settings: subject,
  body and footer. Empty fields fall back to the localized default texts, so
  out-of-the-box emails are now translated into the recipient's language (previously the
  default body was always English). A new placeholder `{validity}` (code validity in
  minutes) is available in all parts alongside `{code}`, `{user}` and `{cloud}`.
- Body and footer support a minimal markup that survives in the HTML variant of the
  email: a blank line starts a new paragraph, a single line break becomes a line break,
  and `[URL="https://example.org"]Text[/URL]` (or `[URL]https://example.org[/URL]`)
  becomes a clickable link (http, https and mailto). Everything else is HTML-escaped.
- Images can be embedded in the body as `[IMG="https://example.org/image.png"]Description[/IMG]`
  (https only). Note that many email clients load remote images only after confirmation.
- The instance logo is controlled by the body: the new `{logo}` placeholder renders it
  (small) wherever it is written — or not at all when it is omitted. The classic logo
  header block is no longer used; the new default body starts with `{logo}`.
- New default body text: it explains who is logging in where, that email was chosen as
  the second factor, how long the code is valid — and to treat an unexpected code as an
  attack attempt.

### Changed

- The challenge email no longer renders a separate heading; its text duplicated the
  first sentence of the body.

## 3.1.1 (2026-06-01)

### Fixed

- When updating the app from v2 to v3, authentication codes are no longer migrated, see
  https://github.com/datenschutz-individuell/twofactor_email/issues/69#issuecomment-4588492017

## 3.1.2 (2026-06-11)

### Fixed

- Code cleanup; updated dependencies that also fix an optical glitch in the personal settings toggle

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

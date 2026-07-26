# Review guidance

For automated review. It says what is worth flagging here, and what has already been
decided so that it does not get re-litigated in every pull request.

The general facts about the project — supported versions, layout, commands — are in
[`CLAUDE.md`](CLAUDE.md).

## Worth flagging

**Correctness before everything.** This app guards a login. A defect that lets a
second factor be skipped, accepted twice, or brute-forced matters more than anything
about style.

- **Security-relevant attributes on controllers.** A state-changing user route without
  `#[PasswordConfirmationRequired]`, a public or challenge-time endpoint without
  `#[BruteForceProtection]` or a rate limit, an admin route without
  `#[AuthorizedAdminSetting]`. Also flag an exemption that is broader than the comment
  next to it claims.
- **Codes and their lifetime.** Anything that stores a code in clear text, keeps it
  after use, compares it without constant-time semantics, or widens its validity.
- **An empty result treated as success.** `for` over nothing, a filter that matches
  nothing, a check whose loop body never runs — if "found nothing to test" can report a
  pass, that is a bug. This has happened here.
- **Failure paths that cannot work.** Diagnostics after a teardown, a log dump that
  runs when the container is gone, `|| true` swallowing the very error the step exists
  to surface.
- **Shell portability in dev tooling.** Scripts under `tests/` are run by contributors
  on macOS, whose stock `/bin/bash` is 3.2: expanding a possibly-empty array under
  `set -u` aborts there.
- **Version-range shims that outlived their reason.** When the supported Nextcloud
  range changes, workarounds for the dropped version become dead weight around
  security-relevant code. Flag them.
- **Repository conventions**, because they exist for a reason and are easy to miss when
  adding a file of a kind that already exists: SPDX/REUSE coverage for new files,
  GitHub Actions pinned to a commit SHA with a version comment, `persist-credentials:
  false` on every checkout, `curl` downloads with `-f`.
- **A version bump that misses one of its four places** (`appinfo/info.xml`,
  `package.json`, both fields in `package-lock.json`) or a missing changelog section.

## Already decided — please do not flag

- **The duplicated `NoTwoFactorRequired`** in `ChallengeController::resend()`: the
  docblock annotation serves Nextcloud 33, the attribute serves 34 and is `@since 34`.
  Both are needed until `min-version` reaches 34. The docblock explains it.
- **`symfony/console` at `^6.4`**: Nextcloud bundles Symfony 6.4 and `occ` commands
  must match its major.
- **`@nextcloud/vite-config` pinned to a pre-release**: it is the only version that
  allows Vite 8.
- **Formatting.** `php-cs-fixer` with `nextcloud/coding-standard` owns it, ESLint and
  Stylelint own the frontend. Tabs, brace placement and import order are not review
  material.
- **`package-lock.json` churn** in a dependency update, and generated translation
  files.
- **Test doubles that look over-specific.** Assertions on exact mock calls are
  deliberate here; the unit tests are meant to pin behaviour, not to be flexible.

## What "ready" means here

CI green, no new Psalm or taint findings, and — for anything touching routes,
controllers or the challenge flow — the smoke test run against **both** ends of the
supported Nextcloud range. One server version is not a test of a version range: a
resend endpoint once worked on the newest server and was dead on the oldest, and only
that two-version run found it.

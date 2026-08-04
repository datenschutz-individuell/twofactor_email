# Review guidance

For automated review. It says what is worth flagging here, and what has already been
decided so that it does not get re-litigated in every pull request.

The general facts about the project — supported versions, layout, commands — are in
[`CLAUDE.md`](CLAUDE.md).

## Worth flagging

**Correctness comes first here.** This app guards a login, so a defect that lets a second
factor be skipped, accepted twice or brute-forced outweighs any question of style.

- **Security-relevant attributes on controllers.** A route reachable by a **fully
  logged-in** user that changes state without `#[PasswordConfirmationRequired]`; a
  public or challenge-time endpoint without `#[BruteForceProtection]` or a rate limit;
  an admin route without `#[AuthorizedAdminSetting]`. Also flag an exemption that is
  broader than the comment next to it claims. Routes that run *during* the challenge
  cannot use password confirmation — see the settled list below.
- **Codes and their lifetime.** Anything that stores a code in clear text, keeps it
  after a **successful** verification, compares it without constant-time semantics, or
  widens its validity beyond the configured window.
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
  adding a file of a kind that already exists: SPDX/REUSE coverage for new files, a
  decision about `.nextcloudignore` for anything new at the root, GitHub Actions pinned
  to a commit SHA with a version comment, `persist-credentials: false` on checkouts,
  and `-f` on any `curl` that **downloads a tool or artefact** (not on the test
  requests in `tests/smoke/`, where the status code is the assertion).
- **Texts that will not survive translation.** User-facing strings, comments and
  docblocks should be plain, short English. Idioms, nested clauses and German sentence
  structure in English words all make translation worse.
- **Parametrised tests that hide differences.** `it.each` is right only when setup and
  expected result are identical across cases; a branch inside the case, or a different
  mock mechanism per case, means it should have been separate tests.
- **Abstraction without a second implementation.** An interface, factory or base class
  introduced for a case that does not exist yet.
- **A version bump that misses one of its four places** (`appinfo/info.xml`,
  `package.json`, both fields in `package-lock.json`) or a missing changelog section.

## Already decided — please do not flag

- **The duplicated `NoTwoFactorRequired`** in `ChallengeController::resend()`: the
  docblock annotation serves Nextcloud 33, the attribute serves 34 and is `@since 34`.
  Both are needed until `min-version` reaches 34. The docblock explains it.
- **`symfony/console` at `^6.4.42`**: Nextcloud bundles Symfony 6.4 and `occ` commands
  must match its major. This one has no expiry — it moves when Nextcloud moves.
- **`@nextcloud/vite-config` pinned to a pre-release**: it is the only version that
  allows Vite 8. **This one does expire** — once a stable release supports Vite 8, the
  pin becomes exactly the kind of leftover the rule above asks you to flag.
- **`ChallengeController::resend()` has no `#[PasswordConfirmationRequired]`.** It runs
  before the second factor is complete, where password confirmation cannot be
  satisfied. Its protection is the rate limit, brute-force protection and the service
  cooldown.
- **A wrong code does not invalidate the stored one.** Deleting it only on success is a
  deliberate trade: users may mistype. Documented at the decision in
  `LoginChallenge`.
- **The remaining `npm audit` low findings.** They come from `elliptic` beneath the
  Vite config's polyfill plugin; its latest release is the flagged one, nothing in the
  chain has a fix, and none of it reaches the built bundle. Likewise the `EBADENGINE`
  warnings, which appear because the `@nextcloud/*` packages have not declared Node 26
  yet.
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

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
- **An array key used as a value.** PHP converts a key of digits only to `int`, whatever
  the docblock promises — and OCP promises `array<string, …>` for user ids and provider
  ids alike. Psalm believes the promise and cannot see this, so a key that reaches a
  `string` parameter needs a cast. Nextcloud allows an all-digit user id, so this is a
  real id, not a contrived one. This has happened here too.
- **Failure paths that cannot work.** Diagnostics after a teardown, a log dump that
  runs when the container is gone, `|| true` swallowing the very error the step exists
  to surface.
- **Shell portability in dev tooling.** Scripts under `tests/` are run by contributors
  on macOS, whose stock `/bin/bash` is 3.2: expanding a possibly-empty array under
  `set -u` aborts there.
- **Version-range shims that outlived their reason.** When the supported Nextcloud
  range changes, workarounds for the dropped version become dead weight around
  security-relevant code. Flag them. Flag a **new** one too if it is not registered in
  `CompatibilityShimsTest`: that class is meant to list every workaround the app carries
  only because it spans several versions, so that dropping a version is a sweep rather
  than a search.
- **Repository conventions**, because they exist for a reason and are easy to miss when
  adding a file of a kind that already exists: SPDX/REUSE coverage for new files, a
  decision about `.nextcloudignore` for anything new at the root, GitHub Actions pinned
  to a commit SHA with a version comment, `persist-credentials: false` on checkouts,
  and `-f` on any `curl` that **downloads a tool or artefact** (not on the test
  requests in `tests/smoke/`, where the status code is the assertion). Psalm enforces
  `#[\Override]` on every override, so that one reports itself.
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
  Both are needed until `min-version` reaches 34. The docblock explains it, and so
  does the `UndefinedAttributeClass` handler in `psalm.xml` — the attribute does not
  exist in the oldest supported server's OCP, which the analysis now checks against.
  That handler names the **one** class on purpose; a suppression in the method's
  docblock silenced every undefined attribute on the route that skips the second
  factor, including a typo. Both go when `min-version` reaches 34 — and
  `CompatibilityShimsTest` fails until they do, so this is enforced rather than
  remembered.
- **`findUnusedIssueHandlerSuppression="false"` outlives that pair.** A suppression in
  `psalm.xml` exists because the app spans several versions, so in any single analysis
  run it may simply not be triggered — and psalm would then report it as unused. The
  flag belongs to all of them and goes with the last one, which is a check of its own,
  not to the handler it was first added for.
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
- **The inline `${{ ... }}` versions in the workflows that pull requests trigger, and
  CodeQL's cache poisoning alerts.** `npm-audit.yml` passes the npm version through the
  environment because it audits a branch other than the default one. The other workflows
  keep the template's inline form on purpose: they run on `pull_request`, where a fork
  gets a read-only token and no secrets, and they execute the pull request's own code
  anyway — `npm ci` and the build, or the `psalm` script from its `composer.json`.
  Injection wins nothing there. The two `actions/cache-poisoning/poisonable-step` alerts
  on `npm-audit.yml` are dismissed for that reason and a stronger one: no workflow in
  this repository writes or restores an Actions cache, so there is nothing to poison.
  Hardening the checkout was considered and rejected: a `sparse-checkout` does not
  close the path. `package.json` has to stay, because the version step reads it, and
  its `engines.npm` is what reaches `npm i -g`; keeping a root-level `.npmrc` off the
  runner would need `sparse-checkout-cone-mode: false` on top, because cone mode always
  materialises the repository root. Both refs are branches of this repository anyway —
  whoever could poison one could edit this workflow instead — and the alert would stay,
  because the rule follows the ref, not the files on disk. Revisit if this job ever
  gains a secret, writes or restores a cache, or runs on a fork trigger.
- **Formatting.** `php-cs-fixer` with `nextcloud/coding-standard` owns it, ESLint and
  Stylelint own the frontend. Tabs, brace placement and import order are not review
  material.
- **There is no Rector configuration, on purpose.** Its Nextcloud sets for 33, 34 and
  35 are the same file, and running them against `lib/` changes nothing. Keeping the
  tool would add a dependency project that only earns its keep when a future server
  renames an API. Run it from a throwaway checkout when the supported range moves —
  `doc/development-setup.md` says how — rather than carrying it here.
- **`package-lock.json` churn** in a dependency update, and generated translation
  files.
- **Test doubles that look over-specific.** Assertions on exact mock calls are
  deliberate here; the unit tests are meant to pin behaviour, not to be flexible.

- **Precision where a mistake is cosmetic, caution where it is fatal.** URL detection
  in `TemplateRenderer` serves the auto-linking, where getting a boundary wrong costs
  a link. It is deliberately **not** the security boundary: where a URL ends in free
  text is not decidable, and every mail client answers it differently. The one check
  that decides whether the code may be sent runs on the **finished** mail
  (`LinkScanner::couldLeakCode()`), is cruder than everything before it, and cannot
  fail. When in doubt it says yes, because sending the default text for one mail
  without need is better than letting one code slip through.
  Making the template checks carry that job produced eleven review rounds of boundary
  bugs (2026-08-06); do not move it back. `hasPlaceholderInUrl()` and
  `couldLeakCode()` read a text into addresses through the **same** function, and
  must keep doing so: if the write-side check were the narrower one, an admin could
  save a text that is then never sent.
  `LinkScanner` is where both live, so neither the renderer nor the validator depends
  on the other (2026-08-26).

- **The check on the finished mail asks about the one-time code, nothing else.** It is
  the last stop before sending, and it exists for the secret that logging in depends
  on. Any other value in a web address — a display name in a tracking link, say — is
  refused when the text is written and reported on upgrade, but it is not stopped
  again at send time: it is not a secret, and replacing the admin's whole text over a
  privacy nuisance they can fix themselves is the worse trade. `doc/developers.md`
  states the limit. Decided 2026-08-06; widening it is a product decision, not a bug.

- **A stored text with a placeholder in a web address saves without a warning in
  the admin UI.** The save is deliberately not blocked — an instance can carry such
  a text without anyone having typed it there, and blocking would freeze every other
  setting. Reporting it *without* blocking needs a second channel next to `errors`
  in the JSON and a warning state in the field component; that is a UI change with
  its own cut, not an oversight. Until then the condition is named by `occ upgrade`,
  by the log on every affected mail, and by the validator as soon as the text is
  touched. Known since 2026-08-06.

- **`{logo}` inside a web address is allowed.** It expands to an image tag or to
  nothing and never inserts a value, so it hands no data to whoever owns the address.
  Only the placeholders in `TemplateRenderer::VALUE_PLACEHOLDERS` are refused there.
  The list belongs to the renderer, which builds its values by walking it, so a
  placeholder cannot be substituted without the settings checks knowing it — psalm
  reports the incomplete match instead of a test comparing two lists.

- **The fallback to the default text is not checked again before sending.** The
  default keeps `{code}` in a paragraph of its own, outside every translatable
  string, so neither a translation nor an inserted value can move an address next to
  it. `DefaultEMailTextsTest` asserts both halves of that for **every** shipped
  language: that the body still contains `"\n\n{code}\n\n"`, and that rendering it
  with a display name and an instance name that end in an unfinished web address
  delivers the code and keeps it out of every address. A run-time check of our own
  text could only fire if that text were changed in the source, which is where a test
  belongs (2026-08-26).
  A **theme** can replace a translated string of ours — a translation file under
  `themes/` that the server merges over the app's own — and the substitution would
  then run on that text. That needs write access to the instance, which
  `doc/threat-model.md` puts out of scope: the same access removes the check. It is
  not a separate attack path.

## What "ready" means here

CI green, no new Psalm or taint findings, and — for anything touching routes,
controllers or the challenge flow — the smoke test run against **both** ends of the
supported Nextcloud range. One server version is not a test of a version range: a
resend endpoint once worked on the newest server and was dead on the oldest, and only
that two-version run found it.

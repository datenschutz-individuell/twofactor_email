# Working on this app

Notes for AI agents. Facts and conventions only — the reasoning lives in
[`doc/`](doc/), and this file should stay short enough that reading it is never a
detour. [`REVIEW.md`](REVIEW.md) says what review pays attention to here and which
questions are already settled.

## What it is

A two-factor provider for Nextcloud that mails a one-time code. It plugs into
Nextcloud's two-factor framework and is built against the public `OCP` interfaces
only. [`doc/architecture.md`](doc/architecture.md) explains how the pieces fit;
[`doc/threat-model.md`](doc/threat-model.md) states what the app defends against.

## Supported versions

| | |
|---|---|
| Nextcloud | 33–34 (`appinfo/info.xml`) |
| PHP | 8.2–8.5 |
| Node | `^24 \|\| ^26` |

**The CI's PHP floor is derived from the Nextcloud `min-version`**, not from
`<php min-version>`: `icewind1991/nextcloud-version-matrix` takes the minimum PHP of
the oldest supported server. Raising the PHP floor therefore means dropping the
Nextcloud versions that still allow the older PHP. When you touch the range, change
`info.xml` (both), `composer.json` (`require.php`, `config.platform.php`,
`nextcloud/ocp`) and `psalm.xml` together, then check that CI agrees. Nextcloud 35
requires PHP 8.3, so supporting it means dropping every server that still allows 8.2.

## Layout

- `lib/` — PHP, namespace `OCA\TwoFactorEMail`. Controllers use routing attributes
  (`#[FrontpageRoute]`); there is no `appinfo/routes.php` any more.
- `src/` — Vue 3 with Pinia, tested with Vitest.
- `templates/` — four templates: the login challenge, the enrolment step shown during
  login (`LoginSetup`, an `ILoginSetupProvider`), and the admin and personal settings.
- `tests/Unit/` — PHPUnit, mirrors `lib/`.
- `tests/smoke/` — the app running in a disposable Nextcloud; use it for anything
  touching routes, controllers or the challenge flow. See its
  [README](tests/smoke/README.md).

## Commands

```bash
composer test:unit:dev            # PHPUnit without coverage
composer cs:check                 # php-cs-fixer, nextcloud/coding-standard
composer psalm                    # static analysis
composer psalm:taint              # taint analysis, must stay empty
npm run lint && npm run stylelint && npm test && npm run build
krankerl package                  # release package
```

Psalm **analyses** against the app's minimum PHP version — `phpVersion` in `psalm.xml`,
and the CI checks that the two agree. That is independent of the interpreter it **runs**
on: Psalm 6.16.1 runs fine on PHP 8.5, verified on 8.5.9 for both the normal and the
taint pass. If a future version does cap the runtime, the error says so; do not reach
for an older interpreter without one.

Run the checks that match your diff **before** opening a pull request. CI is the gate,
but finding it locally is cheaper for everyone.

**`composer outdated` in the root does not see `vendor-bin/*`.** The bamarni plugin
gives those their own projects — use `composer bin all outdated`.

**A root `composer install` also installs `vendor-bin/*`**, through a hook that exists
so working in the repo takes one command instead of two. CI and the package build do
not need the tooling and pass `--no-scripts`; the only job that keeps it is the one
running `cs:check`. A new workflow that installs dependencies should pass
`--no-scripts` too.

**Query npm with `--all`.** `npm ls <package>` and friends traverse shallowly without
it and silently miss deeper paths, which is how a dependency once looked dev-only when
it was not. After an update, read the warnings and remove their cause rather than
treating them as background noise, and re-check whether the existing pins and
`overrides` still change anything — every one of them is debt.

## Conventions

- **Every new file needs licensing.** Either an SPDX header or an entry in
  `REUSE.toml`; the `reuse` CI job fails otherwise. Root-level Markdown is licensed
  through an **explicit filename entry** in `REUSE.toml` — only `doc/**` and `l10n/**`
  are globbed, so a new root file has to be added there by hand.
- **GitHub Actions are pinned to a commit SHA** with a version comment, and every
  checkout sets `persist-credentials: false`. Follow the existing workflows.
- **A method that overrides or implements anything carries `#[\Override]`** — an
  interface, an abstract class, a parent method, whether from OCP, Symfony or this app.
  Psalm requires it (`ensureOverrideAttribute`) and names every method missing one. It
  is what turns a method Nextcloud has renamed into an analysis error instead of code
  that is quietly never called again.
- **A version bump touches four places:** `appinfo/info.xml`, `package.json`, and
  **both** version fields in `package-lock.json` — plus a `CHANGELOG.md` section with
  the release date. Change the values in place; do not re-resolve the lock file during
  a release.
- **Decide for every new file whether it belongs in the release package.**
  `.nextcloudignore` keeps the package to runtime files; a new file at the root ships
  unless it is listed there.
- **Write texts for translation.** Comments, docblocks and user-facing strings in
  plain, simple English — short sentences, no idioms, nothing that only makes sense in
  German. Translators and non-native readers both benefit.
- **Changelog entries are one terse line each**; the reasoning belongs in the commit
  message, not in `CHANGELOG.md`.
- **Parametrise a test only when setup *and* expected result are identical** across the
  cases. Differing expectations, or a different mock mechanism per case, mean separate
  tests — a parametrised test with a branch inside it hides what it checks.
- **SOLID pragmatically, not dogmatically.** Single-purpose classes, no abstraction
  introduced for a second implementation that does not exist yet. Maintainability first.
- Do not reformat code you are not otherwise changing.

## Things that look wrong and are not

- **`ChallengeController::resend()` carries both `#[NoTwoFactorRequired]` and a
  `@NoTwoFactorRequired` docblock annotation.** Nextcloud 33 reads the exemption from
  the docblock only; the attribute is `@since 34`. Removing either one breaks a
  supported server. Nextcloud 35 still reads both, so this is never urgent —
  `CompatibilityShimsTest` fails as soon as `min-version` reaches 34 and names the
  three pieces that go together: the annotation, the `UndefinedAttributeClass`
  handler in `psalm.xml`, and the `findUnusedIssueHandlerSuppression` beside it.
- **`symfony/console` is held at `^6.4.42`.** Nextcloud bundles Symfony 6.4, and `occ`
  commands must build against the same major.
- **`allowScripts` in `package.json` lists `fsevents`, which is never installed here.**
  It is darwin-only; the entry exists for macOS machines, where npm would otherwise
  warn about its install script. `npm install-scripts prune` reports it as unused and
  removes it on Linux — do not run that blindly, and note that
  `npm install-scripts deny fsevents` cannot recreate it here (`ENOMATCH`).
- **`@nextcloud/vite-config` is pinned to a pre-release.** Only that version allows
  Vite 8; the stable line is still on Vite 7. **This one expires:** switch as soon as a
  stable release supports Vite 8, and treat the pin as a finding from then on.

## Releasing

`krankerl package` packages the **committed** state, not the working tree — an
uncommitted fix is not in the package, and a test against it proves the old behaviour.
The rest, including why a published release stays invisible to instances for a while,
is in [`doc/releasing.md`](doc/releasing.md).

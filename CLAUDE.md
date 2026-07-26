# Working on this app

Notes for AI agents. Facts and conventions only — the reasoning lives in
[`doc/`](doc/), and this file should stay short enough that reading it is never a
detour.

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
`nextcloud/ocp`) and `psalm.xml` together, then check that CI agrees.

## Layout

- `lib/` — PHP, namespace `OCA\TwoFactorEMail`. Controllers use routing attributes
  (`#[FrontpageRoute]`); there is no `appinfo/routes.php` any more.
- `src/` — Vue 3 with Pinia, tested with Vitest.
- `templates/` — the login challenge plus the three settings templates.
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

Psalm runs on the app's **minimum** PHP version and does not support newer runtimes;
if your default PHP is newer, invoke it with the older one.

**`composer outdated` in the root does not see `vendor-bin/*`.** The bamarni plugin
gives those their own projects — use `composer bin all outdated`.

## Conventions

- **Every new file needs licensing.** Either an SPDX header or an entry in
  `REUSE.toml`; the `reuse` CI job fails otherwise. Root-level Markdown is covered by
  `REUSE.toml`, other files carry headers.
- **GitHub Actions are pinned to a commit SHA** with a version comment, and every
  checkout sets `persist-credentials: false`. Follow the existing workflows.
- **A version bump touches four places:** `appinfo/info.xml`, `package.json`, and
  **both** version fields in `package-lock.json` — plus a `CHANGELOG.md` section with
  the release date. Change the values in place; do not re-resolve the lock file during
  a release.
- Do not reformat code you are not otherwise changing.

## Things that look wrong and are not

- **`ChallengeController::resend()` carries both `#[NoTwoFactorRequired]` and a
  `@NoTwoFactorRequired` docblock annotation.** Nextcloud 33 reads the exemption from
  the docblock only; the attribute is `@since 34`. Removing either one breaks a
  supported server. The annotation goes when `min-version` reaches 34.
- **`symfony/console` is held at `^6.4.42`.** Nextcloud bundles Symfony 6.4, and `occ`
  commands must build against the same major.
- **`@nextcloud/vite-config` is pinned to a pre-release.** Only that version allows
  Vite 8; the stable line is still on Vite 7.

## Releasing

`krankerl package` packages the **committed** state, not the working tree — an
uncommitted fix is not in the package, and a test against it proves the old behaviour.
The rest, including why a published release stays invisible to instances for a while,
is in [`doc/releasing.md`](doc/releasing.md).

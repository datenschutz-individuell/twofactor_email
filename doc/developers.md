# For developers

The app plugs into Nextcloud's [two-factor provider framework](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/two-factor-provider.html) and is built against Nextcloud's public interfaces ([`OCP`](https://github.com/nextcloud-deps/ocp)). See [architecture.md](architecture.md) for how the components fit together and where data lives.

## Building & testing

- Set up a local dev environment with Docker: see [development-setup.md](development-setup.md).
- Build the release package with `krankerl package`, or follow the manual steps in the README's [Building yourself](../README.md#building-yourself).
- **PHP:** PHPUnit for the services, php-cs-fixer for style, and Psalm (including taint analysis) for static analysis, which *analyses* against the app's minimum PHP version — see below.
- **Frontend:** Vitest for the logic and components, plus ESLint and Stylelint; `npm run build` produces the bundle.

## Contributing

Contributions are welcome — see [CONTRIBUTING](../CONTRIBUTING.md).

1. **Discuss larger ideas first** in the [idea collection](https://github.com/datenschutz-individuell/twofactor_email/issues/8), so nobody builds something that will not be merged.
2. **Branch off `main`** and keep one topic per pull request. A focused change can be reviewed properly; a mixed one usually cannot.
3. **Run the checks locally before opening the PR** — CI runs the same ones, but finding it out yourself is faster:
   ```
   composer test:unit:dev && composer psalm && composer cs:check
   npm run lint && npm run stylelint && npm test && npm run build
   ```
   `composer cs:fix` applies the style fixes automatically. Note that Psalm
   *analyses* against the minimum PHP version the app supports, set as `phpVersion`
   in `psalm.xml`; that is not a limit on the interpreter it runs on. Should a tool
   ever refuse your default PHP, it says so, and an older interpreter next to it
   helps (on Arch Linux, for example, `php-legacy vendor/bin/psalm.phar`).
4. **Add SPDX headers to new files.** The project is [REUSE](https://reuse.software/) compliant and CI enforces it; files that cannot carry a header (images, generated files) are annotated centrally in `REUSE.toml`.
5. **Describe *why* in the commit message**, not *what* — the diff already shows what changed.
6. **CI is the gate.** All checks have to pass; a red run will not be merged.

If a change affects behaviour, say how you verified it. Unit tests cover the
services and the frontend logic, but route registration, emails and the login flow
only show up in a running Nextcloud — see [development-setup.md](development-setup.md).

## Security mechanisms

What these mechanisms promise, and against whom, is bounded by the [threat model](threat-model.md): input through an interface is checked; write access to the instance's files or database is not something they can defend against.

- **Code generation.** `NumericalCodeGenerator` uses `OCP\Security\ISecureRandom` (a CSPRNG) with `CHAR_DIGITS` and the configured length. No `rand()`/`mt_rand()`.
- **Storage at rest.** `CodeStorage` stores `IHasher::hash($code)` — never the plaintext — in the user's config, plus a creation timestamp. The value is flagged `IUserConfig::FLAG_SENSITIVE`, so it is masked in `occ config:list` and in system/support reports.
- **Verification.** `IHasher::verify()` is constant-time. The code is deleted only on **successful** verification (so a mistyped code can be retried); wrong tries are absorbed by Nextcloud's brute-force protection.
- **Issuance policy.** A new code is issued only when no valid code is stored, which stops login-page reloads from flooding the mailbox. The new hash is persisted **only after** the email was sent successfully, so a failed delivery leaves the previous code valid.
- **Resend throttling.** `ChallengeController::resend()` carries `#[NoAdminRequired]`, `#[NoTwoFactorRequired]`, `#[UserRateLimit(limit: 1, period: 60)]` and `#[BruteForceProtection]`, on top of the app-level resend cooldown (`ResendTooSoon`). See Nextcloud's [rate limiting](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/security.html#rate-limiting) docs.
- **Sensitive-action confirmation & CSRF.** `StateController::save()` (enable/disable) carries `#[PasswordConfirmationRequired]`. All controllers are session-authenticated Nextcloud controllers subject to its CSRF protection.
- **Email content safety.** Everything is HTML-escaped (`htmlspecialchars`); raw HTML is impossible. Placeholders (`{code}`, `{user}`, `{cloud}`, `{validity}`, `{logo}`) and detected URLs are escaped individually, values are inserted in a single pass so an inserted value cannot smuggle in a placeholder, and inside a URL the values are inserted bare so no markup reaches an attribute. Whether the one-time code may leave the system is decided **once, on the finished mail**: `EMailSender` asks `LinkScanner::couldLeakCode()` whether the code ended up in something a link scanner would fetch — including an address that a display name built around it, which no check on the template could see. If so the mail is sent with the localized default text instead, which keeps `{code}` in a paragraph of its own where no inserted value can reach it — `DefaultEMailTextsTest` renders it in every translated language to keep that true. That last check asks about the **one-time code**; any other value in a web address is refused when the text is written, and an already stored one is reported on upgrade, but it is not stopped again at send time. `SettingsValidator` still refuses such a template when it is written, so the admin hears about it early, but the guarantee does not depend on it. The **subject** has CR/LF stripped as defense-in-depth against header injection.
- **Input validation.** `SettingsValidator` enforces numeric ranges and string limits for every admin setting and is the single validation path shared by the web controller and the `occ` command.

## Assurance

- **Static analysis:** Psalm (errorLevel 3) plus **Psalm taint analysis** (source-to-sink SAST, added in 3.4.0) — currently **no findings**. Taint rides on the annotations shipped in [`nextcloud/ocp`](https://github.com/nextcloud-deps/ocp), so it needs no extra stubs.
- **Tests:** PHPUnit for the PHP services, Vitest for the frontend logic and components.
- **Frontend SAST:** CodeQL (JavaScript) via the repository's default setup.
- **Supply chain:** `roave/security-advisories` blocks known-vulnerable composer packages; Dependabot tracks npm/composer updates; the release package ships only runtime files.

## Licensing

The app is licensed [AGPL-3.0-or-later](../LICENSES/AGPL-3.0-or-later.txt); bundled assets keep their own licences (e.g. the app icon). [LICENSE.md](../LICENSE.md) lists every licence used here and how the REUSE/SPDX metadata is applied. Add an SPDX header to each new file, or an entry in `REUSE.toml` where a header is impossible; CI verifies it.

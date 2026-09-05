# Contributing

Thanks for your interest in improving this app! It's a community effort, and
contributions of all kinds are welcome — code, tests, documentation,
translations, bug reports, and ideas.

By participating, you agree to follow our [Code of Conduct](CODE_OF_CONDUCT.md).

## Before you start

Please discuss non-trivial ideas in the
[idea collection](https://github.com/datenschutz-individuell/twofactor_email/issues/8)
before opening a pull request, so nobody spends time on something that won't
be merged.

Note that this branch is the maintenance line for the 3.3 releases. New
features go to `main`; here we take fixes and the changes that keep both lines
in step.

## Pull requests

1. **Keep one topic per pull request.** A focused change can be reviewed
   properly; a mixed one usually cannot.
2. **Run the checks locally before opening the pull request** — CI runs the
   same ones, but finding it out yourself is faster:
   ```
   composer test:unit:dev && composer psalm && composer cs:check
   npm run lint && npm run stylelint && npm test && npm run build
   ```
   `composer cs:fix` applies the style fixes automatically.
3. **Add SPDX headers to new files.** The project is
   [REUSE](https://reuse.software/) compliant and CI enforces it; files that
   cannot carry a header are annotated centrally in `REUSE.toml`. See
   [LICENSE.md](LICENSE.md) for the licenses in use.
4. **Describe *why* in the commit message**, not *what* — the diff already
   shows what changed.
5. **CI is the gate.** All checks have to pass; a red run will not be merged.

Building the app is described in the README under
[Building yourself](README.md#building-yourself).

## Translations

This app uses Transifex. If it's not yet available in your language, join the
[Nextcloud translators community](https://explore.transifex.com/nextcloud/).

## Security issues

[SECURITY.md](SECURITY.md) says when a vulnerability may be reported as a
public issue and when it should reach the maintainers privately. Please
follow it rather than guessing.

## Questions

If you have any questions, reach out to the maintainers listed in
[CONTRIBUTORS.md](CONTRIBUTORS.md).

# License

This project is [REUSE](https://reuse.software/) compliant: every file carries
clear copyright and license metadata, either inline (in its own header) or
via [`REUSE.toml`](REUSE.toml) for files that cannot carry a header (e.g.
binaries, lockfiles). This is verified in CI. Full license texts are in
[`/LICENSES`](LICENSES).

## Code

Unless stated otherwise in a file's own header, the application is licensed
under the **GNU Affero General Public License v3.0 or later
([AGPL-3.0-or-later](LICENSES/AGPL-3.0-or-later.txt))**. This is also the
default applied via `REUSE.toml` to files that cannot carry a header (config,
lockfiles, translations).

## Other licenses used in this repository

| License | SPDX identifier | Used for |
| --- | --- | --- |
| GNU Affero General Public License v3.0 or later | [`AGPL-3.0-or-later`](LICENSES/AGPL-3.0-or-later.txt) | The project's default license: application source code and most other files, either via their own header or, where a header isn't possible, via `REUSE.toml` (config, lockfiles, translations) |
| Apache License 2.0 | [`Apache-2.0`](LICENSES/Apache-2.0.txt) | `img/app.svg` and `img/app-dark.svg` (© Google LLC) |
| MIT License | [`MIT`](LICENSES/MIT.txt) | Every GitHub Actions workflow except `reuse.yml`, plus `codecov.yml`, kept permissive so other projects can freely reuse this CI setup. The remaining files under `.github` stay AGPL |
| Creative Commons Zero v1.0 Universal | [`CC0-1.0`](LICENSES/CC0-1.0.txt) | `.github/workflows/reuse.yml`, the REUSE-compliance check template provided by the FSFE |

Every file decides its own license. The table above is a quick overview, not
a substitute for checking the file itself. To see exactly which license
applies to a given file, check its header, or run `reuse spdx` for a full,
file-by-file report.

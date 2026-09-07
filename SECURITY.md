# Security Policy

The security model is documented by audience — [users](doc/users.md), [administrators](doc/admins.md), and [developers](doc/developers.md) — with a separate [threat model](doc/threat-model.md). For how the app is built, see [doc/architecture.md](doc/architecture.md).

## Supported Versions

**What we promise:** every Nextcloud version that Nextcloud itself still supports is served by a line of this app that gets security fixes. Today that is the **3.5** line, for Nextcloud 33 to 35.

**What we offer beyond it:** an older Nextcloud keeps the last line that ran on it — **3.3** for Nextcloud 32, version **2.8** below that — for as long as fixing it stays reasonable. We judge that per case, and nothing here commits us to it.

Within a line only its latest release is fixed, so update to that one before reporting.

## Reporting a Vulnerability

Report a bug, an easy-to-fix flaw, or one that only hits rare cases as a GitHub issue. If you see no easy fix *and* many users would be affected, email Olav directly at <olav@seyfarth.de>, ideally encrypted — his OpenPGP key 0x6AE1EF56 and the other channels he reads are on his [website](https://olav.seyfarth.de/).

We review reports promptly and fix what we can, unless the cause is upstream. Please give us contact details so we can reach you. When the fix is published we would like to credit you, so tell us how you want to be named. See [CONTRIBUTORS](https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md) for examples.

We cannot pay a bounty. If the flaw would affect many users or Nextcloud as a platform, report it to the [Nextcloud security team](https://nextcloud.com/security/) as well.

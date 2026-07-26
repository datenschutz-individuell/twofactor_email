<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Releasing

Notes for whoever publishes a release. The concrete infrastructure — which host signs,
where the key lives, how the instances are updated — is deliberately not part of this
document; it is specific to the maintainer's setup. What is here is the part that is
true for anyone publishing a Nextcloud app, and most of it was learned the hard way.

## Before building

**Raise the version in every place that carries it.** For this app that is
`appinfo/info.xml`, `package.json`, and **both** version fields in
`package-lock.json` (the top-level one and `packages[""]`). Missing the lock file is
easy and stays invisible until someone compares the shipped package with the tag.
Change the value in place — do not let a tool re-resolve the dependency tree during a
release.

**Add the changelog section for exactly that version** before building. Release notes
are generated from it, so a missing section means empty notes.

**Check the dependencies:** `composer bin all outdated` (the plain `composer outdated`
does **not** look into the `vendor-bin/*` sub-projects the bamarni plugin creates) and
`npm outdated --all`. Also check whether the declared floors can be raised to what is
actually installed, and whether existing pins and `overrides` are still needed.

**Look at the open security alerts.** Either fix them or close them with a reason —
"scope: development only" is a judgement that has to be made per case, not assumed.

## Building

`krankerl package` packages the **committed** state, not the working tree. An
uncommitted fix is not in the package, and any test you then run proves the old
behaviour. This has bitten us: a smoke test once passed on a package that predated the
change it was meant to verify.

## Testing before publishing

Run `tests/smoke/smoke.sh` against the built package. It covers **both ends of the
supported server range**, which is not pedantry: 3.4.0 shipped a resend endpoint that
was dead on Nextcloud 33 and fine on 34, because the exemption from the two-factor
gate is taken from the docblock on 33 and from the PHP attribute on 34, and that
attribute is `@since 34`. A single manual pass on a current server cannot see that
class of bug.

What the smoke test cannot check is how it looks. Leave one instance running
(`KEEP=1`) and look at the challenge dialog, dark mode and a non-English locale.

## After publishing: why nobody sees it yet

This is the part that looks like a bug in your instances and is not. Two independent
caches sit between the app store and an `occ app:update`.

**The store's own list.** `apps.nextcloud.com` serves
`api/v1/platform/<ncversion>/apps.json` through a cache, and a freshly published
version is not in it immediately — in one measured case about 15 minutes. Appending a
query string bypasses that cache and shows the fresh list, which is useful for
comparing but is not a fix: instances request the plain URL. There is nothing to flush
here, only to wait.

**Each instance's copy.** Nextcloud caches the list in
`<datadir>/appdata_<instanceid>/appstore/apps.json` for an hour
(`Fetcher::INVALIDATE_AFTER_SECONDS`), and both `occ app:update` and
`occ update:check` read it through `Installer::isUpdateAvailable()`. To force a
refetch, **truncate** that file rather than deleting it: appdata is managed through
the Files API, so removing the file on disk leaves an orphaned filecache entry. An
empty file parses as invalid JSON, so Nextcloud fetches again and overwrites it. Do
every access as the owner of the data directory — it is not readable by anyone else,
and a check that fails there makes the flush do nothing while looking like it worked.

**The store rate-limits per IP**, and this is the answer to a puzzle that looked
random for years: when several instances behind one address fetch the ~4 MB list in
quick succession, some get `429 Too Many Requests`. Nextcloud catches that exception
and returns an empty list, so a throttled instance reports "All apps are up-to-date"
— indistinguishable from "nothing to do". That is why, after a release, a seemingly
arbitrary subset of instances updates, and a different subset the next time. If you
run more than one or two instances from the same address, put a caching proxy in front
of the store (Nextcloud's `appstoreurl` can point at it) and serve stale content on
`429`; then a throttle can no longer masquerade as "up to date".

**Silent blockers worth knowing:** an app directory that is a git checkout is never
updated, and `appstoreenabled=false` stops the store being asked at all. Neither says
so.

## Signing

The store expects a signature over the tarball, and Nextcloud expects
`appinfo/signature.json` inside it. These are two different things made by two
different tools — `occ integrity:sign-app` writes the file, `openssl dgst -sha512
-sign` produces the signature you hand to the store API. `krankerl` does not create
`signature.json`; expecting it to is a dead end.

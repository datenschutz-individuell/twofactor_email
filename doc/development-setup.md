# Development setup

Three ways to work on this app, in the order most work needs them: no server at all, a disposable one, or a full Nextcloud development environment.

## Without a Nextcloud

Most of the work needs none. Clone the repository, then:

```bash
composer install     # PHPUnit, Psalm, php-cs-fixer and the app's own dependencies
npm ci
npm run build        # or npm run dev while working on the frontend
```

The unit tests, the component tests, the static analysis and both linters all run from here; [developers.md](developers.md) lists them. What they cannot see is route registration, the challenge email and the login flow — those need a server.

## A disposable Nextcloud

The app supports a range of Nextcloud versions, and a bug can be specific to one of them. [`tests/smoke/`](../tests/smoke/) holds a disposable instance built from the official image — SQLite plus a mail catcher, because without SMTP the challenge email never arrives and the login flow cannot be tested at all.

```bash
krankerl package                  # in the repository root; both start from the package
cd tests/smoke

./smoke.sh                        # either: the checks, every server in the range
NC_TAG=33-apache ./setup.sh       # or:     one instance, left up to click around in
```

The last two are alternatives, not a sequence: `smoke.sh` refuses to run while an instance is up rather than pulling it away from whoever is using it. `setup.sh` prints the URLs and how to switch the provider on for the user; `docker compose down -v` removes everything again.

While you still have uncommitted work, there is no package to test — `krankerl` packages the committed state, and `smoke.sh` stops rather than prove the wrong thing. Mount the working tree instead:

```bash
( cd "$(git rev-parse --show-toplevel)" && composer install -o && npm ci && npm run build )
APP_DIR="$(git rev-parse --show-toplevel)" ./smoke.sh
```

Prefer the packaged run before a release, though: it is what users get, and it catches what a checkout hides — a file missing from the release because of `.nextcloudignore` cannot show up when the working tree is mounted.

The options, what the checks cover, and a list of things that behave surprisingly are in [tests/smoke/README.md](../tests/smoke/README.md).

If the Docker daemon refuses to start with `error initializing graphdriver: driver not supported`, the kernel was updated without a reboot since — the modules of the running kernel are gone, so overlayfs cannot be loaded. Rebooting fixes it.

## A full development environment

Work that needs Xdebug, several server versions side by side, LDAP, or the server sources themselves belongs in Nextcloud's own setup, [nextcloud-docker-dev](https://github.com/nextcloud/nextcloud-docker-dev). Its [documentation](https://nextcloud.github.io/nextcloud-docker-dev/) has the installation and the options; copying them here is how they went stale the last time.

Two things are specific to this app once that environment runs. Clone it into `workspace/server/apps/`, then `composer i && npm ci && npm run build` in it — Nextcloud shows it after that, and it works once enabled. And the container is called `master-nextcloud-1`, because the setup ships `COMPOSE_PROJECT_NAME=master`:

```bash
docker exec -ti master-nextcloud-1 tail -f data/nextcloud.log
docker exec -ti master-nextcloud-1 bash -c 'cd apps/twofactor_email && composer test'
```

## The one check that is not in a lock file

`composer install` brings PHPUnit, Psalm and php-cs-fixer, `npm install` the JavaScript side. Both put their tools inside the checkout. The `reuse` check has no such home, so it is the one that usually reports itself through a red CI job:

```
# on Arch, as root
pacman -S reuse
```

Other systems are covered on [reuse.software](https://reuse.software/) — `pipx install reuse` works everywhere Python does. Install it **for the machine, not for one account**: unlike the tools above it is not part of the checkout, and a check only one user can run is one the next person does not know about.

`reuse lint` in the repository root then answers what the CI job answers: does every file carry copyright and license information, in an SPDX header or through an entry in `REUSE.toml`. A file with neither is the usual reason that job turns red. Watch the entries that are globbed — `doc/**` (the screenshots live there), `vendor-bin/*/composer.json` and `.lock`, and under `l10n/` only `**.js`, `**.json` and `**.php`. Anything outside those patterns, a new file at the root above all, needs a **named** entry or a header of its own.

Rector is the other tool that is not installed here, and CI does not run it either. That is deliberate; see below.

## Rector, when the Nextcloud range moves

Rector can rewrite calls that a new server renamed, but it is not part of this project: its Nextcloud sets for 33, 34 and 35 are the same file, and over `lib/` they propose nothing at all. Run it from a throwaway directory when the supported range moves, then throw the directory away:

```bash
mkdir -p /tmp/rector && cd /tmp/rector
composer require --dev rector/rector nextcloud/rector
cat > rector.php <<'PHP'
<?php
use Nextcloud\Rector\Set\NextcloudSets;
use Rector\Config\RectorConfig;
return RectorConfig::configure()
	->withPaths([
		'/path/to/twofactor_email/lib',
		'/path/to/twofactor_email/tests',
	])
	->withAutoloadPaths([
		'/path/to/twofactor_email/vendor/autoload.php',
		'/path/to/twofactor_email/tests/bootstrap.php',
	])
	->withSets([NextcloudSets::NEXTCLOUD_35]);
PHP
vendor/bin/rector process --dry-run
```

`--dry-run` prints the changes without writing them; read them before applying anything by hand.

**Both autoload paths are required.** Without the app's own dependencies, Rector cannot see the classes the code inherits from, and a rule that needs that information proposes the wrong change with the same confidence as a right one — in one run it wanted to delete every `parent::setUp()` in the test suite. `nextcloud/ocp` ships no autoload section of its own, which is why `tests/bootstrap.php` belongs in the list: it registers the autoloader for `OCP\` and `NCU\` that nothing else provides.

Read each proposal on its merits rather than applying the run wholesale, and expect to reject some. Adding the PHPUnit code-quality set produced four rules over `tests/`, of which two were worth taking: `parent::setUp()` is kept here deliberately, and a `(string)` cast it suggests is already guaranteed by the declared types.

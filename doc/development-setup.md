# Development setup

How to set up a local environment to work on this app, based on Nextcloud's Docker dev image, [nextcloud-docker-dev](https://github.com/juliushaertl/nextcloud-docker-dev).

## Docker-based environment

1. **Install Docker** (Docker Desktop, or the engine plus compose) — see the [Docker docs](https://docs.docker.com/desktop/install/ubuntu/).
2. **Set up Nextcloud's dev image:**
   ```
   git clone https://github.com/juliushaertl/nextcloud-docker-dev
   cd nextcloud-docker-dev
   ./bootstrap.sh
   ```
   Start it — needed every time — with `docker compose up nextcloud`; stop it with Ctrl+C.
3. **Clone the app into the workspace:**
   ```
   cd workspace/server/apps
   git clone git@github.com:datenschutz-individuell/twofactor_email.git
   ```
4. **Install dependencies and build the frontend:**
   ```
   composer i
   npm ci
   npm run build
   ```

The app should now appear in Nextcloud and work once enabled.

## Common commands

- **Tail the PHP error log:** `docker exec -ti master-nextcloud-1 tail -f data/nextcloud.log`
- **Rebuild the frontend** after changing Vue/JS files: `npm run build` (or `npm run dev`).
- **Run the tests** inside the container:
  ```
  docker exec -it master-nextcloud-1 bash
  cd apps/twofactor_email/
  composer run-script test
  ```
- **Test against a specific Nextcloud version** — see below.

## Minimalist setup on Arch Linux

Credits: @seyfahni. Follows the [nextcloud-docker-dev documentation](https://juliushaertl.github.io/nextcloud-docker-dev/).

```
# as root
pacman -S docker docker-compose docker-buildx
# optional: more networks with fewer hosts each
cat >/etc/docker/daemon.json <<'EOF'
{
  "default-address-pools": [
    { "base": "172.16.0.0/12", "size": 26 }
  ],
  "features": { "buildkit": true }
}
EOF
systemctl enable --now docker.socket
usermod -aG docker <DEV_USER>
# log off and back on for the group change to take effect

# as DEV_USER (with a working GitHub SSH setup)
git clone git@github.com:juliushaertl/nextcloud-docker-dev.git
cd nextcloud-docker-dev
./bootstrap.sh

docker compose up --pull always -d nextcloud
# Nextcloud is then reachable at http://nextcloud.local/  (user: admin, password: admin)
```

## Throwaway instance for a specific Nextcloud version

The app supports a range of Nextcloud versions, and a bug can be specific to one of
them. [`tests/smoke/`](../tests/smoke/) holds a disposable instance built from the
official image — SQLite plus a mail catcher, because without SMTP the challenge email
never arrives and the login flow cannot be tested at all.

```bash
krankerl package                  # in the repository root; both start from the package
cd tests/smoke

./smoke.sh                        # either: the checks, oldest and newest server
NC_TAG=33-apache ./setup.sh       # or:     one instance, left up to click around in
```

The last two are alternatives, not a sequence: `smoke.sh` refuses to run while an
instance is up rather than pulling it away from whoever is using it. `setup.sh` prints
the URLs and how to switch the provider on for the user; `docker compose down -v`
removes everything again.

While you still have uncommitted work, there is no package to test — `krankerl` packages
the committed state, and `smoke.sh` stops rather than prove the wrong thing. Mount the
working tree instead:

```bash
composer install -o && npm ci && npm run build
APP_DIR="$(git rev-parse --show-toplevel)" ./smoke.sh
``` The options, what the checks cover,
and a list of things that behave surprisingly are in
[tests/smoke/README.md](../tests/smoke/README.md).

Testing the **built package** rather than the working tree is deliberate: it is what
users get, and it catches what a checkout hides — `krankerl` packages the committed
state, so an uncommitted fix is simply not in it.

If the Docker daemon refuses to start with `error initializing graphdriver: driver not
supported`, the kernel was updated without a reboot since — the modules of the running
kernel are gone, so overlayfs cannot be loaded. Rebooting fixes it.

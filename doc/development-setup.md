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
- **Test against a specific Nextcloud version** — pick a branch or tag from [nextcloud/server](https://github.com/nextcloud/server) and mount your local checkout (replace `/PATH/TO` with the path to your `nextcloud-docker-dev` clone):
  ```
  docker run --rm -p 8080:80 -e SERVER_BRANCH=v27.1.7 \
    -v /PATH/TO/nextcloud-docker-dev/workspace/server/apps/twofactor_email:/var/www/html/apps/twofactor_email \
    ghcr.io/juliushaertl/nextcloud-dev-php80:latest
  ```

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

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
them. The quickest way to check one is a disposable instance built from the official
image. SQLite is enough, and a mail catcher is **required** — without SMTP the
challenge email never arrives, so the login flow cannot be tested at all.

`compose.yaml`:

```yaml
services:
  nextcloud:
    image: nextcloud:33-apache          # pick the version you want to test
    ports: ["8080:80"]
    environment:
      SQLITE_DATABASE: nextcloud
      NEXTCLOUD_ADMIN_USER: admin
      NEXTCLOUD_ADMIN_PASSWORD: admin
      NEXTCLOUD_TRUSTED_DOMAINS: localhost
    volumes:
      - nc-data:/var/www/html
      # the built app, not the working tree — test what gets shipped
      - ./twofactor_email:/var/www/html/custom_apps/twofactor_email:ro
  mailpit:
    image: axllent/mailpit
    ports: ["8025:8025"]                # the challenge codes land here
volumes:
  nc-data:
```

Unpack a built package next to it and start the stack:

```
tar xzf build/artifacts/twofactor_email.tar.gz -C .
docker compose up -d
```

Then point Nextcloud at the mail catcher, give the admin an address (the provider
cannot be enabled without one) and enable the app:

```
occ() { docker compose exec -T -u www-data nextcloud php occ "$@"; }
occ config:system:set mail_smtpmode --value=smtp
occ config:system:set mail_smtphost --value=mailpit
occ config:system:set mail_smtpport --value=1025
occ config:system:set mail_from_address --value=nextcloud
occ config:system:set mail_domain --value=example.org
occ user:setting admin settings email admin@example.org
occ app:enable twofactor_email
```

Nextcloud is at `http://localhost:8080` (admin/admin), the mailbox at
`http://localhost:8025`. `docker compose down -v` removes everything again.

To reach the login challenge, switch the provider on for the user. The app keeps this
state in Nextcloud's own two-factor registry, so `occ` can do it without a browser:

```
occ twofactorauth:enable admin email
occ twofactorauth:state admin        # email must be listed under "Enabled providers"
```

The next login then asks for the code, which you read in the mail catcher.

Three things that look like bugs but are not:

- **The admin password cannot be changed to a weak one later.** `password_policy` is
  active in the image and asks for ten characters and a password that is not on the
  breach list. It does not apply while the instance is being installed, which is why
  `admin/admin` works, but a later `occ user:resetpassword` to `admin` fails. Pick a
  strong password, or throw the instance away and set it up again.
- **A `curl` login needs an `Origin` header.** Nextcloud rejects a POST to `/login`
  without a trusted `Origin` before it ever checks the password, and answers with a
  redirect to `?direct=1&user=…` plus a misleading `Logging out` line in the log. It
  looks exactly like a wrong password.
- **Send the request token as a header, not as a form field.** The token is base64, so
  it often contains a `+`. `curl -d` sends the body verbatim and PHP turns that `+`
  into a space while decoding, which breaks the token — but only for the tokens that
  happen to contain one, so the same command works about one time in three. As a
  header it needs no encoding:

  ```
  curl -b jar -c jar -H 'Origin: http://localhost:8080' -H "requesttoken: $TOKEN" \
    -d user=admin -d password=admin http://localhost:8080/login
  ```

If the Docker daemon refuses to start with `error initializing graphdriver: driver
not supported`, the kernel was updated without a reboot since — the modules of the
running kernel are gone, so overlayfs cannot be loaded. Rebooting fixes it.

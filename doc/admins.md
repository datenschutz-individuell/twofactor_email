# For administrators

This page covers installing, configuring, and running the provider.

## Installing & enabling

- Install **Two-Factor Email** from the [Nextcloud app store](https://apps.nextcloud.com/apps/twofactor_email). The server needs a working mail setup: the second factor travels that path, so use TLS to the mail server and treat it as part of your security perimeter.
- Users can enable it themselves in their security settings, or you can enable/disable it per user via `occ` — see [From the command line](#from-the-command-line).
- You can also **enforce 2FA** server-wide or per group (a Nextcloud feature) — see [Enforcing two-factor authentication](#enforcing-two-factor-authentication).
- **Keep it updated.** Only the newest release of a line carries the fixes, so install app updates as they appear — in *Administration settings › Apps*, or with `occ app:update twofactor_email`. Which line belongs to your server is in the [README](../README.md#supported-versions).

## From the command line

Run `occ` in the Nextcloud base directory; many systems need the interpreter in front: `php occ <command>`.

The app brings three commands of its own:

- `twofactor_email:settings` — show/change the app settings.
- `twofactor_email:delete-codes` — delete the stored code of one user or all.
- `twofactor_email:cleanup` — delete expired codes (also run daily by a background job).

Everything else is done with Nextcloud's own commands, and there are **two ids that are easy to mix up**: the app is `twofactor_email`, the provider inside it is `email`.

```shell
occ app:list --enabled | grep twofactor  # is the app installed and on?
occ app:enable twofactor_email           # make the provider available on the server
occ app:disable twofactor_email          # remove it again — see the note below
occ twofactorauth:state <uid>            # which providers are on for this account
occ twofactorauth:enable <uid> email     # switch email 2FA on for that account
occ twofactorauth:disable <uid> email    # switch it off again
```

The last two work because this provider allows changes by an admin. The admin manual describes the commands in section [Two-factor authentication](https://docs.nextcloud.com/server/stable/admin_manual/occ_users.html#two-factor-authentication).

### Scripting

To switch the provider on for many accounts, use a loop. This one prints every account for which Nextcloud reports no enabled provider:

```shell
# expects the jq tool to be installed on your system

occ user:list --limit 0 --output=json | jq -r 'keys[]' | while IFS= read -r uid; do
    occ twofactorauth:state "$uid" </dev/null | grep -q '^Two-factor authentication is enabled' || echo "$uid"
done
```

Notes on that script:

- **Its list is not complete.** `occ twofactorauth:state` counts backup codes (`backup_codes` in its output) as an enabled provider; a login does not, because Nextcloud never accepts them as the only second factor. An account holding nothing else is therefore missing from the list, and where 2FA is enforced its user still meets the setup step.
- **A user id may contain spaces**, so the loop reads the ids line by line. Splitting them on whitespace would turn one account into two ids that do not exist.
- **`occ user:list` stops at 500 accounts** unless you pass `--limit 0`. The loop calls `occ` once per account, so on a large instance it runs for minutes.

Before you switch the provider on for the accounts it names:

- **Take only accounts that have an address.** Nothing checks this when you switch it on, and an account without one cannot complete the code step. `occ user:info <uid>` prints the address, but only the system one — see [Email addresses](#email-addresses) for the second address an account can hold.
- **Every user hears about it.** A change made by an admin reaches them as a notification, and every change lands in their activity list, whoever made it.

### Removing the app

**Switch the provider off for every account before you remove the app.** Nextcloud keeps the association, and once the app is gone the per-account command cannot remove it any more — those users then see *Could not load at least one of your enabled two-factor auth methods* at every login. If the app is already gone, use `occ twofactorauth:cleanup email` instead: it drops the association for everyone at once, and that cannot be undone.

## Enforcing two-factor authentication

Enforcement belongs to Nextcloud, not to this app: *Administration settings › Security*, or

```shell
occ twofactorauth:enforce                                   # show the current state
occ twofactorauth:enforce --on
occ twofactorauth:enforce --on --exclude=bots
occ twofactorauth:enforce --on --group=staff
occ twofactorauth:enforce --off
```

Both options can be repeated for several groups. Naming groups with `--group` makes `--exclude` pointless: once a list of enforced groups exists, the exclusions are no longer looked at, and a user in both lists has to use 2FA. The [admin manual](https://docs.nextcloud.com/server/stable/admin_manual/configuration_user/two_factor-auth.html#enforcing-two-factor-authentication) explains the group logic.

Nextcloud cannot enforce one **specific** method. But **if this app is the only provider that offers setup at login, enforcing 2FA does enforce email 2FA**: a user without a second factor meets a setup step at the next login, and this provider supports it — one code, no device to enroll, nothing to install. Backup codes do not count here: every server has them, and they offer no setup step.

The condition is an address on every account. A user whose only factor is email and whose address does not work is locked out. Where that is a risk, keep [another provider](https://docs.nextcloud.com/server/stable/user_manual/en/user_2fa.html) installed — enforcement is then no longer email-specific.

![The setup step during login: 2FA is enforced and the user picks one of the installed providers — here Email, TOTP and Security key](img/atlogin-select.webp)

## Email addresses

The code goes to the address Nextcloud holds for the account, so where that address comes from is part of your login security:

- **Fill it from your directory.** With the LDAP backend, *Email Field* maps an attribute (usually `mail`) to the Nextcloud address, and the value is refreshed from the directory on every login. See [Special attributes](https://docs.nextcloud.com/server/stable/admin_manual/configuration_user/user_auth_ldap.html#special-attributes).
- **Keep users from changing it.** `allow_user_to_change_email` in `config.php` stops users editing the *system* address; adding a further address and picking it as the primary one stays possible, so this does not decide where the code goes. It defaults to whatever [`allow_user_to_change_display_name`](https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/config_sample_php_parameters.html#allow-user-to-change-display-name) is set to.
- **Set it from a script.** `occ user:setting <uid> settings email <address>`, or the [provisioning API](https://docs.nextcloud.com/server/stable/admin_manual/configuration_user/user_provisioning_api.html).

An account can hold a **second** address: a user may add further addresses to their profile, verify one and pick it as the primary address — and the primary one is where the code goes. Correcting the system address is then not enough. `occ user:setting <uid> settings` shows both.

**An address that disappears switches the factor off — but only when that is safe.** Every path that clears the *system* address tells the app: personal settings, the users page, the provisioning API, `occ user:setting`. (A directory sync never clears it: the LDAP backend ignores an empty or missing attribute rather than writing it through.) If the account still has another second factor, the app switches email 2FA off. If email was the only one, it stays on and every login fails until the address is back or you run `occ twofactorauth:disable <uid> email`.

One case gets past this: deleting the *additional* address a user had picked as their primary one fires no event at all, so the app learns nothing — delivery falls back to the system address, or stops when the account has none.

Backup codes do not count as the other factor here. Nextcloud does not accept them as the only one, so an account that holds nothing else keeps email 2FA enabled, and its user logs in with a backup code.

## Settings

Code length, code validity, the resend cooldown and the subject and body of the challenge email are configurable, in the admin UI **and** with `occ`. Both go through one validator — fixed ranges for the numbers, maximum lengths for the texts, no CR/LF in the subject — so neither can store an out-of-range or malformed value. The generic `occ config:app:set` bypasses it; see the read-side bounds below.

![The admin settings page: code length, validity and resend cooldown, and the subject and body of the challenge email](img/admin-settings.webp)

**A placeholder must not sit inside a web address.** `https://example.com/{code}` is rejected in the subject and in the body: the value would become part of the link, and link scanners fetch such addresses on their own — handing the one-time code to whoever owns that address. A text already stored is kept, and the finished mail is checked once more before it goes out: if the code ended up in a web address, the default text is sent instead, so no code leaves in a link. Until such a text is fixed the admin page saves nothing, because it saves all fields together — change another setting with `occ twofactor_email:settings`, or fix the text there.

**The three numeric settings are bounded when they are read, too.** `occ config:app:set twofactor_email …` writes past this app's validation, so a value outside the range is corrected to the nearest valid one at use time — the app never generates a code shorter than the minimum. `occ twofactor_email:settings` shows the value in effect, so a corrected one looks like a plain setting there. The correction goes to the log: once per request while the value is in use, and once more from the repair step after an update. That step is a background job, so look in the log, not in the output of `occ upgrade`.

Abuse of the "resend code" action is limited both by the app's own resend cooldown and by Nextcloud's **per-user rate limit and brute-force protection**.

## When no code arrives

The challenge page names a missing address, or a mail that could not be sent, and both go to the log under `twofactor_email`. Why the mail server refused the address is not in there — Nextcloud logs that itself, under `core` for a transport error and only at debug level for an address it cannot parse.

If the page says a code was sent and none arrives, the mail left Nextcloud — look at your mail server and at the spam folder. Then at the account: `occ user:setting <uid> settings` lists both addresses it can hold, and the code goes to the primary one if the user picked one, to the system address otherwise — see [Email addresses](#email-addresses). Reading only one of the two points at the wrong mailbox.

## Frequently asked questions

**Can I enforce email 2FA specifically?** Not directly: Nextcloud enforces *a* second factor, never a particular one. Installing this provider and no other has the same effect — see [Enforcing two-factor authentication](#enforcing-two-factor-authentication).

**Can I switch the provider on for everyone, or for every new account?** For everyone, loop over `occ user:list` and call `occ twofactorauth:enable` — see [Scripting](#scripting). For new accounts Nextcloud has no setting, and this app deliberately does not switch itself on: the address is usually unverified then, and the invitation mail travels the same path as the codes, so it would add no security.

**Can a user receive their codes at some other address than their Nextcloud one?** No, by decision. A second address is one more value to validate, store and keep in sync, and whoever reads a mailbox can also request a password reset there. If email is not a safe enough channel for an account, use another provider for it.

**A user cannot reach their mailbox any more. How do I let them in?** Put the codes where the user can read them: `occ user:setting <uid> settings email <address>`. `occ twofactorauth:disable <uid> email` looks shorter and is wrong where 2FA is enforced — the next login sends the user through the setup step, usually into this provider again, so they are asked for a code at the same unreachable mailbox. It also removes the *Use backup code* link, which the login screen shows only while a second factor is enabled: if the account has backup codes, leave the factor on and let the user log in with one. If the factor really has to go, take the account out of enforcement first. The [Two-Factor Admin Support](https://apps.nextcloud.com/apps/twofactor_admin) app is another way to help such a user.

**Do users have to confirm a code at every login?** Yes, once per login; the session stays valid afterwards. One exception: if the user opts to *Stay logged in*, Nextcloud keeps a cookie — valid for 15 days unless `remember_login_cookie_lifetime` says otherwise — and a session restored from that cookie asks for no second factor. Logging out drops it.

**Our desktop and mobile clients stopped working.** That happens with any second factor: those apps cannot show the web login. Each needs an app password, created under *Personal settings › Security › Devices & sessions*.

**After an update the mail lost its logo or its line breaks.** An older version saved the default text into the settings like a text of your own, so later improvements to the default never reached those instances. An update removes it again where it is still the unchanged 3.1 text — as a background job, so not in the same minute; an edited one is yours to clear — in the admin settings, or with `occ twofactor_email:settings email_template ""`, and the same for `email_subject`.

**Can I put `{code}` in the subject?** You may, but you probably should not. Mail clients show subjects in system notifications, which are readable on a locked screen.

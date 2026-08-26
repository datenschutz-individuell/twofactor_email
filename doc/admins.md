# For administrators

This page covers installing, configuring, and running the provider.

## Installing & enabling

- Install **Two-Factor Email** from the [Nextcloud App Store](https://apps.nextcloud.com/apps/twofactor_email). The server needs a working, configured mail server.
- Users can enable it themselves in their security settings, or you can enable/disable it per user via `occ` (see below).
- You can also **enforce 2FA** server-wide or per group (a Nextcloud feature). If you do, see the deployment note about email addresses below.

## Settings

Code length, code validity, the resend cooldown, and the challenge email subject/template are configurable — from the admin UI **and** via `occ`. All values go through one validator with fixed bounds (length, validity, cooldown ranges; subject/template max length; CR/LF rejected in the subject), so the web UI and `occ` cannot be used to set out-of-range or malformed values.

## `occ` commands

- `twofactor_email:settings` — show/change the app settings.
- `twofactor_email:delete-codes` — delete the stored code of one user or all.
- `twofactor_email:cleanup` — delete expired codes (also run daily by a background job).

## When no code arrives

The challenge page says whether the app got that far: it names a missing address, or a mail that could not be sent. Both are written to the log under `twofactor_email`, and the entry says which of the two it was. Why the mail server refused the address is not in it — Nextcloud logs that itself, under `core` for a transport error and only at debug level for an address it cannot parse.

If the page instead says a code was sent and none arrives, the mail left Nextcloud — look at your mail server and at the spam folder. Worth checking on the account itself: `occ user:setting <uid> settings` lists both addresses an account can hold. The code goes to `primary_email` when the user has picked one in their profile, and to `email` otherwise, so reading only one of the two can point at the wrong mailbox.

## Deployment notes

- The app **requires working, trustworthy email**. The second factor travels over your mail path — use TLS to your mail server and treat the mailserver as part of your security perimeter.
- When you **enforce 2FA**, each user needs at least one factor they can complete. Email is only critical for a user with no [other 2FA provider](https://docs.nextcloud.com/server/latest/user_manual/en/user_2fa.html) available. For that user, keep the primary email address valid — otherwise they are locked out.
- Abuse of the "resend code" action is limited both by an app-level **cooldown** and by Nextcloud's **per-user rate limit and brute-force protection**.

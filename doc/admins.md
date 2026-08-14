# For administrators

This page covers installing, configuring, and running the provider.

## Installing & enabling

- Install **Two-Factor Email** from the [Nextcloud App Store](https://apps.nextcloud.com/apps/twofactor_email). The server needs a working, configured mail server.
- Users can enable it themselves in their security settings, or you can enable/disable it per user via `occ` (see below).
- You can also **enforce 2FA** server-wide or per group (a Nextcloud feature). If you do, see the deployment note about email addresses below.

## Settings

Code length, code validity, the resend cooldown, and the challenge email subject/template are configurable — from the admin UI **and** via `occ`. All values go through one validator with fixed bounds (length, validity, cooldown ranges; subject/template max length; CR/LF rejected in the subject), so the web UI and `occ` cannot be used to set out-of-range or malformed values.

**A placeholder must not sit inside a web address.** `https://example.com/{code}` in the subject or the body is rejected, because the value would become part of the link — and link scanners fetch such addresses on their own, which would hand the one-time code to whoever owns that address. Should such a text already be stored, it is kept and reported: before every mail the finished text is checked once more, and if the code ended up in a web address the mail is sent with the default text instead. So no code leaves in a link. The admin settings page then refuses to save anything until the text is fixed, because the form submits every field at once — use `occ twofactor_email:settings` to change another setting first, or fix the text there.

**The three numeric settings are also bounded when they are read**, not only when they are written. `occ config:app:set twofactor_email …` writes past this app's validation, so a value outside the allowed range is corrected to the nearest valid one at use time; the app never generates a code shorter than the minimum. `occ twofactor_email:settings` shows the value that is in effect, so a corrected one looks like a plain setting there; the correction itself is reported in the Nextcloud log and, once, in the output of `occ upgrade`.

## `occ` commands

- `twofactor_email:settings` — show/change the app settings.
- `twofactor_email:delete-codes` — delete the stored code of one user or all.
- `twofactor_email:cleanup` — delete expired codes (also run daily by a background job).

## Deployment notes

- The app **requires working, trustworthy email**. The second factor travels over your mail path — use TLS to your mail server and treat the mailserver as part of your security perimeter.
- When you **enforce 2FA**, each user needs at least one factor they can complete. Email is only critical for a user with no [other 2FA provider](https://docs.nextcloud.com/server/latest/user_manual/en/user_2fa.html) available. For that user, keep the primary email address valid — otherwise they are locked out.
- Abuse of the "resend code" action is limited both by an app-level **cooldown** and by Nextcloud's **per-user rate limit and brute-force protection**.

# Threat model & limitations

This app raises the bar for account takeover but, like every second factor, it is not absolute.

## Who the promises are made against

Everything this app checks, it checks against input that arrives **through an interface**:

- a **user in the browser**, including one who already passed the password;
- an **admin in the web UI** and the app's own `occ` commands (`twofactor_email:settings`), which share one validator — so neither path can store a setting that weakens the factor or carries the code into a link;
- a value that reached the app config on **another** path — the generic `occ config:app:set`, a database restored from a different release — is corrected or ignored when it is *read*, because a validator never saw it written. That is robustness on top, and it covers only the settings this app owns.

**Out of scope: write access to the instance itself.** Whoever can change files (this app's code, a theme's translation files, `config.php`) or write to the database directly is already inside the trust boundary: the same access removes any check made here, so no check can defend against it. A full server compromise defeats any second factor, this one included. Read access is a different question and is treated above — codes are stored hashed and flagged sensitive, which limits what a config or database *read* yields, though not what root can do.

## Limitations of the factor itself

- **The email channel is the trust anchor.** The factor is "something you can *receive*". Its strength equals the security of the user's mailbox and the mail transport. A compromised mailbox, or mail read in transit, defeats it. This is the fundamental trade-off of email-based 2FA versus "something you *have*" factors (TOTP apps, hardware keys).
- **No phishing resistance.** A real-time phishing proxy can prompt for the code and replay it, exactly as with TOTP. Only origin-bound factors ([FIDO2/WebAuthn](https://docs.nextcloud.com/server/stable/user_manual/en/user_2fa.html)) resist this.
- **Limited code entropy, compensated by policy.** A six-digit code has ~10⁶ values; brute force is contained by the short validity, the single valid code, the resend rate limit, and Nextcloud's login brute-force protection — not by entropy alone.
- **Reloading the challenge page is capped, not free.** Nextcloud rate-limits submitting a code and this app's resend endpoint, but not a reload of the challenge page. A code that was sent is stored, and a stored code stops the next reload from sending another. A send that failed stores nothing, so the app registers every connection it opens to the mail server with Nextcloud's rate limiter, ten in five minutes per account, and refuses the rest. The window is that long on purpose: a mail server that hangs holds each attempt for the SMTP timeout, so a shorter one would expire before the cap is reached. Without that, an unreachable mail server would cost one outgoing connection per reload, and a failed page offers nothing else to do. The cap sits behind the address check, so an account with no address is still reported as exactly that, however often the page is loaded. Only someone who already passed the first factor can reach the page at all.
- **Residual timing side channel (accepted).** `verifyChallenge()` returns early when no code is stored, which is measurably faster than a hash comparison, so response time reveals whether an unexpired code exists — but only to someone who already passed the first factor, and the comparison itself stays constant-time. A decoy hash comparison on the miss path was deliberately left out. That it takes the first factor to get there at all is the first invariant below.
- **What the log carries.** Log messages and context values hold the user ID only — never the code, never the email address, and two unit tests keep it that way. An attached exception can still quote both, because PHP records function arguments in a stack trace unless `zend.exception_ignore_args` is on (off is the compiled-in default, on is what `php.ini-production` sets), Nextcloud serializes those arguments, and its redaction list covers `solveChallenge` and `verifyChallenge` but not this app's sender. For the code that is harmless by construction: a code is stored only after its mail was sent, so every exception able to quote one comes from a failed send, and the quoted value was never a code anything could accept. For the address it is accepted: the log is read by an administrator, who sees the address in the user's profile in any case, and rewriting a foreign exception message would cost the diagnosis without being a promise the app can keep.
- **The address belongs to Nextcloud, not to this app.** Delivery uses `IUser::getEMailAddress()` at the moment a code is sent, and the app keeps no address to deliver to. That is deliberate: an administrator can switch the factor on for someone, and there is then no second place that could send a code somewhere the account does not name. What the app does keep beside a stored code is a fingerprint — a hash — of the address that code went to. It is a comparison value and never a destination: it can only make the app drop a code, never send one. So the promise is exactly this much — a code is only ever mailed to an address that Nextcloud held as the user's own when the mail was sent. Where the *next* code goes is not the app's to promise: everyone who may edit that address, the user and an administrator and a group subadmin and a directory sync alike, can move it. That is Nextcloud's authorization model, and an app cannot override it from the outside.
- **A changed notification address goes unannounced.** `IUser::setPrimaryEMailAddress()` writes `settings/primary_email` and fires no event, while `getEMailAddress()` prefers exactly that value — so choosing another notification address, or setting `notify_email` through the provisioning API, moves delivery without Nextcloud telling anyone. The app cannot act at the moment it happens. What it can do is stop honouring a code that no longer belongs to the address delivery would use: `CodeStorage` checks every stored code against a fingerprint of the address it was sent to and drops it on the next read, instead of leaving it acceptable for its remaining minutes. No listener is registered for the address either: `UserChangedEvent` carries the *system* address, so it fires for changes that leave delivery where it was and stays silent for the ones that move it — acting on it would invalidate codes that are still correct and still miss the cases that matter.

## How email 2FA compares

| Factor                      | Phishing-resistant     | Depends on                      | Convenience                  |
|-----------------------------|------------------------|---------------------------------|------------------------------|
| **Email code (this app)**   | No                     | User's mailbox + mail path      | High (no extra device/setup) |
| TOTP app                    | No                     | A provisioned authenticator app | Medium                       |
| Hardware / FIDO2 (WebAuthn) | **Yes** (origin-bound) | A physical key                  | Medium                       |

Email 2FA is a low-friction, broadly available second factor — a clear improvement over password-only. Nextcloud lets a user enable several providers at once, so pair it with a stronger, phishing-resistant method where possible. Users able to use one then get the better protection, with email as a fallback.

## Verified invariants

These are statements about **Nextcloud's own behaviour** that the app depends on. None
of them can be read off the public `OCP` interfaces: the code that enforces them lives
in the server's private classes. So each was checked against the server's source for
every supported version, and each says where.

**Checked 2026-08-26**, against pinned commits of
[nextcloud/server](https://github.com/nextcloud/server) rather than a moving branch, so
every line number below can still be found:

| Server line | Commit | Version |
|-------------|--------|---------|
| `stable33`  | [`a91897f4`](https://github.com/nextcloud/server/commit/a91897f461c4bd1a1c9eca44147fb3c7366dfa0c) | 33.0.8.2 |
| `stable34`  | [`a599620e`](https://github.com/nextcloud/server/commit/a599620e9b75dc3c919b39dabd82a4f98b543b74) | 34.0.3.2 |

Adding a server version to the supported range means checking these against the commit
that version ships, and pinning it here, rather than assuming they still hold.

**1. Every route of this app requires a completed first factor.** None of them carries
`#[PublicPage]`, and `SecurityMiddleware` answers a request to a non-public route from
a session without a user with `NotLoggedInException`
(`lib/private/AppFramework/Middleware/Security/SecurityMiddleware.php`, line 135 on
stable33, line 139 on stable34). The two-factor exemption below is read by a different
middleware and does not touch this: it lifts the *second* factor, never the first.

**2. `NoTwoFactorRequired` is read differently per version.** Nextcloud 33 reads the
exemption from the docblock annotation only (`core/Middleware/TwoFactorMiddleware.php`
line 46), Nextcloud 34 from the annotation *or* the attribute (line 50, via
`hasAnnotationOrAttribute`). That is why `ChallengeController::resend()` carries both:
the attribute is `@since 34`, and removing either one breaks a supported server.

**3. Extending `ALoginSetupController` lifts the second-factor gate.** That class is
empty; its only effect is that `TwoFactorMiddleware` recognises it and skips the gate
while a user needs a second factor and has no primary provider to complete it with
(`core/Middleware/TwoFactorMiddleware.php`, line 67 on stable33, line 72 on stable34).
That state is the enrolment case, and `StateController` needs it — the setup step shown
during login switches the provider on through it. `AdminSettingsController` therefore
does **not** extend it: with the base class, changing this app's settings would need
the password alone while an admin is still being set up.

**4. A delegated group passes `#[AuthorizedAdminSetting]`.** Being a member of the
`admin` group is not the only way in. `SecurityMiddleware` also matches the settings
classes named in the attribute against the classes delegated to the user's groups
(line 147 on stable33, line 151 on stable34). So an admin who delegates this app's
settings with `occ admin-delegation:add` grants exactly the routes that attribute
guards — which is the point of the feature, and worth knowing before delegating.

**5. Nextcloud lower-cases and trims an email address before storing it.** The
fingerprint a stored code is bound to does the same, so an address differing only in
case or surrounding space does not read as a changed one. Both setters normalise
(`lib/private/User/User.php`, `setSystemEMailAddress()` line 161 and
`setPrimaryEMailAddress()` line 184 on stable33, lines 149 and 173 on stable34), and
`getSystemEMailAddress()` normalises again on the way out (line 529 on stable33, 533 on
stable34). Should the two ever diverge, a code would die one send early — harmless, and
named here because it would otherwise be hard to trace.

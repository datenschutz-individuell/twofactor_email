# Threat model & limitations

This app raises the bar for account takeover but, like every second factor, it is not absolute.

- **The email channel is the trust anchor.** The factor is "something you can *receive*". Its strength equals the security of the user's mailbox and the mail transport. A compromised mailbox, or mail read in transit, defeats it. This is the fundamental trade-off of email-based 2FA versus "something you *have*" factors (TOTP apps, hardware keys).
- **No phishing resistance.** A real-time phishing proxy can prompt for the code and replay it, exactly as with TOTP. Only origin-bound factors ([FIDO2/WebAuthn](https://docs.nextcloud.com/server/latest/user_manual/en/user_2fa.html)) resist this.
- **Limited code entropy, compensated by policy.** A six-digit code has ~10⁶ values; brute force is contained by the short validity, the single valid code, the resend rate limit, and Nextcloud's login brute-force protection — not by entropy alone.
- **Residual timing side channel (accepted).** `verifyChallenge()` returns early when no code is stored, which is measurably faster than a hash comparison, so response time reveals whether an unexpired code exists — but only to someone who already passed the first factor, and the comparison itself stays constant-time. A decoy hash comparison on the miss path was deliberately left out.
- **A full server compromise defeats any 2FA**, this one included; storing codes hashed limits exposure from lower-privilege config/DB reads, not from root.

## How email 2FA compares

| Factor                      | Phishing-resistant     | Depends on                      | Convenience                  |
|-----------------------------|------------------------|---------------------------------|------------------------------|
| **Email code (this app)**   | No                     | User's mailbox + mail path      | High (no extra device/setup) |
| TOTP app                    | No                     | A provisioned authenticator app | Medium                       |
| Hardware / FIDO2 (WebAuthn) | **Yes** (origin-bound) | A physical key                  | Medium                       |

Email 2FA is a low-friction, broadly available second factor — a clear improvement over password-only. Nextcloud lets a user enable several providers at once, so pair it with a stronger, phishing-resistant method where possible. Users able to use one then get the better protection, with email as a fallback.

# Architecture

The app plugs into Nextcloud's [two-factor provider framework](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/two-factor-provider.html). It is built against Nextcloud's public interfaces ([`OCP`](https://github.com/nextcloud-deps/ocp)) and its own small service interfaces, so the pieces are individually testable and replaceable. This document shows how they fit together; for the security mechanisms see [developers.md](developers.md), and the [threat model](threat-model.md) for its limits.

## Login challenge flow

The email challenge is the **second** stage of login. The first stage is a password — or a passwordless credential such as a passkey or security key. Once it succeeds, Nextcloud asks the enabled provider for a challenge, and this app generates a code, emails it, and verifies what the user enters:

![Login challenge flow: after the first login stage, the provider reuses a still-valid code or issues a new one — generate with a CSPRNG, email it, then store its hash only after the email is sent. The code the user enters is checked with a constant-time hash comparison and deleted on success.](img/login-challenge-flow.svg)

A new code is issued only when no unexpired one is stored, and the hash is written only after the email was sent. So reloading the login page does not spam the mailbox, and a failed send leaves the previous code valid.

## Components

The building blocks and the interface each one implements:

| Concern                       | Interface                     | Implementation                                                            |
|-------------------------------|-------------------------------|---------------------------------------------------------------------------|
| 2FA provider entry point      | `IProvider` (OCP)             | `Provider\TwoFactorEMail`                                                 |
| Login-setup at enforced login | `ILoginSetupProvider` (OCP)   | `Provider\LoginSetup`                                                     |
| Challenge orchestration       | `Service\ILoginChallenge`     | `Service\LoginChallenge`                                                  |
| Code generation               | `Service\ICodeGenerator`      | `Service\NumericalCodeGenerator`                                          |
| Code storage                  | `Service\ICodeStorage`        | `Service\CodeStorage`                                                     |
| Email delivery                | `Service\IEMailSender`        | `Service\EMailSender` (+ `Mail\TemplateRenderer`, `Mail\LinkScanner`)     |
| Delivery address              | — (concrete)                  | `Service\EMailAddressSource`                                              |
| Address masking               | `Service\IEMailAddressMasker` | `Service\EMailAddressMasker`                                              |
| Enable/disable state          | `Service\IStateManager`       | `Service\StateManager`                                                    |
| Settings                      | `Service\IAppSettings`        | `Service\AppSettings` (+ `Service\SettingsValidator`, `Service\WarnOnce`) |

Around these services sit the HTTP controllers, event listeners (activity/notifications/registry updates on state change and on email removal), the daily cleanup background job, `occ` commands, and the admin/personal settings sections.

## Data footprint

The app introduces no database table. Per user, it stores only the hashed current code and its timestamp (transient, expired-out and deleted on use). App-wide settings live in the app config. It does not store any personal data beyond the email address Nextcloud already holds.

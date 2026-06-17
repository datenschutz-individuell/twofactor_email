<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

interface IAppSettings {
	/**
	 * How many digits a stored 2FA code shall consist of.
	 *
	 * @return int number of digits
	 */
	public function getCodeLength(): int;

	/**
	 * How long a stored 2FA code shall be valid.
	 *
	 * @return int minutes of validity
	 */
	public function getCodeValidMinutes(): int;

	/**
	 * Minimum number of minutes that must pass before the user may request a
	 * new code while the current one is still valid (resend cooldown).
	 *
	 * @return int minutes
	 */
	public function getResendMinMinutes(): int;

	/**
	 * The resend cooldown in seconds (derived from the minutes setting) — the
	 * single place that converts the admin unit to what the cooldown logic and
	 * the challenge page work in.
	 *
	 * @return int seconds
	 */
	public function getResendCooldownSeconds(): int;

	/**
	 * Subject of the 2FA challenge email, as stored by the admin.
	 * An empty string means: no custom subject — use getDefaultEMailSubject().
	 *
	 * @return string email subject template
	 */
	public function getEMailSubject(): string;

	/**
	 * Plain-text body template of the 2FA challenge email, as stored by the admin.
	 * An empty string means: no custom body — use getDefaultEMailBody().
	 *
	 * @return string email body template
	 */
	public function getEMailTemplate(): string;

	/**
	 * Localized default subject, used when no custom subject is stored and
	 * shown as a hint in the admin form.
	 * Supports the placeholders {code}, {user}, {cloud} and {validity}.
	 */
	public function getDefaultEMailSubject(): string;

	/**
	 * Localized default body, used when no custom body is stored and shown as
	 * a hint in the admin form.
	 * Supports the placeholders {logo}, {code}, {user}, {cloud} and {validity}.
	 */
	public function getDefaultEMailBody(): string;

	public function setCodeLength(int $codeLength): void;

	public function setCodeValidMinutes(int $codeValidMinutes): void;

	public function setResendMinMinutes(int $resendMinutes): void;

	public function setEMailSubject(string $subject): void;

	public function setEMailTemplate(string $body): void;

	/**
	 * Remove all stored settings so the defaults take effect again.
	 */
	public function resetToDefaults(): void;
}

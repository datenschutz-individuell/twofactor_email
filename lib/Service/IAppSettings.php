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
	 * Subject of the 2FA challenge email.
	 * Supports the placeholders {code}, {user}, {cloud} and {validity}.
	 * An empty string means: use the localized default subject.
	 *
	 * @return string email subject template
	 */
	public function getEMailSubject(): string;

	/**
	 * Plain-text email body template used when sending the 2FA challenge email.
	 * Supports the placeholders {code}, {user}, {cloud} and {validity}.
	 * An empty string means: use the localized default body text.
	 *
	 * @return string email body template
	 */
	public function getEMailTemplate(): string;

	/**
	 * Footer text of the 2FA challenge email.
	 * Supports the placeholders {code}, {user}, {cloud} and {validity}.
	 * An empty string means: use the standard footer of this Nextcloud
	 * instance (theming slogan).
	 *
	 * @return string email footer template
	 */
	public function getEMailFooter(): string;
}

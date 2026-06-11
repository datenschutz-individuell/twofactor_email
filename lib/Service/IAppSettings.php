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
	 * Plain-text email template used when sending the 2FA challenge email.
	 * Supports the placeholders {code}, {user}, {cloud}.
	 *
	 * @return string email template
	 */
	public function getEMailTemplate(): string;
}

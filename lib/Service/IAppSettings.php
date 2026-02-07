<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
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
	 * How long shall a stored 2FA code be valid.
	 *
	 * @return int seconds of validity
	 */
	public function getCodeValidSeconds(): int;
}

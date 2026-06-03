<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

final class AppSettingsDefaults {

	// Config keys used to store settings in the app config
	public const CONFIG_KEY_CODE_LENGTH = 'code_length';
	public const CONFIG_KEY_CODE_VALID_MINUTES = 'code_valid_minutes';
	public const CONFIG_KEY_EMAIL_TEMPLATE = 'email_template';

	// Default values — used when no value has been stored in the app config
	public const CODE_LENGTH = 6;
	public const CODE_VALID_MINUTES = 10;

	// Placeholders available in the email template: {code}, {user}, {cloud}
	public const EMAIL_TEMPLATE
		= "Your two-factor authentication code is: {code}\n\n"
		. 'If you tried to login, please enter that code on {cloud}. '
		. 'If you did not, somebody else did and knows your email address '
		. 'or username – and your password!';
}

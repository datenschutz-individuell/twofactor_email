<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\IAppConfig;

final class AppSettings implements IAppSettings {

	// Placeholders available in the email template: {code}, {user}, {cloud}
	private const DEFAULT_EMAIL_TEMPLATE
		= "Your two-factor authentication code is: {code}\n\n"
		. 'If you tried to login, please enter that code on {cloud}. '
		. 'If you did not, somebody else did and knows your email address '
		. 'or username – and your password!';

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getCodeLength(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, 'code_length', 6);
	}

	public function getCodeValidMinutes(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, 'code_valid_minutes', 10);
	}

	public function getEMailTemplate(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'email_template', self::DEFAULT_EMAIL_TEMPLATE);
	}
}

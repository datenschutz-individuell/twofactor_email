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

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	public function getCodeLength(): int {
		return $this->appConfig->getValueInt(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_CODE_LENGTH,
			AppSettingsDefaults::CODE_LENGTH,
		);
	}

	public function getCodeValidMinutes(): int {
		return $this->appConfig->getValueInt(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_CODE_VALID_MINUTES,
			AppSettingsDefaults::CODE_VALID_MINUTES,
		);
	}

	public function getEMailSubject(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_EMAIL_SUBJECT,
			AppSettingsDefaults::EMAIL_SUBJECT,
		);
	}

	public function getEMailHeading(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_EMAIL_HEADING,
			AppSettingsDefaults::EMAIL_HEADING,
		);
	}

	public function getEMailTemplate(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_EMAIL_TEMPLATE,
			AppSettingsDefaults::EMAIL_TEMPLATE,
		);
	}

	public function getEMailFooter(): string {
		return $this->appConfig->getValueString(
			Application::APP_ID,
			AppSettingsDefaults::CONFIG_KEY_EMAIL_FOOTER,
			AppSettingsDefaults::EMAIL_FOOTER,
		);
	}
}

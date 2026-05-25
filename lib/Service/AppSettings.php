<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\IAppConfig;

final class AppSettings implements IAppSettings {

    public function __construct(
        private IAppConfig $appConfig,
    ) {
    }

    public function getCodeLength(): int {
		return 6;
	}
	public function getCodeValidMinutes(): int {
        return $this->appConfig->getValueInt(Application::APP_ID, 'code_valid_minutes', 10);
	}
	public function getSendRateLimitAttempts(): int {
		return 10;
	}
	public function getSendRateLimitPeriodSeconds(): int {
		return 60 * 10; // 10 minutes
	}
}

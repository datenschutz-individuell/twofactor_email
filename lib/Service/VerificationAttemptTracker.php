<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Config\IUserConfig;

/**
 * Tracks failed verification attempts using Nextcloud user preferences.
 */
final class VerificationAttemptTracker implements IVerificationAttemptTracker {
	private const KEY_FAILED_ATTEMPTS = 'code_failed_attempts';

	public function __construct(
		private IAppSettings $settings,
		private IUserConfig $config,
	) {
	}

	public function recordFailedAttempt(string $userId): bool {
		$currentAttempts = $this->config->getValueInt(
			$userId,
			Application::APP_ID,
			self::KEY_FAILED_ATTEMPTS,
			0
		);
		$newAttempts = $currentAttempts + 1;
		$this->config->setValueInt(
			$userId,
			Application::APP_ID,
			self::KEY_FAILED_ATTEMPTS,
			$newAttempts
		);

		return $newAttempts >= $this->settings->getMaxVerificationAttempts();
	}

	public function resetAttempts(string $userId): void {
		$this->config->deleteUserConfig(
			$userId,
			Application::APP_ID,
			self::KEY_FAILED_ATTEMPTS
		);
	}
}

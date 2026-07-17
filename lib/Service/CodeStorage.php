<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;

final class CodeStorage implements ICodeStorage {
	private const KEY_CODE = 'code';
	private const KEY_CREATED_AT = 'code_created_at';

	public function __construct(
		private readonly IAppSettings $settings,
		private readonly IUserConfig $config,
	) {
	}

	public function readCode(string $userId): ?string {
		$expiresBefore = time() - $this->settings->getCodeValidMinutes() * 60;
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		if ($createdAt < $expiresBefore) {
			$this->deleteCode($userId);
			return null;
		}

		$code = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE);
		if ($code === '') {
			$this->deleteCode($userId);
			return null;
		}
		return $code;
	}

	public function secondsSinceLastCode(string $userId): ?int {
		// Only a still-valid code counts: an expired one is treated as "none"
		// so the user may request a fresh code without waiting.
		if ($this->readCode($userId) === null) {
			return null;
		}
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		return max(0, time() - $createdAt);
	}

	public function deleteCode(string $userId): bool {
		$existed = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE) !== '';
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CODE);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CREATED_AT);
		return $existed;
	}

	public function writeCode(string $userId, string $code, ?int $createdAt = null): void {
		$createdAt ??= time();
		$this->config->setValueString($userId, Application::APP_ID, self::KEY_CODE, $code);
		$this->config->setValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT, $createdAt);
	}

	public function deleteAllCodes(): int {
		$count = count($this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT));
		$this->config->deleteKey(Application::APP_ID, self::KEY_CODE);
		$this->config->deleteKey(Application::APP_ID, self::KEY_CREATED_AT);
		return $count;
	}

	public function deleteExpired(): int {
		$expiresBefore = time() - $this->settings->getCodeValidMinutes() * 60;
		$creationTime = $this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT);

		$count = 0;
		foreach ($creationTime as $userId => $createdAt) {
			if ($createdAt < $expiresBefore) {
				$this->deleteCode($userId);
				$count++;
			}
		}
		return $count;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;

final readonly class CodeStorage implements ICodeStorage {
	private const KEY_CODE = 'code';
	private const KEY_CREATED_AT = 'code_created_at';

	public function __construct(
		private IAppSettings $settings,
		private IUserConfig $config,
	) {
	}

	#[\Override]
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

	#[\Override]
	public function secondsSinceLastCode(string $userId): ?int {
		// Only a still-valid code counts: an expired one is treated as "none"
		// so the user may request a fresh code without waiting.
		if ($this->readCode($userId) === null) {
			return null;
		}
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		return max(0, time() - $createdAt);
	}

	#[\Override]
	public function deleteCode(string $userId): bool {
		$existed = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE) !== '';
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CODE);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CREATED_AT);
		return $existed;
	}

	#[\Override]
	public function writeCode(string $userId, string $code, ?int $createdAt = null): void {
		$createdAt ??= time();
		// The stored value is a hash, but flag it sensitive so it is masked in
		// occ config:list and system/support reports.
		$this->config->setValueString($userId, Application::APP_ID, self::KEY_CODE, $code, flags: IUserConfig::FLAG_SENSITIVE);
		$this->config->setValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT, $createdAt);
	}

	#[\Override]
	public function deleteAllCodes(): int {
		$count = count($this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT));
		$this->config->deleteKey(Application::APP_ID, self::KEY_CODE);
		$this->config->deleteKey(Application::APP_ID, self::KEY_CREATED_AT);
		return $count;
	}

	#[\Override]
	public function deleteExpired(): int {
		$expiresBefore = time() - $this->settings->getCodeValidMinutes() * 60;
		$creationTime = $this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT);

		$count = 0;
		// A user id of digits only arrives as an int: PHP converts numeric array
		// keys, and Nextcloud allows such ids.
		foreach ($creationTime as $userId => $createdAt) {
			if ($createdAt < $expiresBefore) {
				$this->deleteCode((string)$userId);
				$count++;
			}
		}
		return $count;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\Config\IUserConfig;
use OCP\Config\ValueType;
use OCA\TwoFactorEMail\AppInfo\Application;

class PreferencesCodeStorage implements ICodeStorage
{
	private const KEY_CODE_HASH = 'code_hash';
	private const KEY_CREATED_AT = 'code_created_at';

	public function __construct(
		private IApplicationSettings $settings,
		private IUserConfig $config,
	) {
	}

	public function getCodeHash(string $userId): string
	{
		return $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE_HASH);
	}

	public function getCodeCreatedAt(string $userId): int
	{
		return $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
	}

	public function writeCode(string $userId, string $code, ?int $createdAt = null): void
	{
		$createdAt ??= time();
		
		// Hash the code using bcrypt for secure storage
		// Using PASSWORD_BCRYPT with default cost for balance of security and performance
		$hash = password_hash($code, PASSWORD_BCRYPT);
		
		$this->config->setValueString($userId, Application::APP_ID, self::KEY_CODE_HASH, $hash);
		$this->config->setValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT, $createdAt);
	}

	public function deleteCode(string $userId): void
	{
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CODE_HASH);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CREATED_AT);
	}

	public function deleteExpired(): void
	{
		$expiresBefore = time() - $this->settings->getCodeValidSeconds();
		$creationTime = $this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT);

		foreach ($creationTime as $userId => $createdAt) {
			if ($createdAt < $expiresBefore) {
				$this->deleteCode($userId);
			}
		}
	}
}

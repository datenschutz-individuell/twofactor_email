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
	private const KEY_SEND_ATTEMPTS = 'send_attempts';
	private const KEY_FIRST_SEND_AT = 'first_send_at';

	public function __construct(
		private IApplicationSettings $settings,
		private IUserConfig $config,
	) {
	}

	public function verifyCode(string $userId, string $submittedCode): bool
	{
		$submittedCode = trim($submittedCode);

		// Check expiration first
		$expiresBefore = time() - $this->settings->getCodeValidSeconds();
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		if ($createdAt < $expiresBefore) {
			$this->deleteCode($userId);
			return false;
		}

		// Get stored hash
		$storedHash = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE_HASH);
		if ($storedHash === '') {
			return false;
		}

		// Timing-safe verification using password_verify (bcrypt)
		// This prevents timing attacks as bcrypt comparison is constant-time
		return password_verify($submittedCode, $storedHash);
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
				$this->resetRateLimiting($userId);
			}
		}
	}

	public function canSendCode(string $userId): bool
	{
		$windowStart = time() - $this->settings->getResendWindowSeconds();
		$firstSendAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_FIRST_SEND_AT);
		
		// If outside the rate limit window, reset and allow
		if ($firstSendAt < $windowStart) {
			$this->resetRateLimiting($userId);
			return true;
		}

		// Check attempt count within window
		$attempts = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_SEND_ATTEMPTS);
		return $attempts < $this->settings->getMaxResendAttempts();
	}

	public function recordSendAttempt(string $userId): void
	{
		$now = time();
		$windowStart = $now - $this->settings->getResendWindowSeconds();
		$firstSendAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_FIRST_SEND_AT);

		// If outside window, start new window
		if ($firstSendAt < $windowStart) {
			$this->config->setValueInt($userId, Application::APP_ID, self::KEY_FIRST_SEND_AT, $now);
			$this->config->setValueInt($userId, Application::APP_ID, self::KEY_SEND_ATTEMPTS, 1);
		} else {
			// Increment attempts within window
			$attempts = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_SEND_ATTEMPTS);
			$this->config->setValueInt($userId, Application::APP_ID, self::KEY_SEND_ATTEMPTS, $attempts + 1);
		}
	}

	public function getSecondsUntilCanResend(string $userId): int
	{
		if ($this->canSendCode($userId)) {
			return 0;
		}

		$firstSendAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_FIRST_SEND_AT);
		$windowEnd = $firstSendAt + $this->settings->getResendWindowSeconds();
		$remaining = $windowEnd - time();

		return max(0, $remaining);
	}

	private function resetRateLimiting(string $userId): void
	{
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_SEND_ATTEMPTS);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_FIRST_SEND_AT);
	}
}

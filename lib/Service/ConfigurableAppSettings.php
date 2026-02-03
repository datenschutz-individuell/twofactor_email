<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\IConfig;

/**
 * App settings that can be configured by admins via the admin settings panel.
 * Values are stored in Nextcloud's appconfig table.
 */
final class ConfigurableAppSettings implements IAppSettings {
	// Setting keys
	private const KEY_CODE_LENGTH = 'code_length';
	private const KEY_CODE_VALID_SECONDS = 'code_valid_seconds';
	private const KEY_MAX_VERIFICATION_ATTEMPTS = 'max_verification_attempts';
	private const KEY_USE_ALPHANUMERIC = 'use_alphanumeric_codes';
	private const KEY_RATE_LIMIT_ATTEMPTS = 'rate_limit_attempts';
	private const KEY_RATE_LIMIT_PERIOD = 'rate_limit_period_seconds';
	private const KEY_SKIP_SEND_IF_EXISTS = 'skip_send_if_code_exists';
	private const KEY_INCLUDE_EMAIL_HEADER = 'include_email_header';
	private const KEY_ALLOWED_DOMAINS = 'allowed_domains';
	private const KEY_PREFER_LDAP_EMAIL = 'prefer_ldap_email';

	// Default values
	private const DEFAULT_CODE_LENGTH = 6;
	private const DEFAULT_CODE_VALID_SECONDS = 600; // 10 minutes
	private const DEFAULT_MAX_VERIFICATION_ATTEMPTS = 3;
	private const DEFAULT_USE_ALPHANUMERIC = false;
	private const DEFAULT_RATE_LIMIT_ATTEMPTS = 10;
	private const DEFAULT_RATE_LIMIT_PERIOD = 600; // 10 minutes
	private const DEFAULT_SKIP_SEND_IF_EXISTS = false;
	private const DEFAULT_INCLUDE_EMAIL_HEADER = true;
	private const DEFAULT_ALLOWED_DOMAINS = '';
	private const DEFAULT_PREFER_LDAP_EMAIL = false;

	public function __construct(
		private IConfig $config,
	) {
	}

	// ========== CODE SETTINGS ==========

	public function getCodeLength(): int {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_CODE_LENGTH,
			(string)self::DEFAULT_CODE_LENGTH
		);
		$length = (int)$value;
		// Enforce valid range (4-12)
		return max(4, min(12, $length));
	}

	public function getCodeValidSeconds(): int {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_CODE_VALID_SECONDS,
			(string)self::DEFAULT_CODE_VALID_SECONDS
		);
		$seconds = (int)$value;
		// Enforce valid range (60 seconds to 30 minutes)
		return max(60, min(1800, $seconds));
	}

	public function getMaxVerificationAttempts(): int {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_MAX_VERIFICATION_ATTEMPTS,
			(string)self::DEFAULT_MAX_VERIFICATION_ATTEMPTS
		);
		$attempts = (int)$value;
		// Enforce valid range (1-10)
		return max(1, min(10, $attempts));
	}

	// ========== CODE FORMAT ==========

	public function useAlphanumericCodes(): bool {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_USE_ALPHANUMERIC,
			self::DEFAULT_USE_ALPHANUMERIC ? '1' : '0'
		) === '1';
	}

	// ========== RATE LIMITING ==========

	public function getSendRateLimitAttempts(): int {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_RATE_LIMIT_ATTEMPTS,
			(string)self::DEFAULT_RATE_LIMIT_ATTEMPTS
		);
		$attempts = (int)$value;
		// Enforce valid range (1-50)
		return max(1, min(50, $attempts));
	}

	public function getSendRateLimitPeriodSeconds(): int {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_RATE_LIMIT_PERIOD,
			(string)self::DEFAULT_RATE_LIMIT_PERIOD
		);
		$seconds = (int)$value;
		// Enforce valid range (60 seconds to 1 hour)
		return max(60, min(3600, $seconds));
	}

	// ========== CODE DELIVERY ==========

	public function skipSendIfCodeExists(): bool {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_SKIP_SEND_IF_EXISTS,
			self::DEFAULT_SKIP_SEND_IF_EXISTS ? '1' : '0'
		) === '1';
	}

	// ========== EMAIL SETTINGS ==========

	public function includeEmailHeader(): bool {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_INCLUDE_EMAIL_HEADER,
			self::DEFAULT_INCLUDE_EMAIL_HEADER ? '1' : '0'
		) === '1';
	}

	// ========== DOMAIN RESTRICTIONS ==========

	public function getAllowedDomains(): array {
		$value = $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_ALLOWED_DOMAINS,
			self::DEFAULT_ALLOWED_DOMAINS
		);
		if ($value === '') {
			return [];
		}
		// Parse comma-separated domains and clean them
		$domains = array_map('trim', explode(',', $value));
		$domains = array_filter($domains, fn($d) => $d !== '');
		return array_map('strtolower', $domains);
	}

	public function preferLdapEmail(): bool {
		return $this->config->getAppValue(
			Application::APP_ID,
			self::KEY_PREFER_LDAP_EMAIL,
			self::DEFAULT_PREFER_LDAP_EMAIL ? '1' : '0'
		) === '1';
	}

	// ========== SETTERS (for admin settings) ==========

	public function setCodeLength(int $length): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_CODE_LENGTH,
			(string)max(4, min(12, $length))
		);
	}

	public function setCodeValidSeconds(int $seconds): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_CODE_VALID_SECONDS,
			(string)max(60, min(1800, $seconds))
		);
	}

	public function setMaxVerificationAttempts(int $attempts): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_MAX_VERIFICATION_ATTEMPTS,
			(string)max(1, min(10, $attempts))
		);
	}

	public function setUseAlphanumericCodes(bool $use): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_USE_ALPHANUMERIC,
			$use ? '1' : '0'
		);
	}

	public function setSendRateLimitAttempts(int $attempts): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_RATE_LIMIT_ATTEMPTS,
			(string)max(1, min(50, $attempts))
		);
	}

	public function setSendRateLimitPeriodSeconds(int $seconds): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_RATE_LIMIT_PERIOD,
			(string)max(60, min(3600, $seconds))
		);
	}

	public function setSkipSendIfCodeExists(bool $skip): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_SKIP_SEND_IF_EXISTS,
			$skip ? '1' : '0'
		);
	}

	public function setIncludeEmailHeader(bool $include): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_INCLUDE_EMAIL_HEADER,
			$include ? '1' : '0'
		);
	}

	public function setAllowedDomains(array $domains): void {
		$cleaned = [];
		foreach ($domains as $domain) {
			if (!is_string($domain)) {
				continue;
			}
			$domain = trim($domain);
			$domain = strtolower($domain);
			// Basic domain validation: alphanumeric, hyphens, dots only
			if ($domain !== '' && preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $domain)) {
				$cleaned[] = $domain;
			}
		}
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_ALLOWED_DOMAINS,
			implode(',', array_unique($cleaned))
		);
	}

	public function setPreferLdapEmail(bool $prefer): void {
		$this->config->setAppValue(
			Application::APP_ID,
			self::KEY_PREFER_LDAP_EMAIL,
			$prefer ? '1' : '0'
		);
	}

	/**
	 * Get all settings as an array (for API response).
	 */
	public function getAllSettings(): array {
		return [
			'codeLength' => $this->getCodeLength(),
			'codeValidSeconds' => $this->getCodeValidSeconds(),
			'codeValidMinutes' => (int)($this->getCodeValidSeconds() / 60),
			'maxVerificationAttempts' => $this->getMaxVerificationAttempts(),
			'useAlphanumericCodes' => $this->useAlphanumericCodes(),
			'rateLimitAttempts' => $this->getSendRateLimitAttempts(),
			'rateLimitPeriodSeconds' => $this->getSendRateLimitPeriodSeconds(),
			'rateLimitPeriodMinutes' => (int)($this->getSendRateLimitPeriodSeconds() / 60),
			'skipSendIfCodeExists' => $this->skipSendIfCodeExists(),
			'includeEmailHeader' => $this->includeEmailHeader(),
			'allowedDomains' => $this->getAllowedDomains(),
			'preferLdapEmail' => $this->preferLdapEmail(),
		];
	}
}

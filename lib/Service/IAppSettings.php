<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

interface IAppSettings {
	// ========== CODE SETTINGS ==========

	/**
	 * How many characters a 2FA code shall consist of.
	 *
	 * @return int number of characters (4-12)
	 */
	public function getCodeLength(): int;

	/**
	 * How long shall a stored 2FA code be valid.
	 *
	 * @return int seconds of validity
	 */
	public function getCodeValidSeconds(): int;

	/**
	 * How many failed verification attempts are allowed before the code is invalidated.
	 * This is a defense-in-depth measure on top of Nextcloud's built-in rate limiting.
	 *
	 * @return int maximum number of failed attempts before code invalidation
	 */
	public function getMaxVerificationAttempts(): int;

	// ========== CODE FORMAT ==========

	/**
	 * Whether to use alphanumeric codes (A-Z, 0-9) instead of numeric only.
	 * Alphanumeric codes provide significantly higher entropy.
	 *
	 * @return bool true for alphanumeric, false for numeric only
	 */
	public function useAlphanumericCodes(): bool;

	// ========== RATE LIMITING ==========

	/**
	 * How many e-mails may be sent during a certain period.
	 *
	 * @return int number of attempts allowed
	 */
	public function getSendRateLimitAttempts(): int;

	/**
	 * Period in which the defined amount of e-mails may be sent.
	 *
	 * @return int seconds of sliding window
	 */
	public function getSendRateLimitPeriodSeconds(): int;

	// ========== EMAIL SETTINGS ==========

	/**
	 * Whether to include the logo/header in the email.
	 *
	 * @return bool true to include header with logo
	 */
	public function includeEmailHeader(): bool;

	// ========== DOMAIN RESTRICTIONS ==========

	/**
	 * Get the list of allowed email domains.
	 * Empty array means all domains are allowed.
	 *
	 * @return array<string> list of allowed domains (e.g., ['company.com', 'corp.example.org'])
	 */
	public function getAllowedDomains(): array;

	/**
	 * Whether to prefer the LDAP-provided email address over the user-editable one.
	 * This provides better security as users cannot change their 2FA email.
	 *
	 * @return bool true to prefer LDAP email
	 */
	public function preferLdapEmail(): bool;
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

interface ICodeStorage {
	/**
	 * Verify a submitted code against the stored hash (timing-safe)
	 * @return bool True if code is valid and not expired
	 */
	public function verifyCode(string $userId, string $submittedCode): bool;

	/**
	 * Store a hashed code for the user
	 */
	public function writeCode(string $userId, string $code, ?int $createdAt = null): void;

	/**
	 * Delete stored code for user
	 */
	public function deleteCode(string $userId): void;

	/**
	 * Delete all expired codes
	 */
	public function deleteExpired(): void;

	/**
	 * Check if user can request a new code (rate limiting)
	 * @return bool True if within rate limits
	 */
	public function canSendCode(string $userId): bool;

	/**
	 * Record a code send attempt for rate limiting
	 */
	public function recordSendAttempt(string $userId): void;

	/**
	 * Get remaining seconds until user can request a new code
	 * @return int Seconds to wait, 0 if can send now
	 */
	public function getSecondsUntilCanResend(string $userId): int;
}

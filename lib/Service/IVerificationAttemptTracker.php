<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

/**
 * Tracks failed verification attempts for 2FA codes.
 * Provides defense-in-depth by invalidating codes after too many failed attempts.
 */
interface IVerificationAttemptTracker {
	/**
	 * Record a failed verification attempt and check if code should be invalidated.
	 *
	 * @param string $userId The user ID
	 * @return bool True if the code should be invalidated (max attempts reached)
	 */
	public function recordFailedAttempt(string $userId): bool;

	/**
	 * Reset the failed attempt counter for a user.
	 * Should be called when a new code is generated or verification succeeds.
	 *
	 * @param string $userId The user ID
	 */
	public function resetAttempts(string $userId): void;
}

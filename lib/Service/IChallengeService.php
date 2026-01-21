<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\RateLimitExceededException;
use OCP\IUser;

interface IChallengeService {
	/**
	 * Send a challenge code to the user via email
	 * @throws RateLimitExceededException if rate limit exceeded
	 */
	public function sendChallenge(IUser $user): void;

	/**
	 * Verify a submitted challenge code
	 * @return bool True if code is valid
	 */
	public function verifyChallenge(IUser $user, string $submittedCode): bool;

	/**
	 * Check if user can request a new challenge code
	 * @return bool True if within rate limits
	 */
	public function canSendChallenge(IUser $user): bool;

	/**
	 * Get seconds until user can request a new code
	 * @return int Seconds to wait, 0 if can send now
	 */
	public function getSecondsUntilCanResend(IUser $user): int;
}

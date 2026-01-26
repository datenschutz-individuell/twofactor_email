<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Exception;

use Exception;

class RateLimitExceededException extends Exception {
	public function __construct(
		private int $secondsRemaining = 0,
		string $message = 'Rate limit exceeded',
	) {
		parent::__construct($message);
	}

	public function getSecondsRemaining(): int {
		return $this->secondsRemaining;
	}
}

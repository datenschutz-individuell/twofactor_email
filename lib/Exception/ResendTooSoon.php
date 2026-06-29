<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Exception;

use Exception;
use Throwable;

/**
 * Thrown when a user requests a new code before the resend cooldown elapsed.
 */
final class ResendTooSoon extends Exception {
	public function __construct(
		public readonly int $retryAfterSeconds,
		?Throwable $previous = null,
	) {
		parent::__construct("Resend requested too soon, retry after {$retryAfterSeconds}s", previous: $previous);
	}
}

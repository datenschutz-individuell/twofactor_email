<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Exception;

use Throwable;

/**
 * The account asked the app to contact the mail server too often, so it was not
 * asked again. A failed send for everyone who only wants to know whether a code
 * went out, and its own type for the log and for the resend endpoint, which
 * would otherwise blame the mail server for something it was never asked to do.
 */
final class SendRateLimited extends SendEMailFailed {
	public function __construct(
		public readonly int $retryAfterSeconds,
		?Throwable $previous = null,
	) {
		parent::__construct("Send rate limit reached, retry after {$retryAfterSeconds}s", previous: $previous);
	}
}

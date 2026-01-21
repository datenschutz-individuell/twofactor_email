<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

interface IApplicationSettings
{
	/**
	 * How long a code remains valid after generation
	 */
	public function getCodeValidSeconds(): int;

	/**
	 * Maximum number of codes that can be sent within the rate limit window
	 */
	public function getMaxResendAttempts(): int;

	/**
	 * Time window in seconds for rate limiting resend attempts
	 */
	public function getResendWindowSeconds(): int;

	/**
	 * Number of digits in the generated code
	 */
	public function getCodeLength(): int;
}

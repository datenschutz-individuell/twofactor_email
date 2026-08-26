<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use Psr\Log\LoggerInterface;

/**
 * Logs a warning about a condition only the first time it is met. A stored setting
 * is read many times per request — the code validity alone once per rendered mail
 * part — and the condition is the same every time, so say it once.
 *
 * "Once" means once per request: Nextcloud's container keeps the instance it
 * autowired, so every reader shares this one. The condition names are therefore
 * shared as well — a second caller must not reuse a name, or its warning is
 * swallowed by the first.
 */
final class WarnOnce {

	/** @var array<string, true> conditions already logged for this instance */
	private array $reported = [];

	public function __construct(
		private readonly LoggerInterface $logger,
	) {
	}

	public function warn(string $condition, string $message): void {
		if (isset($this->reported[$condition])) {
			return;
		}
		$this->reported[$condition] = true;
		$this->logger->warning($message);
	}
}

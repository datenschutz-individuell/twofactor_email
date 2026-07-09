<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\BackgroundJob;

use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/**
 * Removes expired codes of users who did not return to the login page.
 * Without this job their hashed codes and timestamps would stay in the
 * user preferences indefinitely (data minimization).
 */
final class CleanUpExpiredCodes extends TimedJob {
	private const CLEANUP_INTERVAL_SECONDS = 24 * 60 * 60; // 1 day

	public function __construct(
		ITimeFactory $time,
		private readonly ICodeStorage $codeStorage,
	) {
		parent::__construct($time);
		$this->setInterval(self::CLEANUP_INTERVAL_SECONDS);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	protected function run($argument): void {
		$this->codeStorage->deleteExpired();
	}
}

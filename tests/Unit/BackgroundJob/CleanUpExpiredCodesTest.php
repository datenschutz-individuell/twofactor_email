<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\BackgroundJob;

use OCA\TwoFactorEMail\BackgroundJob\CleanUpExpiredCodes;
use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CleanUpExpiredCodesTest extends TestCase {
	/**
	 * @throws Exception
	 */
	public function testRunDeletesExpiredCodes(): void {
		$codeStorage = $this->createMock(ICodeStorage::class);
		$codeStorage->expects($this->once())->method('deleteExpired');
		$job = new CleanUpExpiredCodes($this->createMock(ITimeFactory::class), $codeStorage);

		// run() is protected by the TimedJob contract
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}
}

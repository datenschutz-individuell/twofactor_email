<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Service\WarnOnce;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class WarnOnceTest extends TestCase {
	private LoggerInterface&MockObject $logger;

	private WarnOnce $warnOnce;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->warnOnce = new WarnOnce($this->logger);
	}

	public function testLogsTheSameConditionOnlyOnce(): void {
		$this->logger->expects($this->once())->method('warning')->with('The code length is out of range');

		$this->warnOnce->warn('code length', 'The code length is out of range');
		$this->warnOnce->warn('code length', 'The code length is out of range');
	}

	/** One swallowed condition must not swallow the next: each is a separate repair. */
	public function testLogsEveryConditionOfItsOwn(): void {
		$this->logger->expects($this->exactly(2))->method('warning');

		$this->warnOnce->warn('code length', 'The code length is out of range');
		$this->warnOnce->warn('email subject', 'The email subject is not valid UTF-8');
	}
}

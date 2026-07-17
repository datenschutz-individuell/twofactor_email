<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Command;

use OCA\TwoFactorEMail\Command\CleanUp;
use OCA\TwoFactorEMail\Service\ICodeStorage;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanUpTest extends TestCase {
	private ICodeStorage&MockObject $codeStorage;

	private CommandTester $tester;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->codeStorage = $this->createMock(ICodeStorage::class);
		$this->tester = new CommandTester(new CleanUp($this->codeStorage));
	}

	public function testRemovesExpiredCodesAndReportsTheCount(): void {
		$this->codeStorage->expects($this->once())->method('deleteExpired')->willReturn(3);

		$exitCode = $this->tester->execute([]);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('Removed 3 expired code(s)', $this->tester->getDisplay());
	}

	public function testReportsZeroWhenNothingWasExpired(): void {
		$this->codeStorage->expects($this->once())->method('deleteExpired')->willReturn(0);

		$exitCode = $this->tester->execute([]);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('Removed 0 expired code(s)', $this->tester->getDisplay());
	}
}

<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Command;

use OCA\TwoFactorEMail\Command\DeleteCodes;
use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteCodesTest extends TestCase {
	private ICodeStorage&MockObject $codeStorage;

	private IUserManager&MockObject $userManager;

	private CommandTester $tester;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->codeStorage = $this->createMock(ICodeStorage::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->tester = new CommandTester(new DeleteCodes($this->codeStorage, $this->userManager));
	}

	/**
	 * @throws Exception
	 */
	public function testDeletesCodeOfSingleUser(): void {
		$this->userManager->method('get')->with('alice')->willReturn($this->createMock(IUser::class));
		$this->codeStorage->expects($this->once())->method('deleteCode')->with('alice');
		$this->codeStorage->expects($this->never())->method('deleteAllCodes');

		$exitCode = $this->tester->execute(['uid' => 'alice']);

		$this->assertSame(Command::SUCCESS, $exitCode);
	}

	public function testWarnsAboutUnknownUserButStillDeletes(): void {
		$this->userManager->method('get')->with('ghost')->willReturn(null);
		$this->codeStorage->expects($this->once())->method('deleteCode')->with('ghost');

		$exitCode = $this->tester->execute(['uid' => 'ghost']);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('No user with id "ghost" exists', $this->tester->getDisplay());
	}

	public function testDeletesCodesOfAllUsers(): void {
		$this->codeStorage->expects($this->once())->method('deleteAllCodes')->willReturn(3);
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$exitCode = $this->tester->execute(['--all' => true]);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('3 user(s)', $this->tester->getDisplay());
	}

	public function testRejectsMissingUserIdAndAll(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');
		$this->codeStorage->expects($this->never())->method('deleteAllCodes');

		$this->expectException(InvalidOptionException::class);

		$this->tester->execute([]);
	}

	public function testRejectsUserIdCombinedWithAll(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');
		$this->codeStorage->expects($this->never())->method('deleteAllCodes');

		$this->expectException(InvalidOptionException::class);

		$this->tester->execute(['uid' => 'alice', '--all' => true]);
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Listener;

use OCA\TwoFactorEMail\Listener\EMailChanged;
use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\UserChangedEvent;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EMailChangedTest extends TestCase {
	private ICodeStorage&MockObject $codeStorage;

	private EMailChanged $listener;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->codeStorage = $this->createMock(ICodeStorage::class);

		$this->listener = new EMailChanged($this->codeStorage);
	}

	/**
	 * @throws Exception
	 */
	private function mockUser(): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		return $user;
	}

	/**
	 * @throws Exception
	 */
	public function testDropsTheCodeWhenTheAddressChanges(): void {
		$this->codeStorage->expects($this->once())->method('deleteCode')->with('alice');

		$this->listener->handle(new UserChangedEvent($this->mockUser(), 'eMailAddress', 'new@example.com', 'old@example.com'));
	}

	/**
	 * @throws Exception
	 */
	public function testDropsTheCodeWhenTheAddressIsCleared(): void {
		$this->codeStorage->expects($this->once())->method('deleteCode')->with('alice');

		$this->listener->handle(new UserChangedEvent($this->mockUser(), 'eMailAddress', '', 'old@example.com'));
	}

	/**
	 * @throws Exception
	 */
	public function testKeepsTheCodeWhenTheAddressIsWrittenUnchanged(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->listener->handle(new UserChangedEvent($this->mockUser(), 'eMailAddress', 'same@example.com', 'same@example.com'));
	}

	/**
	 * @throws Exception
	 */
	/**
	 * The emitter compares against null for an address that was never set, so clearing
	 * an already empty one fires with '' against null. Such an account may still be
	 * reachable through its primary address, and its pending code has to survive.
	 *
	 * @throws Exception
	 */
	public function testKeepsTheCodeWhenAnAlreadyEmptyAddressIsCleared(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->listener->handle(new UserChangedEvent($this->mockUser(), 'eMailAddress', '', null));
	}

	/**
	 * @throws Exception
	 */
	public function testIgnoresAnotherFeatureOfTheSameEvent(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->listener->handle(new UserChangedEvent($this->mockUser(), 'displayName', 'New Name', 'Old Name'));
	}

	public function testIgnoresAnUnrelatedEvent(): void {
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->listener->handle(new Event());
	}
}

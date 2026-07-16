<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Listener;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Listener\EMailDeleted;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\Accounts\UserUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\User\Events\UserChangedEvent;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EMailDeletedTest extends TestCase {
	private IStateManager&MockObject $stateManager;

	private EMailDeleted $listener;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->stateManager = $this->createMock(IStateManager::class);

		$this->listener = new EMailDeleted($this->stateManager);
	}

	/**
	 * @throws Exception
	 */
	private function mockUser(?string $email): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($email);
		return $user;
	}

	private function emailCleared(IUser $user): UserChangedEvent {
		return new UserChangedEvent($user, 'eMailAddress', '', 'old@example.com');
	}

	public function testDisablesWhenAnotherProviderRemains(): void {
		$user = $this->mockUser(null);
		$this->stateManager->method('isEnabled')->willReturn(true);
		$this->stateManager->method('hasOtherActiveProvider')->willReturn(true);
		$this->stateManager->expects($this->once())->method('disable')->with($user, StateChangeActor::SYSTEM);

		$this->listener->handle($this->emailCleared($user));
	}

	public function testKeepsSoleFactorEnabledWhoeverRemovedTheEmail(): void {
		// Fail-closed: the only second factor is never auto-disabled
		$user = $this->mockUser(null);
		$this->stateManager->method('isEnabled')->willReturn(true);
		$this->stateManager->method('hasOtherActiveProvider')->willReturn(false);
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle($this->emailCleared($user));
	}

	public function testKeepsProviderWhenAddressStillPresent(): void {
		// The address was changed, not cleared
		$user = $this->mockUser('new@example.com');
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle(new UserChangedEvent($user, 'eMailAddress', 'new@example.com', 'old@example.com'));
	}

	public function testDoesNotDisableAnAlreadyDisabledProvider(): void {
		$user = $this->mockUser(null);
		$this->stateManager->method('isEnabled')->willReturn(false);
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle($this->emailCleared($user));
	}

	public function testIgnoresOtherChangedFeatures(): void {
		$user = $this->mockUser(null);
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle(new UserChangedEvent($user, 'displayName', 'Alice'));
	}

	/**
	 * @throws Exception
	 */
	public function testIgnoresUnrelatedProfileUpdate(): void {
		// Regression: a UserUpdatedEvent (any profile change) must not trigger a
		// disable — even for a user who currently has no email address. Only a
		// genuine email-change event (UserChangedEvent) is a trigger.
		$user = $this->mockUser(null);
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle(new UserUpdatedEvent($user, []));
	}

	public function testIgnoresForeignEvents(): void {
		$this->stateManager->expects($this->never())->method('disable');

		$this->listener->handle(new Event());
	}
}

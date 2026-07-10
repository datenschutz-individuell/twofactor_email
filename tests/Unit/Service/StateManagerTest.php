<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Service\StateManager;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StateManagerTest extends TestCase {
	private IRegistry&MockObject $registry;

	private StateManager $stateManager;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->registry = $this->createMock(IRegistry::class);
		$this->stateManager = new StateManager($this->createMock(IEventDispatcher::class), $this->registry);
	}

	/**
	 * @throws Exception
	 */
	private function withProviderStates(array $states): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$this->registry->method('getProviderStates')->with($user)->willReturn($states);
		return $user;
	}

	public function testHasOtherActiveProviderIsTrueForAnEnabledNonEmailProvider(): void {
		$user = $this->withProviderStates(['email' => true, 'totp' => true]);

		$this->assertTrue($this->stateManager->hasOtherActiveProvider($user));
	}

	public function testHasOtherActiveProviderIsFalseWhenEmailIsTheOnlyEnabledOne(): void {
		$user = $this->withProviderStates(['email' => true, 'totp' => false]);

		$this->assertFalse($this->stateManager->hasOtherActiveProvider($user));
	}

	public function testHasOtherActiveProviderIgnoresEmailItself(): void {
		$user = $this->withProviderStates(['email' => true]);

		$this->assertFalse($this->stateManager->hasOtherActiveProvider($user));
	}

	public function testHasOtherActiveProviderIsFalseWithoutAnyProviders(): void {
		$user = $this->withProviderStates([]);

		$this->assertFalse($this->stateManager->hasOtherActiveProvider($user));
	}
}

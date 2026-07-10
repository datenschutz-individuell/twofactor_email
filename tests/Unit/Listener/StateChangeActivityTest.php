<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Listener;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCA\TwoFactorEMail\Listener\StateChangeActivity;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class StateChangeActivityTest extends TestCase {

	private StateChangeActivity $listener;

	private IManager&MockObject $activityManager;

	private IUserSession&MockObject $userSession;

	/**
	 * @throws Exception
	 */
	private function mockActivityEvent(): IEvent&MockObject {
		$activityEvent = $this->createMock(IEvent::class);
		$activityEvent->method('setApp')->willReturnSelf();
		$activityEvent->method('setType')->willReturnSelf();
		$activityEvent->method('setAuthor')->willReturnSelf();
		$activityEvent->method('setAffectedUser')->willReturnSelf();
		$activityEvent->method('setSubject')->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($activityEvent);
		return $activityEvent;
	}

	/**
	 * @throws Exception
	 */
	private function loginAs(?string $uid): void {
		$actor = null;
		if ($uid !== null) {
			$actor = $this->createMock(IUser::class);
			$actor->method('getUID')->willReturn($uid);
		}
		$this->userSession->method('getUser')->willReturn($actor);
	}

	/**
	 * @throws Exception
	 */
	public function testUserChangeIsAuthoredByTheUser(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$activityEvent = $this->mockActivityEvent();
		$this->loginAs('alice');
		$activityEvent->expects($this->once())->method('setAuthor')->with('alice')->willReturnSelf();
		$activityEvent->expects($this->once())->method('setAffectedUser')->with('alice')->willReturnSelf();
		$this->activityManager->expects($this->once())->method('publish');

		$this->listener->handle(new StateChanged($user, true, StateChangeActor::USER));
	}

	/**
	 * @throws Exception
	 */
	public function testAdminChangeIsAuthoredByTheActingAdmin(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$activityEvent = $this->mockActivityEvent();
		$this->loginAs('admin');
		$activityEvent->expects($this->once())->method('setAuthor')->with('admin')->willReturnSelf();
		$activityEvent->expects($this->once())->method('setAffectedUser')->with('alice')->willReturnSelf();

		$this->listener->handle(new StateChanged($user, false, StateChangeActor::ADMIN));
	}

	/**
	 * @throws Exception
	 */
	public function testAdminChangeWithoutSessionHasNoAuthor(): void {
		// e.g. `occ twofactorauth:disable` — no user in the session
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$activityEvent = $this->mockActivityEvent();
		$this->loginAs(null);
		$activityEvent->expects($this->once())->method('setAuthor')->with('')->willReturnSelf();

		$this->listener->handle(new StateChanged($user, false, StateChangeActor::ADMIN));
	}

	/**
	 * @throws Exception
	 */
	public function testSystemChangeHasNoAuthorAndTheNoEmailSubject(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$activityEvent = $this->mockActivityEvent();
		$this->loginAs('alice'); // even with a session, a SYSTEM change stays authorless
		$activityEvent->expects($this->once())->method('setAuthor')->with('')->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setSubject')
			->with('twofactor_email_disabled_no_email')
			->willReturnSelf();

		$this->listener->handle(new StateChanged($user, false, StateChangeActor::SYSTEM));
	}

	public function testIgnoresForeignEvents(): void {
		$this->activityManager->expects($this->never())->method('generateEvent');

		$this->listener->handle(new Event());
	}

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IManager::class);
		$this->userSession = $this->createMock(IUserSession::class);

		$this->listener = new StateChangeActivity($this->activityManager, $this->userSession);
	}
}

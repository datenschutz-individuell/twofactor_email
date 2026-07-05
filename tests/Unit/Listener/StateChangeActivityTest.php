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
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class StateChangeActivityTest extends TestCase {

	private StateChangeActivity $listener;

	private IManager|MockObject $activityManager;

	/**
	 * @throws Exception
	 */
	public function testHandleStateEvent() {
		$uid = 'user234';
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$event = new StateChanged($user, true);
		$activityEvent = $this->createMock(IEvent::class);
		$this->activityManager->expects($this->once())
			->method('generateEvent')
			->willReturn($activityEvent);
		$activityEvent->expects($this->once())
			->method('setApp')
			->with('twofactor_email')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setType')
			->with('security')
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAuthor')
			->with($uid)
			->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setAffectedUser')
			->with($uid)
			->willReturnSelf();
		$this->activityManager->expects($this->once())
			->method('publish')
			->with($activityEvent);

		$this->listener->handle($event);
	}

	/**
	 * @throws Exception
	 */
	public function testSystemDisableCreatesNoEmailActivity(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('user234');
		$event = new StateChanged($user, false, StateChangeActor::SYSTEM);
		$activityEvent = $this->createMock(IEvent::class);
		$activityEvent->method('setApp')->willReturnSelf();
		$activityEvent->method('setType')->willReturnSelf();
		$activityEvent->method('setAuthor')->willReturnSelf();
		$activityEvent->method('setAffectedUser')->willReturnSelf();
		$activityEvent->expects($this->once())
			->method('setSubject')
			->with('twofactor_email_disabled_no_email')
			->willReturnSelf();
		$this->activityManager->method('generateEvent')->willReturn($activityEvent);
		$this->activityManager->expects($this->once())->method('publish');

		$this->listener->handle($event);
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

		$this->listener = new StateChangeActivity($this->activityManager);
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Listener;

use DateTime;
use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCA\TwoFactorEMail\Listener\StateChangeNotification;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\IUser;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StateChangeNotificationTest extends TestCase {
	private IManager&MockObject $notificationManager;

	private StateChangeNotification $listener;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(IManager::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new DateTime());

		$this->listener = new StateChangeNotification($this->notificationManager, $timeFactory);
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
	private function mockNotification(): INotification&MockObject {
		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->with('alice')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')->willReturnSelf();
		return $notification;
	}

	/**
	 * @throws Exception
	 */
	private function expectNotificationWithSubject(string $subject): void {
		$notification = $this->mockNotification();
		$notification->expects($this->once())
			->method('setSubject')
			->with($subject)
			->willReturnSelf();
		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())->method('notify');
	}

	public function testNotifiesOnAdminEnable(): void {
		$this->expectNotificationWithSubject('twofactor_email_enabled_by_admin');

		$this->listener->handle(new StateChanged($this->mockUser(), true, StateChangeActor::ADMIN));
	}

	public function testNotifiesOnAutomaticDisable(): void {
		$this->expectNotificationWithSubject('twofactor_email_disabled_no_email');

		$this->listener->handle(new StateChanged($this->mockUser(), false, StateChangeActor::SYSTEM));
	}

	public function testDoesNotNotifyAboutOwnChanges(): void {
		$this->notificationManager->expects($this->never())->method('notify');

		$this->listener->handle(new StateChanged($this->mockUser(), true, StateChangeActor::USER));
	}

	public function testIgnoresForeignEvents(): void {
		$this->notificationManager->expects($this->never())->method('notify');

		$this->listener->handle(new Event());
	}
}

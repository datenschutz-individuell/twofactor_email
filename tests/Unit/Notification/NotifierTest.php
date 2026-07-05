<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Notification;

use OCA\TwoFactorEMail\Notification\Notifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {
	private Notifier $notifier;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturn('/path/app-dark.svg');
		$urlGenerator->method('getAbsoluteURL')->willReturn('https://cloud.example.com/path/app-dark.svg');

		$this->notifier = new Notifier($l10nFactory, $urlGenerator);
	}

	/**
	 * @throws Exception
	 */
	private function mockNotification(string $app, string $subject): INotification&MockObject {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn($app);
		$notification->method('getSubject')->willReturn($subject);
		return $notification;
	}

	public function testPreparesKnownSubject(): void {
		$notification = $this->mockNotification('twofactor_email', 'twofactor_email_disabled_no_email');
		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Email two-factor authentication was disabled because your account has no email address')
			->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->willReturnSelf();

		$this->notifier->prepare($notification, 'en');
	}

	public function testRejectsForeignApp(): void {
		$notification = $this->mockNotification('other_app', 'irrelevant');

		$this->expectException(UnknownNotificationException::class);

		$this->notifier->prepare($notification, 'en');
	}

	public function testRejectsUnknownSubject(): void {
		$notification = $this->mockNotification('twofactor_email', 'no_such_subject');

		$this->expectException(UnknownNotificationException::class);

		$this->notifier->prepare($notification, 'en');
	}
}

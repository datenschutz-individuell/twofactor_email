<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Activity\Notification;
use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCP\Activity\IManager as ActivityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<StateChanged>
 */
final class StateChangeActivity implements IEventListener {

	public function __construct(
		private readonly ActivityManager $activityManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof StateChanged) {
			return;
		}
		$notification = Notification::fromStateChange($event->getActor(), $event->isEnabled());
		$user = $event->getUser();

		$activity = $this->activityManager->generateEvent();
		$activity->setApp(Application::APP_ID)
			->setType('security')
			->setAuthor($user->getUID())
			->setAffectedUser($user->getUID())
			->setSubject($notification->value);
		$this->activityManager->publish($activity);
	}
}

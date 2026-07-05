<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Activity\Notification;
use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCP\Activity\IManager as ActivityManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<StateChanged>
 */
final class StateChangeActivity implements IEventListener {

	public function __construct(
		private readonly ActivityManager $activityManager,
		private readonly IUserSession $userSession,
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
			->setAuthor($this->author($event))
			->setAffectedUser($user->getUID())
			->setSubject($notification->value);
		$this->activityManager->publish($activity);
	}

	/**
	 * The author is who caused the change, not who it affects. An admin action
	 * is attributed to the acting admin (empty if none is in the session, e.g.
	 * an occ command), and an automatic change to no one.
	 */
	private function author(StateChanged $event): string {
		return match ($event->getActor()) {
			StateChangeActor::USER => $event->getUser()->getUID(),
			StateChangeActor::ADMIN => $this->userSession->getUser()?->getUID() ?? '',
			StateChangeActor::SYSTEM => '',
		};
	}
}

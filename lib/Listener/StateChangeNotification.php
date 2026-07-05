<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Activity\Notification;
use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Notification\IManager as NotificationManager;

/**
 * Sends the affected user an immediate Nextcloud notification when their
 * provider state was changed by someone else (admin) or something else
 * (automatic disable). Users changing the state themselves see the result
 * in the settings UI and get no notification.
 *
 * @template-implements IEventListener<StateChanged>
 */
final class StateChangeNotification implements IEventListener {

	public function __construct(
		private readonly NotificationManager $notificationManager,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof StateChanged) {
			return;
		}
		$uid = $event->getUser()->getUID();

		// An earlier notification about this provider's state is outdated now,
		// whoever caused the change — dismiss it before possibly adding a new one.
		$outdated = $this->notificationManager->createNotification();
		$outdated->setApp(Application::APP_ID)
			->setUser($uid)
			->setObject('twofactor_email_state', $uid);
		$this->notificationManager->dismissNotification($outdated);

		if ($event->getActor() === StateChangeActor::USER) {
			return;
		}

		$subject = Notification::fromStateChange($event->getActor(), $event->isEnabled());

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime($this->timeFactory->getDateTime())
			->setObject('twofactor_email_state', $uid)
			->setSubject($subject->value);
		$this->notificationManager->notify($notification);
	}
}

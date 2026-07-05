<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Notification;

use OCA\TwoFactorEMail\Activity\Notification;
use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10nFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Renders the notifications sent by StateChangeNotification. The subject
 * texts are shared with the activity entries (Activity\Notification).
 */
final class Notifier implements INotifier {

	public function __construct(
		private readonly L10nFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Email two-factor authentication');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}
		$subject = Notification::tryFrom($notification->getSubject());
		if ($subject === null) {
			throw new UnknownNotificationException();
		}

		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$notification->setParsedSubject($l->t($subject->getSubjectText()));
		$notification->setIcon($this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg'),
		));
		return $notification;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\Accounts\UserUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\User\Events\UserChangedEvent;

/**
 * Disables the provider when an account loses its email address — codes
 * could no longer be delivered. Listens on both events that cover the two
 * UI paths: profile/account updates (UserUpdatedEvent) and direct email
 * changes, e.g. by an admin via the users page (UserChangedEvent).
 *
 * Not covered (Nextcloud fires no event): raw preference edits like
 * `occ user:setting <uid> settings email --delete`.
 *
 * @template-implements IEventListener<UserUpdatedEvent|UserChangedEvent>
 */
final class EMailDeleted implements IEventListener {

	public function __construct(
		private readonly IStateManager $service,
	) {
	}

	public function handle(Event $event): void {
		$user = $this->affectedUser($event);
		if ($user === null || $user->getEMailAddress() !== null) {
			return;
		}
		// Only disable an enabled provider: without this guard, every profile
		// update of a user without an email address would dispatch another
		// StateChanged event and create a bogus activity entry.
		if (!$this->service->isEnabled($user)) {
			return;
		}
		$this->service->disable($user, StateChangeActor::SYSTEM);
	}

	private function affectedUser(Event $event): ?IUser {
		if ($event instanceof UserUpdatedEvent) {
			return $event->getUser();
		}
		if ($event instanceof UserChangedEvent && $event->getFeature() === 'eMailAddress') {
			return $event->getUser();
		}
		return null;
	}
}

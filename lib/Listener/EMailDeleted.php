<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

/**
 * Disables email 2FA when an account loses its email address, but only when
 * that removes no protection — it never silently downgrades an account to
 * password-only.
 *
 * Trigger: UserChangedEvent for the 'eMailAddress' feature. Every path that
 * clears the address used for delivery goes through IUser::setSystemEMailAddress()
 * (personal settings, the users page, the provisioning API, `occ user:setting`),
 * which fires this event. Account-only changes (additional emails,
 * `occ user:profile`) leave getEMailAddress() intact and are correctly ignored.
 *
 * Once the address is gone and the provider is still enabled:
 *   - another active provider remains → disable, no protection is lost;
 *   - email was the sole factor → keep it enabled (fail-closed), whoever
 *     cleared the address.
 *
 * Disabling the sole factor would drop the account to password-only. That would
 * skip the password confirmation which turning 2FA off directly requires
 * (StateController), and it would let anyone who may edit a user's email — a
 * group subadmin, a directory sync, or someone on an unlocked session — remove
 * a second factor without it. So the provider stays enabled and the challenge
 * fails until the address is restored or an admin runs
 * `occ twofactorauth:disable <uid> email`.
 *
 * @template-implements IEventListener<UserChangedEvent>
 */
final class EMailDeleted implements IEventListener {

	public function __construct(
		private readonly IStateManager $stateManager,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserChangedEvent || $event->getFeature() !== 'eMailAddress') {
			return;
		}
		$user = $event->getUser();
		if ($user->getEMailAddress() !== null) {
			return; // still deliverable (e.g. the address was changed, not cleared)
		}
		if (!$this->stateManager->isEnabled($user)) {
			return;
		}
		// Disable only when another factor remains; keep the sole factor enabled
		// (fail-closed) — see the class docblock.
		if ($this->stateManager->hasOtherActiveProvider($user)) {
			$this->stateManager->disable($user, StateChangeActor::SYSTEM);
		}
	}
}

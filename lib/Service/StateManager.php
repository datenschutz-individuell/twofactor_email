<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCA\TwoFactorEMail\Event\StateChanged;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;

/**
 * enable() and disable() only dispatch a StateChanged event. The registry is
 * updated in \OCA\TwoFactorEMail\Listener\StateChangeRegistryUpdater, because
 * updating it here would create a circular dependency with the provider.
 */
final class StateManager implements IStateManager {

	private const PROVIDER_ID = 'email';

	/**
	 * Nextcloud does not accept backup codes as the only second factor: its own
	 * check for an account being two-factor protected leaves them out. So they
	 * cannot stand in for this provider either.
	 */
	private const BACKUP_CODES_PROVIDER_ID = 'backup_codes';

	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IRegistry $registry,
	) {
	}

	public function enable(IUser $user, StateChangeActor $actor = StateChangeActor::USER): void {
		$this->eventDispatcher->dispatchTyped(new StateChanged($user, true, $actor));
	}

	public function disable(IUser $user, StateChangeActor $actor = StateChangeActor::USER): void {
		$this->eventDispatcher->dispatchTyped(new StateChanged($user, false, $actor));
	}

	public function isEnabled(IUser $user): bool {
		return $this->registry->getProviderStates($user)[self::PROVIDER_ID] ?? false;
	}

	public function hasOtherActiveProvider(IUser $user): bool {
		foreach ($this->registry->getProviderStates($user) as $providerId => $enabled) {
			if ($enabled && $providerId !== self::PROVIDER_ID && $providerId !== self::BACKUP_CODES_PROVIDER_ID) {
				return true;
			}
		}
		return false;
	}
}

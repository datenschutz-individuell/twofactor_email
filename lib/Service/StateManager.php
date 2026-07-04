<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

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
	public function __construct(
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IRegistry $registry,
	) {
	}

	public function enable(IUser $user, bool $byAdmin = false): void {
		$this->eventDispatcher->dispatchTyped(new StateChanged($user, true, $byAdmin));
	}

	public function disable(IUser $user, bool $byAdmin = false): void {
		$this->eventDispatcher->dispatchTyped(new StateChanged($user, false, $byAdmin));
	}

	public function isEnabled(IUser $user): bool {
		return $this->registry->getProviderStates($user)['email'] ?? false;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Event\StateChanged;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<StateChanged>
 */
final class StateChangeRegistryUpdater implements IEventListener {

	public function __construct(
		private readonly IRegistry $registry,
		private readonly IProvider $provider,
	) {
	}

	public function handle(Event $event): void {
		if ($event->isEnabled()) {
			$this->registry->enableProviderFor($this->provider, $event->getUser());
		} else {
			$this->registry->disableProviderFor($this->provider, $event->getUser());
		}
	}
}

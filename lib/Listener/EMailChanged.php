<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Listener;

use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

/**
 * Drops a stored code when the address for delivery changes.
 *
 * A code stays valid for minutes, and it was mailed to the address in force at
 * the time. Once that address changes, the mailbox that holds the code may
 * belong to someone else — a typo being corrected is enough — so the code must
 * stop being accepted. The user simply receives a new one at the new address.
 *
 * Trigger: UserChangedEvent for the 'eMailAddress' feature, the same event
 * EMailDeleted listens to. Clearing the address ends here as well: no address
 * means no delivery, so a code left behind could only ever be a leftover.
 *
 * @template-implements IEventListener<UserChangedEvent>
 */
final class EMailChanged implements IEventListener {

	public function __construct(
		private readonly ICodeStorage $codeStorage,
	) {
	}

	/**
	 * @psalm-suppress DocblockTypeContradiction Deliberate fail-closed guard:
	 *   the generic narrows $event to UserChangedEvent, but we still verify it
	 *   at runtime rather than trust the dispatcher registration.
	 */
	public function handle(Event $event): void {
		if (!$event instanceof UserChangedEvent || $event->getFeature() !== 'eMailAddress') {
			return;
		}
		if ((string)$event->getValue() === (string)$event->getOldValue()) {
			// Rewriting the same address must never break a login in progress. The cast
			// is what makes that true: the emitter compares against null for an address
			// that was never set, so clearing an already empty one does fire, with ''
			// against null. Such an account may still receive mail through its primary
			// address, which this event says nothing about.
			return;
		}
		$this->codeStorage->deleteCode($event->getUser()->getUID());
	}
}

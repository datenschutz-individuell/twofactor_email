<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Event\StateChangeActor;
use OCP\IUser;

interface IStateManager {
	public function enable(IUser $user, StateChangeActor $actor = StateChangeActor::USER): void;

	public function disable(IUser $user, StateChangeActor $actor = StateChangeActor::USER): void;

	public function isEnabled(IUser $user): bool;

	/**
	 * Whether the user has another active 2FA provider besides email, i.e.
	 * disabling email would not leave the account without a second factor.
	 */
	public function hasOtherActiveProvider(IUser $user): bool;
}

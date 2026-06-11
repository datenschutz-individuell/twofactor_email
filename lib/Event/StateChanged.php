<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Event;

use OCP\EventDispatcher\Event;
use OCP\IUser;

final class StateChanged extends Event {

	public function __construct(
		private readonly IUser $user,
		private readonly bool $enabled,
		private readonly bool $byAdmin = false,
	) {
		parent::__construct();
	}

	public function getUser(): IUser {
		return $this->user;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function byAdmin(): bool {
		return $this->byAdmin;
	}
}

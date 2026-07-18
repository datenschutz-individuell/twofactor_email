<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Activity;

use OCA\TwoFactorEMail\Activity\Notification;
use OCA\TwoFactorEMail\Event\StateChangeActor;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase {
	public function testUserEnableMapsToEnabledByUser(): void {
		$this->assertSame(Notification::ENABLED_BY_USER, Notification::fromStateChange(StateChangeActor::USER, true));
	}

	public function testUserDisableMapsToDisabledByUser(): void {
		$this->assertSame(Notification::DISABLED_BY_USER, Notification::fromStateChange(StateChangeActor::USER, false));
	}

	public function testAdminEnableMapsToEnabledByAdmin(): void {
		$this->assertSame(Notification::ENABLED_BY_ADMIN, Notification::fromStateChange(StateChangeActor::ADMIN, true));
	}

	public function testAdminDisableMapsToDisabledByAdmin(): void {
		$this->assertSame(Notification::DISABLED_BY_ADMIN, Notification::fromStateChange(StateChangeActor::ADMIN, false));
	}

	public function testSystemAlwaysMapsToNoEmailRegardlessOfFlag(): void {
		// The system only ever disables, so the enabled flag is irrelevant.
		$this->assertSame(Notification::DISABLED_NO_EMAIL, Notification::fromStateChange(StateChangeActor::SYSTEM, false));
		$this->assertSame(Notification::DISABLED_NO_EMAIL, Notification::fromStateChange(StateChangeActor::SYSTEM, true));
	}
}

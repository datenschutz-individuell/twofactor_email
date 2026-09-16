<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Support;

use OCA\TwoFactorEMail\Test\Support\ServerL10N;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ServerL10NTest extends TestCase {
	public function testEmptyTranslationThrowsAsOnTheServer(): void {
		$this->expectException(RuntimeException::class);

		(new ServerL10N(['Your code' => '']))->t('Your code');
	}
}

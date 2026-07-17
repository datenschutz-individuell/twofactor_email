<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Service\EMailAddressMasker;
use PHPUnit\Framework\TestCase;

class EMailAddressMaskerTest extends TestCase {
	private EMailAddressMasker $masker;

	protected function setUp(): void {
		parent::setUp();
		$this->masker = new EMailAddressMasker();
	}

	public function testMasksATypicalAddress(): void {
		$this->assertSame('a*@*.com', $this->masker->maskForUI('alice@example.com'));
	}

	public function testKeepsOnlyTheTopLevelDomainOfAMultiLabelDomain(): void {
		$this->assertSame('b*@*.uk', $this->masker->maskForUI('bob@mail.example.co.uk'));
	}

	public function testSingleLabelDomainHasNoTld(): void {
		$this->assertSame('u*@*', $this->masker->maskForUI('user@localhost'));
	}

	public function testReturnsInputWithoutAnAtSignUnchanged(): void {
		$this->assertSame('notanemail', $this->masker->maskForUI('notanemail'));
	}

	public function testReturnsInputWithMultipleAtSignsUnchanged(): void {
		$this->assertSame('a@b@c', $this->masker->maskForUI('a@b@c'));
	}

	public function testReturnsEmptyInputUnchanged(): void {
		$this->assertSame('', $this->masker->maskForUI(''));
	}
}

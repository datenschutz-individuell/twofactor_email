<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Migration;

use OCA\TwoFactorEMail\Migration\RemovePersistedDefaultTemplate;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RemovePersistedDefaultTemplateTest extends TestCase {
	private const OLD_DEFAULT_TEMPLATE
		= "Your two-factor authentication code is: {code}\n\n"
		. 'If you tried to login, please enter that code on {cloud}. '
		. 'If you did not, somebody else did and knows your email address '
		. 'or username – and your password!';

	private IAppSettings&MockObject $appSettings;

	private RemovePersistedDefaultTemplate $step;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appSettings = $this->createMock(IAppSettings::class);

		$this->step = new RemovePersistedDefaultTemplate($this->appSettings);
	}

	/**
	 * @throws Exception
	 */
	public function testRemovesThePersistedOldDefault(): void {
		$this->appSettings->method('getEMailTemplate')->willReturn(self::OLD_DEFAULT_TEMPLATE);
		$this->appSettings->expects($this->once())->method('setEMailTemplate')->with('');

		$this->step->run($this->createMock(IOutput::class));
	}

	/**
	 * @throws Exception
	 */
	public function testKeepsCustomizedTemplates(): void {
		$this->appSettings->method('getEMailTemplate')->willReturn('My custom text with {code}');
		$this->appSettings->expects($this->never())->method('setEMailTemplate');

		$this->step->run($this->createMock(IOutput::class));
	}

	/**
	 * @throws Exception
	 */
	public function testKeepsEmptyTemplates(): void {
		$this->appSettings->method('getEMailTemplate')->willReturn('');
		$this->appSettings->expects($this->never())->method('setEMailTemplate');

		$this->step->run($this->createMock(IOutput::class));
	}
}

<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\AppSettings;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AppSettingsTest extends TestCase {
	private IAppConfig&MockObject $appConfig;

	private AppSettings $settings;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []) => vsprintf($text, (array)$parameters),
		);

		$this->settings = new AppSettings($this->appConfig, $l10n);
	}

	public function testGetCodeLengthReadsFromAppConfig(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'code_length', 6)
			->willReturn(8);

		$this->assertSame(8, $this->settings->getCodeLength());
	}

	public function testGetEMailSubjectDefaultsToEmpty(): void {
		// The stored value is empty by default; emptiness signals "use default"
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'email_subject', '')
			->willReturn('');

		$this->assertSame('', $this->settings->getEMailSubject());
	}

	public function testDefaultEMailSubjectKeepsPlaceholders(): void {
		$this->assertSame(
			'Login attempt for {user} @ {cloud}',
			$this->settings->getDefaultEMailSubject(),
		);
	}

	public function testDefaultEMailBodyStructure(): void {
		$this->assertSame(
			"{logo}\n\n"
			. "Your two-factor authentication code for {cloud} is:\n\n"
			. "{code}\n\n"
			. 'The code is valid for {validity} minutes. '
			. 'If you did not try to log in, somebody else knows your username and your password '
			. '— change your password and inform your administrator.',
			$this->settings->getDefaultEMailBody(),
		);
	}

	public function testSetEMailSubjectWritesToAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with(Application::APP_ID, 'email_subject', 'Hello {user}');

		$this->settings->setEMailSubject('Hello {user}');
	}

	public function testSetCodeLengthWritesToAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('setValueInt')
			->with(Application::APP_ID, 'code_length', 8);

		$this->settings->setCodeLength(8);
	}

	public function testGetResendMinSecondsDefaultsTo60(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'resend_min_seconds', 60)
			->willReturn(60);

		$this->assertSame(60, $this->settings->getResendMinSeconds());
	}

	public function testSetResendMinSecondsWritesToAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('setValueInt')
			->with(Application::APP_ID, 'resend_min_seconds', 30);

		$this->settings->setResendMinSeconds(30);
	}

	public function testResetToDefaultsDeletesAllKeys(): void {
		$this->appConfig->expects($this->exactly(5))->method('deleteKey');

		$this->settings->resetToDefaults();
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\AppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCA\TwoFactorEMail\Service\WarnOnce;
use OCP\IAppConfig;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AppSettingsTest extends TestCase {
	private IAppConfig&MockObject $appConfig;

	private LoggerInterface&MockObject $logger;

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

		$this->logger = $this->createMock(LoggerInterface::class);
		$this->settings = new AppSettings($this->appConfig, $l10n, new WarnOnce($this->logger));
	}

	public function testGetCodeLengthReadsFromAppConfig(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'code_length', 6)
			->willReturn(8);

		$this->assertSame(8, $this->settings->getCodeLength());
	}

	/** `occ config:app:set` writes past SettingsValidator. */
	public function testGetCodeLengthClampsAValueWrittenPastTheValidator(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'code_length', 6)
			->willReturn(1);

		$this->assertSame(SettingsValidator::MIN_CODE_LENGTH, $this->settings->getCodeLength());
	}

	/**
	 * Without the log line, a value written past the validator is invisible. It is
	 * read many times per request, so it must be said once, not once per read.
	 */
	public function testWarnsOnceAboutAClampedNumberHoweverOftenItIsRead(): void {
		$this->appConfig->method('getValueInt')->willReturn(100000);
		$this->logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('outside the allowed range'));

		$this->settings->getCodeValidMinutes();
		$this->settings->getCodeValidMinutes();
	}

	public function testGetCodeValidMinutesClampsOutOfRange(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'code_valid_minutes', 10)
			->willReturn(100000);

		$this->assertSame(SettingsValidator::MAX_CODE_VALID_MINUTES, $this->settings->getCodeValidMinutes());
	}

	public function testGetResendMinMinutesClampsOutOfRange(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'resend_min_minutes', 1)
			->willReturn(-5);

		$this->assertSame(SettingsValidator::MIN_RESEND_MINUTES, $this->settings->getResendMinMinutes());
	}

	public function testGetEMailSubjectDefaultsToEmpty(): void {
		// The stored value is empty by default; emptiness signals "use default"
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'email_subject', '')
			->willReturn('');

		$this->assertSame('', $this->settings->getEMailSubject());
	}

	/**
	 * Not valid UTF-8 means every /u match in the renderer fails, so nothing would be
	 * substituted and the mail would carry a literal "{code}" — an instance-wide
	 * lockout. Treating it as unset lets the default text through instead.
	 */
	public function testGetEMailTemplateIgnoresTextThatIsNotValidUtf8(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'email_template', '')
			->willReturn("Your code is {code}. Second \xFF paragraph.");

		$this->assertSame('', $this->settings->getEMailTemplate());
	}

	public function testGetEMailSubjectIgnoresTextThatIsNotValidUtf8(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, 'email_subject', '')
			->willReturn("Code {code} \xFF");

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

	public function testGetResendMinMinutesDefaultsToOne(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'resend_min_minutes', 1)
			->willReturn(1);

		$this->assertSame(1, $this->settings->getResendMinMinutes());
	}

	public function testGetResendCooldownSecondsConvertsMinutes(): void {
		$this->appConfig->method('getValueInt')
			->with(Application::APP_ID, 'resend_min_minutes', 1)
			->willReturn(2);

		$this->assertSame(120, $this->settings->getResendCooldownSeconds());
	}

	public function testSetResendMinMinutesWritesToAppConfig(): void {
		$this->appConfig->expects($this->once())
			->method('setValueInt')
			->with(Application::APP_ID, 'resend_min_minutes', 30);

		$this->settings->setResendMinMinutes(30);
	}

	public function testResetToDefaultsDeletesAllKeys(): void {
		$this->appConfig->expects($this->exactly(5))->method('deleteKey');

		$this->settings->resetToDefaults();
	}
}

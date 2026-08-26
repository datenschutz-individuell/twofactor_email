<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Command;

use OCA\TwoFactorEMail\Command\Settings;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsTest extends TestCase {
	private IAppSettings&MockObject $appSettings;

	private CommandTester $tester;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->appSettings = $this->createMock(IAppSettings::class);
		// Valid current values; single tests override where needed
		$this->appSettings->method('getCodeLength')->willReturn(6);
		$this->appSettings->method('getCodeValidMinutes')->willReturn(10);
		$this->appSettings->method('getResendMinMinutes')->willReturn(1);
		$this->appSettings->method('getEMailSubject')->willReturn('');
		$this->appSettings->method('getEMailTemplate')->willReturn('');
		$this->appSettings->method('getDefaultEMailSubject')->willReturn('Login attempt for {user} @ {cloud}');
		$this->appSettings->method('getDefaultEMailBody')->willReturn("{logo}\n\nYour code is {code}.");

		$this->tester = new CommandTester(new Settings($this->appSettings, new SettingsValidator()));
	}

	public function testListsAllSettingsWithoutArguments(): void {
		$exitCode = $this->tester->execute([]);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$display = $this->tester->getDisplay();
		foreach (['code_length', 'code_valid_minutes', 'resend_min_minutes', 'email_subject', 'email_template'] as $key) {
			$this->assertStringContainsString($key, $display);
		}
	}

	public function testShowsSingleValue(): void {
		$exitCode = $this->tester->execute(['key' => 'code_length']);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertSame('6', trim($this->tester->getDisplay()));
	}

	public function testSetsValidIntValue(): void {
		$this->appSettings->expects($this->once())->method('setCodeLength')->with(8);

		$exitCode = $this->tester->execute(['key' => 'code_length', 'value' => '8']);

		$this->assertSame(Command::SUCCESS, $exitCode);
	}

	/**
	 * A stored body can be invalid without anyone having typed it here — the repair
	 * step keeps such a text on purpose. That must not block the other settings.
	 *
	 * @throws Exception
	 */
	public function testSetsASettingEvenWhileAnotherStoredTextIsInvalid(): void {
		$appSettings = $this->createMock(IAppSettings::class);
		$appSettings->method('getCodeLength')->willReturn(6);
		$appSettings->method('getCodeValidMinutes')->willReturn(10);
		$appSettings->method('getResendMinMinutes')->willReturn(1);
		$appSettings->method('getEMailSubject')->willReturn('');
		$appSettings->method('getEMailTemplate')->willReturn('Your code {code}: https://cloud.example/?u={user}');
		$appSettings->expects($this->once())->method('setCodeLength')->with(8);
		$tester = new CommandTester(new Settings($appSettings, new SettingsValidator()));

		$exitCode = $tester->execute(['key' => 'code_length', 'value' => '8']);

		$this->assertSame(Command::SUCCESS, $exitCode);
	}

	/**
	 * Re-setting the same faulty stored text is refused: only leaving the text
	 * alone is exempt, or tooling would confirm a value the app forbids.
	 *
	 * @throws Exception
	 */
	public function testRefusesReSettingTheSameFaultyStoredText(): void {
		$faulty = 'Your code {code}: https://cloud.example/?u={user}';
		$appSettings = $this->createMock(IAppSettings::class);
		$appSettings->method('getCodeLength')->willReturn(6);
		$appSettings->method('getCodeValidMinutes')->willReturn(10);
		$appSettings->method('getResendMinMinutes')->willReturn(1);
		$appSettings->method('getEMailSubject')->willReturn('');
		$appSettings->method('getEMailTemplate')->willReturn($faulty);
		$appSettings->expects($this->never())->method('setEMailTemplate');
		$tester = new CommandTester(new Settings($appSettings, new SettingsValidator()));

		$exitCode = $tester->execute(['key' => 'email_template', 'value' => $faulty]);

		$this->assertSame(Command::INVALID, $exitCode);
	}

	public function testSetsValidTemplate(): void {
		$this->appSettings->expects($this->once())->method('setEMailTemplate')->with('Use {code} now');

		$exitCode = $this->tester->execute(['key' => 'email_template', 'value' => 'Use {code} now']);

		$this->assertSame(Command::SUCCESS, $exitCode);
	}

	public function testRejectsOutOfRangeValue(): void {
		$this->appSettings->expects($this->never())->method('setCodeLength');

		$exitCode = $this->tester->execute(['key' => 'code_length', 'value' => '3']);

		$this->assertSame(Command::INVALID, $exitCode);
		$this->assertStringContainsString('between 4 and 16', $this->tester->getDisplay());
	}

	public function testRejectsNonIntegerValueForIntSetting(): void {
		$this->appSettings->expects($this->never())->method('setCodeLength');

		$exitCode = $this->tester->execute(['key' => 'code_length', 'value' => 'six']);

		$this->assertSame(Command::INVALID, $exitCode);
		$this->assertStringContainsString('integer', $this->tester->getDisplay());
	}

	public function testRejectsTemplateWithoutCodePlaceholder(): void {
		$this->appSettings->expects($this->never())->method('setEMailTemplate');

		$exitCode = $this->tester->execute(['key' => 'email_template', 'value' => 'no placeholder here']);

		$this->assertSame(Command::INVALID, $exitCode);
		$this->assertStringContainsString('{code}', $this->tester->getDisplay());
	}

	public function testRejectsUnknownKey(): void {
		$exitCode = $this->tester->execute(['key' => 'no_such_setting']);

		$this->assertSame(Command::INVALID, $exitCode);
		$this->assertStringContainsString('Unknown setting', $this->tester->getDisplay());
	}

	public function testResetResetsAllSettings(): void {
		$this->appSettings->expects($this->once())->method('resetToDefaults');

		$exitCode = $this->tester->execute(['--reset' => true]);

		$this->assertSame(Command::SUCCESS, $exitCode);
	}

	public function testResetRejectsCombinationWithKey(): void {
		$this->appSettings->expects($this->never())->method('resetToDefaults');

		$exitCode = $this->tester->execute(['key' => 'code_length', '--reset' => true]);

		$this->assertSame(Command::INVALID, $exitCode);
	}
}

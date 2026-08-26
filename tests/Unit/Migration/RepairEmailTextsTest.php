<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Migration;

use OCA\TwoFactorEMail\Migration\RepairEmailTexts;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RepairEmailTextsTest extends TestCase {
	private IAppConfig&MockObject $appConfig;

	private IOutput&MockObject $output;

	private RepairEmailTexts $step;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->output = $this->createMock(IOutput::class);
		$this->step = new RepairEmailTexts($this->appConfig);
	}

	private function stored(string $subject, string $template): void {
		$this->appConfig->method('getValueString')->willReturnMap([
			['twofactor_email', 'email_subject', '', false, $subject],
			['twofactor_email', 'email_template', '', false, $template],
		]);
		$this->numbers();
	}

	/** Valid numbers unless a test says otherwise, so they report nothing. */
	private function numbers(int $codeLength = 6, int $validMinutes = 10, int $resendMinutes = 1): void {
		$this->appConfig->method('getValueInt')->willReturnMap([
			['twofactor_email', 'code_length', 6, false, $codeLength],
			['twofactor_email', 'code_valid_minutes', 10, false, $validMinutes],
			['twofactor_email', 'resend_min_minutes', 1, false, $resendMinutes],
		]);
	}

	/** Corrected on every read, so this output is the only place it is visible. */
	public function testReportsAStoredNumberOutsideItsRange(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->numbers(codeLength: 2);
		$this->output->expects($this->once())
			->method('warning')
			->with($this->stringContains('code length (2) is outside the allowed range'));

		$this->step->run($this->output);
	}

	/** Two defects, two repairs — the admin must hear about both at once. */
	public function testReportsEveryConditionThatAppliesToOneText(): void {
		$seen = [];
		$this->output->method('warning')->willReturnCallback(function (string $m) use (&$seen): void {
			$seen[] = $m;
		});

		$this->stored('', 'Log in at https://cloud.example/?u={user} — no code here');
		$this->step->run($this->output);

		$this->assertCount(2, $seen);
		$this->assertStringContainsString('does not contain {code}', $seen[0]);
		$this->assertStringContainsString('inside a web address', $seen[1]);
	}

	/**
	 * The code still arrives — EMailSender falls back to the default text — and
	 * throwing the admin's text away is irrecoverable. Report, keep.
	 */
	public function testReportsButKeepsATextWithAPlaceholderInAUrl(): void {
		$this->appConfig->expects($this->never())->method('deleteKey');
		$this->output->expects($this->once())
			->method('warning')
			->with($this->stringContains('inside a web address'));

		$this->stored('', 'Log in at https://cloud.example/?code={code}');
		$this->step->run($this->output);
	}

	/** No mail carries a code, and every settings change is refused until it is fixed. */
	public function testReportsABodyWithoutAnyCode(): void {
		$this->appConfig->expects($this->never())->method('deleteKey');
		$this->output->expects($this->once())
			->method('warning')
			->with($this->stringContains('does not contain {code}'));

		$this->stored('', 'Log in at https://cloud.example/security');
		$this->step->run($this->output);
	}

	/** Repaired once here, instead of a warning on every mail for good. */
	public function testResetsTextThatIsNotValidUtf8(): void {
		$this->appConfig->expects($this->once())
			->method('deleteKey')
			->with('twofactor_email', 'email_subject');

		$this->stored("Code {code} \xFF", 'Your code is {code}.');
		$this->step->run($this->output);
	}

	/** It blocks every settings change, so the upgrade output has to name it. */
	public function testReportsASubjectWithALineBreak(): void {
		$this->appConfig->expects($this->never())->method('deleteKey');
		$this->output->expects($this->once())
			->method('warning')
			->with($this->stringContains('contains a line break'));

		$this->stored("Code {code}\r\nBcc: spy@example.com", 'Your code is {code}.');
		$this->step->run($this->output);
	}

	public function testLeavesAnUnaffectedCustomizationAlone(): void {
		$this->appConfig->expects($this->never())->method('deleteKey');
		$this->output->expects($this->never())->method('warning');

		$this->stored('Your code for {cloud}', "{user}, your code is {code}.\nHelp: https://cloud.example/help");
		$this->step->run($this->output);
	}

}

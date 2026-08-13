<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Service\SettingsValidator;
use PHPUnit\Framework\TestCase;

/** Own test file since the placeholder-in-URL rule decides whether a code can leak. */
final class SettingsValidatorTest extends TestCase {
	private SettingsValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new SettingsValidator();
	}

	/**
	 * @param string $template the email body to validate
	 */
	private function validateTemplate(string $template): array {
		return $this->validator->validate(6, 10, 1, 'Subject', $template);
	}

	public function testAcceptsATemplateThatKeepsItsPlaceholdersOutOfEveryUrl(): void {
		$this->assertSame([], $this->validateTemplate('Your code is {code}.'));
		$this->assertSame([], $this->validateTemplate('Your code is {code}. Help: https://example.com/help'));
		$this->assertSame([], $this->validateTemplate("{user}, your code is {code}.\nhttps://example.com/"));
	}

	/**
	 * Link scanners fetch such a URL unattended, so the code would leave the message.
	 * The check on the finished mail stops every scheme and a bare "www.", so the
	 * admin has to hear about those too — otherwise the setting saves and never works.
	 */
	public function testRejectsAPlaceholderInAnAddressThatIsNotLinkedItself(): void {
		$rejected = ['eMailTemplate' => 'email-template-placeholder-in-url'];

		$this->assertSame($rejected, $this->validateTemplate('Your code {code} at www.evil.example/{code}'));
		$this->assertSame($rejected, $this->validateTemplate('Code {code} ftp://evil.example/{code}'));
	}

	/**
	 * A placeholder before the address is inside the same run, so the check on the
	 * finished mail would stop it — and then it has to be refused here as well.
	 */
	public function testRejectsAPlaceholderThatPrecedesAnAddressWithoutASpace(): void {
		$this->assertSame(
			['eMailTemplate' => 'email-template-placeholder-in-url'],
			$this->validateTemplate('Enter [{code}](https://example.com/verify)'),
		);
	}

	/** {logo} expands to an image tag or to nothing, so it hands no data to anyone. */
	public function testAcceptsTheLogoTokenInsideAUrl(): void {
		$this->assertSame([], $this->validateTemplate('Your code is {code}. https://example.com/{logo}'));
	}

	/** The encoding is the defect; the length is a consequence and must not mask it. */
	public function testNamesTheEncodingBeforeTheLengthForATextThatIsBoth(): void {
		$this->assertSame(
			['eMailSubject' => 'email-subject-not-valid-text'],
			$this->validator->validate(6, 10, 1, str_repeat("\xFF", 300), 'Your code is {code}.'),
		);
	}

	/** AppSettings discards such a text on read, so saving it would report a lie. */
	public function testRejectsTextThatIsNotValidUtf8(): void {
		$this->assertSame(
			['eMailTemplate' => 'email-template-not-valid-text'],
			$this->validateTemplate("Your code is {code}. \xFF"),
		);
	}

	public function testRejectsAnyPlaceholderInsideAUrl(): void {
		$rejected = ['eMailTemplate' => 'email-template-placeholder-in-url'];

		$this->assertSame($rejected, $this->validateTemplate('Confirm here: https://example.com/{code}'));
		// Not the code, but still the user's data handed to whoever owns the URL.
		$this->assertSame($rejected, $this->validateTemplate('Your code is {code}. See https://example.com/?u={user}'));
		$this->assertSame($rejected, $this->validateTemplate('Your code is {code}. https://example.com/a?b=1&c={code}'));
	}

	/** Same placeholders, and the subject travels further. */
	public function testRejectsAPlaceholderInsideAUrlInTheSubject(): void {
		$this->assertSame(
			['eMailSubject' => 'email-subject-placeholder-in-url'],
			$this->validator->validate(6, 10, 1, 'Code https://evil.example/{code}', 'Your code is {code}.'),
		);
	}

	/** Header injection outranks the URL rule and must not be hidden by it. */
	public function testHeaderInjectionInTheSubjectOutranksTheUrlRule(): void {
		$this->assertSame(
			['eMailSubject' => 'email-subject-must-be-single-line'],
			$this->validator->validate(6, 10, 1, "Code\r\nBcc: spy@example.com https://x/{code}", 'Your code is {code}.'),
		);
	}

	public function testAMissingCodePlaceholderOutranksTheUrlRule(): void {
		$this->assertSame(
			['eMailTemplate' => 'email-code-placeholder-missing'],
			$this->validateTemplate('See https://evil.example/{user} for details.'),
		);
	}

	public function testStillRequiresTheCodePlaceholder(): void {
		$this->assertSame(
			['eMailTemplate' => 'email-code-placeholder-missing'],
			$this->validateTemplate('No code in here.'),
		);
	}

	/** The stated limits are characters; a non-ASCII text must not hit them earlier. */
	public function testCountsCharactersNotBytesForTheLengthLimits(): void {
		$subject = str_repeat('ä', SettingsValidator::MAX_EMAIL_SUBJECT_LENGTH);

		$this->assertSame([], $this->validator->validate(6, 10, 1, $subject, 'Your code is {code}.'));
	}

	/**
	 * The baseline exists for the occ command, which sets one key at a time: a stored
	 * text that is faulty must not stop an admin from changing the code length. The
	 * web form passes no baseline, because it submits every field at once. Line
	 * endings are ignored in the comparison, since occ and a browser disagree on them.
	 */
	public function testDropsThePlaceholderInUrlErrorForAnUnchangedText(): void {
		$stored = "Your code {code}:\r\nhttps://cloud.example/?u={user}";

		$this->assertSame([], $this->validator->validate(6, 10, 1, '', $stored, '', $stored));
		$this->assertSame([], $this->validator->validate(6, 10, 1, '', str_replace("\r\n", "\n", $stored), '', $stored));
	}

	/** Every other error blocks even for an unchanged text. */
	public function testKeepsEveryOtherErrorForAnUnchangedText(): void {
		$stored = 'no code here';

		$this->assertSame(
			['eMailTemplate' => 'email-code-placeholder-missing'],
			$this->validator->validate(6, 10, 1, '', $stored, '', $stored),
		);
	}
}

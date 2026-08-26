<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Mail;

use OCA\TwoFactorEMail\Mail\LinkScanner;
use PHPUnit\Framework\TestCase;

final class LinkScannerTest extends TestCase {
	private const CODE = '123456';

	/** A mail whose subject carries the given text and whose body is harmless. */
	private static function inSubject(string $subject): bool {
		return LinkScanner::couldLeakCode($subject, [['Your code is here', 'Your code is here']], self::CODE);
	}

	/** A mail with a harmless subject and one body paragraph. */
	private static function inBody(string $html, string|false $plain): bool {
		return LinkScanner::couldLeakCode('Login attempt', [[$html, $plain]], self::CODE);
	}

	/**
	 * The case no template check can see: the address does not exist until an
	 * inserted value builds it around the code.
	 */
	public function testSeesACodeInAnAddressThatAValueBuilt(): void {
		$this->assertTrue(self::inSubject('Hi https://evil.example/?c=123456 bye'));
		$this->assertTrue(self::inSubject('www.evil.example/123456'));
		// An invisible separator does not end the address here either
		$this->assertTrue(self::inSubject("https://evil.example/\u{00A0}123456"));
	}

	/** A code next to an address, but not in it, is not reported. */
	public function testDoesNotReportACodeOutsideEveryAddress(): void {
		$this->assertFalse(self::inSubject('See https://cloud.example/help — code 123456'));
	}

	/** A link target is read back the way it was written, so a code in it is fetched. */
	public function testSeesACodeInALinkTarget(): void {
		$this->assertTrue(self::inBody('Click <a href="https://evil.example/?c=123456">here</a>', 'Click here'));
	}

	/**
	 * A client that links the rendered text does not see the markup between the
	 * words, so neither does this check.
	 */
	public function testSeesACodeThatOnlyMarkupSeparatesFromTheAddress(): void {
		$this->assertTrue(self::inBody('https://evil.example/?c=<strong>123456</strong>', false));
	}

	/**
	 * A client with images blocked renders the alt text in the flow, in place of the
	 * image — and the alt text is the instance name, which an admin sets in the web
	 * UI. So it can stand directly in front of the code without any separator.
	 */
	public function testSeesACodeBehindAnAddressInTheLogoAltText(): void {
		$this->assertTrue(self::inBody(
			'<img src="https://cloud.example/logo.png" alt="https://evil.example/?c=" style="max-width:250px">'
			. '<strong style="font-family:monospace">123456</strong>',
			false,
		));
	}

	/** A line break separates the code from the address for every reader. */
	public function testDoesNotReportACodeOnItsOwnLine(): void {
		$this->assertFalse(self::inBody(
			'Your code is 123456:<br>https://cloud.example/',
			"Your code is 123456:\nhttps://cloud.example/",
		));
	}

	/** Without a code there is nothing to leak — an empty needle matches everywhere. */
	public function testReportsNothingWithoutACode(): void {
		$this->assertFalse(LinkScanner::couldLeakCode('https://cloud.example/123456', [], ''));
	}

	public function testReportsAPlaceholderInsideAnAddress(): void {
		$this->assertTrue(LinkScanner::hasPlaceholderInUrl('Open https://cloud.example/?code={code} now'));
		$this->assertTrue(LinkScanner::hasPlaceholderInUrl('www.cloud.example/u/{user}'));
	}

	/** {logo} inserts no value, so it hands no data to whoever owns the address. */
	public function testAcceptsTheLogoTokenInsideAnAddress(): void {
		$this->assertFalse(LinkScanner::hasPlaceholderInUrl('https://cloud.example/{logo}'));
	}

	public function testAcceptsAPlaceholderOutsideEveryAddress(): void {
		$this->assertFalse(LinkScanner::hasPlaceholderInUrl("Your code is {code}.\nSee https://cloud.example/help"));
	}
}

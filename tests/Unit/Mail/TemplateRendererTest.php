<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Mail;

use OCA\TwoFactorEMail\Mail\LinkScanner;
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TemplateRendererTest extends TestCase {
	private const STRONG = '<strong style="font-family:monospace">%s</strong>';

	private Defaults&MockObject $defaults;

	private IURLGenerator&MockObject $urlGenerator;

	private IAppSettings&MockObject $appSettings;

	private IUser&MockObject $user;

	private TemplateRenderer $renderer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->defaults = $this->createMock(Defaults::class);
		$this->defaults->method('getName')->willReturn('Example Cloud');
		$this->defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->urlGenerator->method('getAbsoluteURL')
			->with('/themes/logo.png')
			->willReturn('https://cloud.example/themes/logo.png');

		$this->appSettings = $this->createMock(IAppSettings::class);
		$this->appSettings->method('getCodeValidMinutes')->willReturn(10);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getDisplayName')->willReturn('Jane Doe');

		$this->renderer = new TemplateRenderer($this->defaults, $this->urlGenerator, $this->appSettings);
	}

	private function strong(string $value): string {
		return sprintf(self::STRONG, $value);
	}

	public function testSubjectInsertsPlaceholdersBare(): void {
		$this->assertSame(
			'Code 123456 for Jane Doe @ Example Cloud (10 min)',
			$this->renderer->renderSubject('Code {code} for {user} @ {cloud} ({validity} min)', $this->user, '123456'),
		);
	}

	/**
	 * @throws Exception
	 */
	public function testSubjectStaysSingleLineDespiteLineBreaksInDisplayName(): void {
		// A line break in a mail header must never reach the mailer (header injection)
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn("Jane\r\nBcc: spy@example.com");

		$this->assertSame(
			'Login of Jane Bcc: spy@example.com',
			$this->renderer->renderSubject('Login of {user}', $user, '123456'),
		);
	}

	public function testBodyStartsWithTheSpacingParagraph(): void {
		$this->assertSame(
			[['&nbsp;', false]],
			$this->renderer->renderBody('', $this->user, '123456'),
		);
	}

	public function testBodyRendersParagraphsLineBreaksAndPlaceholders(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				// Placeholders are bold and monospace in the HTML variant only,
				// {code} gets markers in the plain text variant
				'Hello ' . $this->strong('Jane Doe') . ',<br>your code: ' . $this->strong('123456'),
				"Hello Jane Doe,\nyour code: >>> 123456 <<<",
			],
			[
				'Use it on ' . $this->strong('Example Cloud') . ' within ' . $this->strong('10') . ' minutes.',
				'Use it on Example Cloud within 10 minutes.',
			],
		], $this->renderer->renderBody(
			"Hello {user},\nyour code: {code}\n\nUse it on {cloud} within {validity} minutes.",
			$this->user,
			'123456',
		));
	}

	/** The pattern runs to the next whitespace, so a quoted URL must not keep the quote. */
	public function testBodyDoesNotLinkTrailingQuotesOrBrackets(): void {
		$rendered = $this->renderer->renderBody(
			'Reset it at "https://cloud.example/reset" or see <https://cloud.example/help>.',
			$this->user,
			'123456',
		);

		$this->assertStringContainsString('href="https://cloud.example/reset"', $rendered[1][0]);
		$this->assertStringContainsString('href="https://cloud.example/help"', $rendered[1][0]);
	}

	/**
	 * The instance name is inserted, and inserted values must not smuggle in
	 * placeholders — also inside the logo's alt attribute.
	 *
	 * @throws Exception
	 */
	public function testAPlaceholderInTheInstanceNameStaysLiteralInTheLogoAltText(): void {
		$defaults = $this->createMock(Defaults::class);
		$defaults->method('getName')->willReturn('{user} Cloud');
		$defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');
		$renderer = new TemplateRenderer($defaults, $this->urlGenerator, $this->appSettings);

		$rendered = $renderer->renderBody('{logo}', $this->user, '123456');

		$this->assertStringContainsString('alt="{user} Cloud"', $rendered[1][0]);
		$this->assertStringNotContainsString('Jane', $rendered[1][0]);
	}

	/** A lone CR breaks the line like CRLF and LF, as the subject already treats it. */
	public function testBodyTurnsALoneCarriageReturnIntoALineBreak(): void {
		$rendered = $this->renderer->renderBody("line1\rline2", $this->user, '123456');

		$this->assertSame('line1<br>line2', $rendered[1][0]);
	}

	public function testBodyAutoLinksUrls(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				// URLs are auto-linked with themselves as the visible text
				'Visit <a href="https://example.org/help?a=1&amp;b=2">https://example.org/help?a=1&amp;b=2</a> for details.',
				'Visit https://example.org/help?a=1&b=2 for details.',
			],
			[
				// Trailing sentence punctuation is not part of the URL
				'More info (<a href="https://example.org/path">https://example.org/path</a>).',
				'More info (https://example.org/path).',
			],
		], $this->renderer->renderBody(
			"Visit https://example.org/help?a=1&b=2 for details.\n\nMore info (https://example.org/path).",
			$this->user,
			'123456',
		));
	}

	/**
	 * A placeholder substituted here but missing from the constant would be invisible
	 * to both settings checks; one in the constant that is never substituted would
	 * refuse a text for no reason. So the two lists must match exactly.
	 */
	public function testTheScannerKnowsEveryPlaceholderThatIsSubstituted(): void {
		$method = new \ReflectionMethod(TemplateRenderer::class, 'placeholderValues');
		$substituted = array_keys($method->invoke($this->renderer, $this->user, '123456'));

		$this->assertEqualsCanonicalizing(
			LinkScanner::VALUE_PLACEHOLDERS,
			$substituted,
			'LinkScanner::VALUE_PLACEHOLDERS and TemplateRenderer::placeholderValues() have drifted apart',
		);
	}

	public function testBodyEscapesRawHtml(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				'&lt;b&gt;Hi&lt;/b&gt; &amp; Co',
				'<b>Hi</b> & Co',
			],
		], $this->renderer->renderBody('<b>Hi</b> & Co', $this->user, '123456'));
	}

	/** {logo} has no text form, so the subject drops it like the plain text does. */
	public function testSubjectDropsTheLogoToken(): void {
		$this->assertSame(
			'Code for Jane Doe',
			$this->renderer->renderSubject('Code {logo}for {user}', $this->user, '123456'),
		);
	}

	/**
	 * A placeholder in a web address is substituted like any other — bare, so no
	 * markup reaches the attribute. Whether the result may be sent is decided once,
	 * on the finished mail, by EMailSender.
	 */
	public function testBodySubstitutesInsideAUrlAndInsertsTheValueBare(): void {
		$rendered = $this->renderer->renderBody('See https://cloud.example/u/{user} please.', $this->user, '123456');

		$this->assertStringContainsString('href="https://cloud.example/u/Jane Doe"', $rendered[1][0]);
	}

	public function testBodyRendersTheLogoTokenInHtmlOnly(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				// Logo-only paragraph: no plain text counterpart at all
				'<img src="https://cloud.example/themes/logo.png" alt="Example Cloud" style="max-width:min(250px, 20%);max-height:250px">',
				false,
			],
			[
				'Your code: ' . $this->strong('123456'),
				'Your code: >>> 123456 <<<',
			],
		], $this->renderer->renderBody("{logo}\n\nYour code: {code}", $this->user, '123456'));
	}
}

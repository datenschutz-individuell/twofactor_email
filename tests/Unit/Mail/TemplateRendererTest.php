<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Mail;

use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase {
	private const STRONG = '<strong style="font-family:monospace">%s</strong>';

	private IUser&MockObject $user;

	private TemplateRenderer $renderer;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$defaults = $this->createMock(Defaults::class);
		$defaults->method('getName')->willReturn('Example Cloud');
		$defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('getAbsoluteURL')
			->with('/themes/logo.png')
			->willReturn('https://cloud.example/themes/logo.png');

		$appSettings = $this->createMock(IAppSettings::class);
		$appSettings->method('getCodeValidMinutes')->willReturn(10);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getDisplayName')->willReturn('Jane Doe');

		$this->renderer = new TemplateRenderer($defaults, $urlGenerator, $appSettings);
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

	public function testBodyEscapesRawHtml(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				'&lt;b&gt;Hi&lt;/b&gt; &amp; Co',
				'<b>Hi</b> & Co',
			],
		], $this->renderer->renderBody('<b>Hi</b> & Co', $this->user, '123456'));
	}

	public function testBodyRendersTheLogoTokenInHtmlOnly(): void {
		$this->assertSame([
			['&nbsp;', false],
			[
				// Logo-only paragraph: no plain text counterpart at all
				'<img src="https://cloud.example/themes/logo.png" alt="Example Cloud" style="max-width:250px;max-width:min(250px, 20%);max-height:250px">',
				false,
			],
			[
				'Your code: ' . $this->strong('123456'),
				'Your code: >>> 123456 <<<',
			],
		], $this->renderer->renderBody("{logo}\n\nYour code: {code}", $this->user, '123456'));
	}
}

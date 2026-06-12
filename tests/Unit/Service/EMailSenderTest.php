<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Service\AppSettingsDefaults;
use OCA\TwoFactorEMail\Service\EMailSender;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EMailSenderTest extends TestCase {
	private IMailer&MockObject $mailer;
	private Defaults&MockObject $defaults;
	private IURLGenerator&MockObject $urlGenerator;
	private IAppSettings&MockObject $appSettings;
	private IEMailTemplate&MockObject $template;

	private EMailSender $sender;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mailer = $this->createMock(IMailer::class);
		$this->defaults = $this->createMock(Defaults::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->appSettings = $this->createMock(IAppSettings::class);
		$this->template = $this->createMock(IEMailTemplate::class);

		$this->defaults->method('getName')->willReturn('Example Cloud');
		$this->appSettings->method('getCodeValidMinutes')->willReturn(10);

		// AppSettingsDefaults is final, so use the real class with a pass-through IL10N
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, $parameters = []) => vsprintf($text, (array)$parameters),
		);

		$this->sender = new EMailSender(
			$this->createMock(LoggerInterface::class),
			$this->mailer,
			$this->defaults,
			$this->urlGenerator,
			$this->appSettings,
			new AppSettingsDefaults($l10n),
		);
	}

	/**
	 * @throws Exception
	 */
	private function mockUser(?string $email): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn($email);
		$user->method('getDisplayName')->willReturn('Jane Doe');
		return $user;
	}

	private function expectMailWithTemplate(): void {
		$message = $this->createMock(IMessage::class);
		$this->mailer->method('createEMailTemplate')->willReturn($this->template);
		$this->mailer->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())->method('send');
	}

	public function testThrowsWhenNoEmailIsSet(): void {
		$this->expectException(EMailNotSet::class);

		$this->sender->sendChallengeEMail($this->mockUser(null), '123456');
	}

	/**
	 * Collects all addBodyText calls as [html, plain] pairs.
	 *
	 * @param list<array{string, string}> $calls
	 */
	private function collectBodyTexts(array &$calls): void {
		$this->template->method('addBodyText')
			->willReturnCallback(static function (string $html, $plain) use (&$calls): void {
				$calls[] = [$html, $plain];
			});
	}

	public function testUsesLocalizedDefaultsWhenSettingsAreEmpty(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('');
		$this->appSettings->method('getEMailTemplate')->willReturn('');
		$this->appSettings->method('getEMailFooter')->willReturn('');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Login attempt for Jane Doe @ Example Cloud');
		$this->template->expects($this->never())
			->method('addHeading');
		$this->template->expects($this->once())
			->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		// Empty footer setting means: standard theming footer (no argument)
		$this->template->expects($this->once())
			->method('addFooter')
			->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		// The blank line in the default body becomes a paragraph break
		$this->assertSame([
			[
				'Your two-factor authentication code is: 123456',
				'Your two-factor authentication code is: 123456',
			],
			[
				'If you tried to login, please enter that code on Example Cloud. '
				. 'If you did not, somebody else did and knows your email address '
				. 'or username – and your password!',
				'If you tried to login, please enter that code on Example Cloud. '
				. 'If you did not, somebody else did and knows your email address '
				. 'or username – and your password!',
			],
		], $bodyTexts);
	}

	public function testUsesCustomTemplatesAndReplacesAllPlaceholders(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('Code {code} for {user}');
		$this->appSettings->method('getEMailTemplate')->willReturn('Use {code} on {cloud} within {validity} minutes.');
		$this->appSettings->method('getEMailFooter')->willReturn('Mail by {cloud}');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Code 123456 for Jane Doe');
		// A customized body controls the logo itself — no automatic header
		$this->template->expects($this->never())
			->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		$this->template->expects($this->once())
			->method('addFooter')
			->with('Mail by Example Cloud');

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([[
			'Use 123456 on Example Cloud within 10 minutes.',
			'Use 123456 on Example Cloud within 10 minutes.',
		]], $bodyTexts);
	}

	public function testRendersParagraphsLineBreaksAndLinks(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('');
		$this->appSettings->method('getEMailTemplate')->willReturn(
			"Hello {user},\nyour code: {code}\n\n"
			. "Visit [the help page](https://example.org/help?a=1&b=2) for details.\n\n"
			. "[bad](javascript:alert(1))\n\n"
			. '<b>Hi</b> & Co'
		);
		$this->appSettings->method('getEMailFooter')->willReturn("Line1\n[Site](https://example.org)");

		$this->expectMailWithTemplate();
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		// Footer: line break becomes <br>, links are shown as "Text (URL)"
		$this->template->expects($this->once())
			->method('addFooter')
			->with('Line1<br>Site (https://example.org)');

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			[
				'Hello Jane Doe,<br>your code: 123456',
				"Hello Jane Doe,\nyour code: 123456",
			],
			[
				'Visit <a href="https://example.org/help?a=1&amp;b=2">the help page</a> for details.',
				'Visit the help page (https://example.org/help?a=1&b=2) for details.',
			],
			[
				// Disallowed link scheme: the markup stays literal in both variants
				'[bad](javascript:alert(1))',
				'[bad](javascript:alert(1))',
			],
			[
				// Raw HTML is escaped in the HTML variant
				'&lt;b&gt;Hi&lt;/b&gt; &amp; Co',
				'<b>Hi</b> & Co',
			],
		], $bodyTexts);
	}

	public function testLogoTokenAndEmbeddedImages(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('');
		$this->appSettings->method('getEMailTemplate')->willReturn(
			"{logo}\n\n"
			. "![Chart of the week](https://example.org/chart.png)\n\n"
			. '![insecure](http://example.org/x.png)'
		);
		$this->appSettings->method('getEMailFooter')->willReturn('');
		$this->defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');
		$this->urlGenerator->method('getAbsoluteURL')
			->with('/themes/logo.png')
			->willReturn('https://cloud.example/themes/logo.png');

		$this->expectMailWithTemplate();
		// A customized body controls the logo itself — no automatic header
		$this->template->expects($this->never())
			->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			[
				// Logo-only paragraph: no plain text counterpart at all
				'<img src="https://cloud.example/themes/logo.png" alt="Example Cloud" style="max-width:100%">',
				false,
			],
			[
				'<img src="https://example.org/chart.png" alt="Chart of the week" style="max-width:100%">',
				'Chart of the week (https://example.org/chart.png)',
			],
			[
				// Images must use https — anything else stays literal
				'![insecure](http://example.org/x.png)',
				'![insecure](http://example.org/x.png)',
			],
		], $bodyTexts);
	}
}

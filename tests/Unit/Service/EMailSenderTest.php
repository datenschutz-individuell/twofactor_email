<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
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

		// TemplateRenderer and AppSettingsDefaults are final — use the real
		// classes, so these tests cover the full rendering pipeline
		$this->sender = new EMailSender(
			$this->createMock(LoggerInterface::class),
			$this->mailer,
			$this->appSettings,
			new AppSettingsDefaults($l10n),
			new TemplateRenderer($this->defaults, $this->urlGenerator, $this->appSettings),
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
		$this->defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');
		$this->urlGenerator->method('getAbsoluteURL')
			->with('/themes/logo.png')
			->willReturn('https://cloud.example/themes/logo.png');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Login attempt for Jane Doe @ Example Cloud');
		// The logo comes solely from the {logo} token in the default body
		$this->template->expects($this->never())
			->method('addHeader');
		$this->template->expects($this->never())
			->method('addHeading');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		// The standard theming footer is always used (no argument)
		$this->template->expects($this->once())
			->method('addFooter')
			->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			[
				// Spacing paragraph (no logo header anymore)
				'&nbsp;',
				false,
			],
			[
				// The default body starts with the {logo} token
				'<img src="https://cloud.example/themes/logo.png" alt="Example Cloud" style="max-width:250px;max-width:min(250px, 20%);max-height:250px">',
				false,
			],
			[
				'Your two-factor authentication code for <strong style="font-family:monospace">Example Cloud</strong> is:',
				'Your two-factor authentication code for Example Cloud is:',
			],
			[
				'<strong style="font-family:monospace">123456</strong>',
				'>>> 123456 <<<',
			],
			[
				'The code is valid for <strong style="font-family:monospace">10</strong> minutes. '
				. 'If you did not try to log in, somebody else knows your username and your password '
				. '— change your password and inform your administrator.',
				'The code is valid for 10 minutes. '
				. 'If you did not try to log in, somebody else knows your username and your password '
				. '— change your password and inform your administrator.',
			],
		], $bodyTexts);
	}

	public function testUsesCustomTemplatesAndReplacesAllPlaceholders(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('Code {code} for {user}');
		$this->appSettings->method('getEMailTemplate')->willReturn('Use {code} on {cloud} within {validity} minutes.');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Code 123456 for Jane Doe');
		// A customized body controls the logo itself — no automatic header
		$this->template->expects($this->never())
			->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		// The standard theming footer is always used (no argument)
		$this->template->expects($this->once())
			->method('addFooter')
			->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			[
				// Spacing paragraph replacing the omitted logo header
				'&nbsp;',
				false,
			],
			[
				'Use <strong style="font-family:monospace">123456</strong> on <strong style="font-family:monospace">Example Cloud</strong> within <strong style="font-family:monospace">10</strong> minutes.',
				'Use >>> 123456 <<< on Example Cloud within 10 minutes.',
			],
		], $bodyTexts);
	}
}

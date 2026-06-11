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
			->method('addBodyText')
			->with("Your two-factor authentication code is: 123456\n\n"
				. 'If you tried to login, please enter that code on Example Cloud. '
				. 'If you did not, somebody else did and knows your email address '
				. 'or username – and your password!');
		// Empty footer setting means: standard theming footer (no argument)
		$this->template->expects($this->once())
			->method('addFooter')
			->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');
	}

	public function testUsesCustomTemplatesAndReplacesAllPlaceholders(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('Code {code} for {user}');
		$this->appSettings->method('getEMailTemplate')->willReturn('Use {code} on {cloud} within {validity} minutes.');
		$this->appSettings->method('getEMailFooter')->willReturn('Mail by {cloud}');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Code 123456 for Jane Doe');
		$this->template->expects($this->once())
			->method('addBodyText')
			->with('Use 123456 on Example Cloud within 10 minutes.');
		$this->template->expects($this->once())
			->method('addFooter')
			->with('Mail by Example Cloud');

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');
	}
}

<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Exception\SendRateLimited;
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCA\TwoFactorEMail\Service\EMailSender;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\DB\Exception as DbException;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
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
	private LoggerInterface&MockObject $logger;
	private ILimiter&MockObject $limiter;

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
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->limiter = $this->createMock(ILimiter::class);

		$this->defaults->method('getName')->willReturn('Example Cloud');
		$this->appSettings->method('getCodeValidMinutes')->willReturn(10);

		// TemplateRenderer is final — use the real class so these tests cover
		// the full rendering pipeline. The localized default texts come from
		// the mocked IAppSettings (their real content is tested in AppSettingsTest).
		$this->sender = new EMailSender(
			$this->logger,
			$this->mailer,
			$this->appSettings,
			new TemplateRenderer($this->defaults, $this->urlGenerator, $this->appSettings),
			$this->limiter,
		);
	}

	private function rateLimitExceeded(): IRateLimitExceededException {
		return new class extends \RuntimeException implements IRateLimitExceededException {
		};
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

	/**
	 * @throws Exception
	 */
	private function mockMailer(): void {
		$this->mailer->method('createEMailTemplate')->willReturn($this->template);
		$this->mailer->method('createMessage')->willReturn($this->createMock(IMessage::class));
	}

	/**
	 * @throws Exception
	 */
	private function expectMailWithTemplate(): void {
		$this->mockMailer();
		$this->mailer->expects($this->once())->method('send');
	}

	/**
	 * @throws SendEMailFailed
	 * @throws Exception
	 */
	public function testThrowsWhenNoEmailIsSet(): void {
		$this->expectException(EMailNotSet::class);

		$this->sender->sendChallengeEMail($this->mockUser(null), '123456');
	}

	/**
	 * Collects all addBodyText calls as [html, plain] pairs.
	 *
	 * @param list<array{string, string|false}> $calls
	 */
	private function collectBodyTexts(array &$calls): void {
		$this->template->method('addBodyText')
			->willReturnCallback(static function (string $html, $plain) use (&$calls): void {
				$calls[] = [$html, $plain];
			});
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testFallsBackToDefaultsWhenSettingsAreEmpty(): void {
		// Empty stored values → the localized defaults from IAppSettings are used
		$this->appSettings->method('getEMailSubject')->willReturn('');
		$this->appSettings->method('getEMailTemplate')->willReturn('');
		$this->appSettings->method('getDefaultEMailSubject')->willReturn('Default for {user}');
		$this->appSettings->method('getDefaultEMailBody')->willReturn("{logo}\n\nDefault code: {code}");
		$this->defaults->method('getLogo')->with(false)->willReturn('/themes/logo.png');
		$this->urlGenerator->method('getAbsoluteURL')
			->with('/themes/logo.png')
			->willReturn('https://cloud.example/themes/logo.png');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Default for Jane Doe');
		// The logo comes solely from the {logo} token in the (default) body
		$this->template->expects($this->never())->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		// The standard theming footer is always used (no argument)
		$this->template->expects($this->once())->method('addFooter')->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			['&nbsp;', false],
			[
				'<img src="https://cloud.example/themes/logo.png" alt="Example Cloud" style="max-width:min(250px, 20%);max-height:250px">',
				false,
			],
			[
				'Default code: <strong style="font-family:monospace">123456</strong>',
				'Default code: >>> 123456 <<<',
			],
		], $bodyTexts);
	}

	/**
	 * @throws SendEMailFailed
	 * @throws Exception
	 * @throws EMailNotSet
	 */
	public function testUsesCustomTemplatesAndReplacesAllPlaceholders(): void {
		$this->appSettings->method('getEMailSubject')->willReturn('Code {code} for {user}');
		$this->appSettings->method('getEMailTemplate')->willReturn('Use {code} on {cloud} within {validity} minutes.');
		// Stored values are present, so the defaults must not be consulted
		$this->appSettings->expects($this->never())->method('getDefaultEMailSubject');
		$this->appSettings->expects($this->never())->method('getDefaultEMailBody');

		$this->expectMailWithTemplate();
		$this->template->expects($this->once())
			->method('setSubject')
			->with('Code 123456 for Jane Doe');
		$this->template->expects($this->never())->method('addHeader');
		$bodyTexts = [];
		$this->collectBodyTexts($bodyTexts);
		$this->template->expects($this->once())->method('addFooter')->with();

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');

		$this->assertSame([
			['&nbsp;', false],
			[
				'Use <strong style="font-family:monospace">123456</strong> on <strong style="font-family:monospace">Example Cloud</strong> within <strong style="font-family:monospace">10</strong> minutes.',
				'Use >>> 123456 <<< on Example Cloud within 10 minutes.',
			],
		], $bodyTexts);
	}

	/**
	 * @throws Exception
	 */
	private function mockSendableMail(): void {
		$this->mockMailer();
		$this->appSettings->method('getEMailSubject')->willReturn('Code {code}');
		$this->appSettings->method('getEMailTemplate')->willReturn('Use {code}.');
	}

	/**
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testReportsAFailureWhenTheMailerThrows(): void {
		$this->mockSendableMail();
		$this->mailer->method('send')->willThrowException(new \RuntimeException('smtp is down'));
		$this->logger->expects($this->once())->method('error');

		$this->expectException(SendEMailFailed::class);

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');
	}

	/**
	 * A refused recipient comes back as a return value, not as an exception.
	 *
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testReportsAFailureWhenTheMailerRefusesTheRecipient(): void {
		$this->mockSendableMail();
		$this->mailer->method('send')->willReturn(['jane@example.com']);
		// The refused address must not reach the log
		$this->logger->expects($this->once())
			->method('error')
			->with($this->logicalNot($this->stringContains('jane@example.com')));

		$this->expectException(SendEMailFailed::class);

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.com'), '123456');
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testAsksTheRateLimiterBeforeContactingTheMailServer(): void {
		$this->mockSendableMail();
		$user = $this->mockUser('jane@example.org');

		$this->limiter->expects($this->once())
			->method('registerUserRequest')
			->with('twofactor_email-send', 10, 300, $user);

		$this->sender->sendChallengeEMail($user, '123456');
	}

	/**
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testSendsNothingOnceTheRateLimitIsReached(): void {
		$this->mockMailer();
		$this->limiter->method('registerUserRequest')->willThrowException($this->rateLimitExceeded());

		$this->mailer->expects($this->never())->method('send');

		$this->expectException(SendRateLimited::class);

		$this->sender->sendChallengeEMail($this->mockUser('jane@example.org'), '123456');
	}

	/**
	 * The whole period is the longest the account can have to wait, and the resend
	 * dialog counts down from it.
	 *
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testNamesHowLongTheCapLasts(): void {
		$this->mockMailer();
		$this->limiter->method('registerUserRequest')->willThrowException($this->rateLimitExceeded());

		try {
			$this->sender->sendChallengeEMail($this->mockUser('jane@example.org'), '123456');
			$this->fail('the capped send has to throw');
		} catch (SendRateLimited $e) {
			$this->assertSame(300, $e->retryAfterSeconds);
		}
	}

	/**
	 * The limiter counts in the database and fails with it. Every caller of this
	 * service handles a failed send, and nothing else — an error from the counter
	 * would reach the login page instead of the message that no code went out.
	 *
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testReportsAFailedSendWhenTheLimiterCannotAnswer(): void {
		$this->mockMailer();
		$this->limiter->method('registerUserRequest')->willThrowException(new DbException());

		$this->mailer->expects($this->never())->method('send');

		try {
			$this->sender->sendChallengeEMail($this->mockUser('jane@example.org'), '123456');
			$this->fail('a limiter that cannot answer has to be reported as a failed send');
		} catch (SendRateLimited) {
			$this->fail('a limiter that cannot answer is not a cap that was reached');
		} catch (SendEMailFailed $e) {
			$this->assertInstanceOf(DbException::class, $e->getPrevious());
		}
	}

	/**
	 * An account without an address never reaches the mail server, so counting it
	 * would spend the budget on nothing and turn a missing address into a mail
	 * server that did not answer.
	 *
	 * @throws SendEMailFailed
	 * @throws Exception
	 */
	public function testSpendsNoLimitWhenNoEmailIsSet(): void {
		$this->limiter->expects($this->never())->method('registerUserRequest');

		$this->expectException(EMailNotSet::class);

		$this->sender->sendChallengeEMail($this->mockUser(null), '123456');
	}
}

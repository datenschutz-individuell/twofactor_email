<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\ICodeGenerator;
use OCA\TwoFactorEMail\Service\ICodeStorage;
use OCA\TwoFactorEMail\Service\IEMailSender;
use OCA\TwoFactorEMail\Service\LoginChallenge;
use OCP\IUser;
use OCP\Security\IHasher;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LoginChallengeTest extends TestCase {
	private ICodeGenerator&MockObject $codeGenerator;
	private ICodeStorage&MockObject $codeStorage;
	private IEMailSender&MockObject $emailSender;
	private IHasher&MockObject $hasher;
	private IAppSettings&MockObject $settings;

	private LoginChallenge $challenge;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->codeGenerator = $this->createMock(ICodeGenerator::class);
		$this->codeStorage = $this->createMock(ICodeStorage::class);
		$this->emailSender = $this->createMock(IEMailSender::class);
		$this->hasher = $this->createMock(IHasher::class);
		$this->settings = $this->createMock(IAppSettings::class);

		$this->challenge = new LoginChallenge(
			$this->codeGenerator,
			$this->codeStorage,
			$this->emailSender,
			$this->hasher,
			$this->settings,
			$this->createMock(LoggerInterface::class),
		);
	}

	/**
	 * @throws Exception
	 */
	private function mockUser(): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		return $user;
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testResendIsRejectedWithinCooldown(): void {
		$this->settings->method('getResendCooldownSeconds')->willReturn(60);
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(10);
		$this->codeStorage->expects($this->never())->method('deleteCode');
		$this->emailSender->expects($this->never())->method('sendChallengeEMail');

		try {
			$this->challenge->resendChallenge($this->mockUser());
			$this->fail('Expected ResendTooSoon');
		} catch (ResendTooSoon $e) {
			$this->assertSame(50, $e->retryAfterSeconds);
		}
	}

	/**
	 * @throws ResendTooSoon
	 * @throws SendEMailFailed
	 * @throws Exception
	 * @throws EMailNotSet
	 */
	public function testResendSendsAndStoresFreshCode(): void {
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(null);
		$this->codeGenerator->method('generateChallengeCode')->willReturn('654321');
		$this->hasher->method('hash')->willReturn('hashed');

		$this->codeStorage->expects($this->never())->method('deleteCode');
		$this->emailSender->expects($this->once())->method('sendChallengeEMail')->with($this->anything(), '654321');
		$this->codeStorage->expects($this->once())->method('writeCode')->with('alice', 'hashed');

		$this->challenge->resendChallenge($this->mockUser());
	}

	/**
	 * @throws ResendTooSoon
	 * @throws SendEMailFailed
	 * @throws Exception
	 */
	public function testResendPropagatesEMailNotSet(): void {
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(null);
		$this->codeGenerator->method('generateChallengeCode')->willReturn('654321');
		$user = $this->mockUser();
		$this->emailSender->method('sendChallengeEMail')->willThrowException(new EMailNotSet($user));

		// A failed sending must not touch the stored code, so the previous one stays valid.
		$this->codeStorage->expects($this->never())->method('writeCode');
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->expectException(EMailNotSet::class);

		$this->challenge->resendChallenge($user);
	}

	/**
	 * @throws Exception
	 */
	public function testSecondsUntilResendAllowedIsZeroWithoutValidCode(): void {
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(null);

		$this->assertSame(0, $this->challenge->secondsUntilResendAllowed($this->mockUser()));
	}

	/**
	 * @throws Exception
	 */
	public function testSecondsUntilResendAllowedReturnsRemainingCooldown(): void {
		$this->settings->method('getResendCooldownSeconds')->willReturn(60);
		$this->settings->method('getCodeValidMinutes')->willReturn(10); // 600s, well above the cooldown
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(10);

		$this->assertSame(50, $this->challenge->secondsUntilResendAllowed($this->mockUser()));
	}

	/**
	 * @throws Exception
	 */
	public function testSecondsUntilResendAllowedIsZeroAfterCooldown(): void {
		$this->settings->method('getResendCooldownSeconds')->willReturn(60);
		$this->settings->method('getCodeValidMinutes')->willReturn(10);
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(100);

		$this->assertSame(0, $this->challenge->secondsUntilResendAllowed($this->mockUser()));
	}

	/**
	 * @throws Exception
	 */
	public function testSecondsUntilResendAllowedIsCappedByCodeValidity(): void {
		// Cooldown 30 min, but the code is only valid 10 min: the countdown must
		// not outlast the code, so it caps at the remaining validity.
		$this->settings->method('getResendCooldownSeconds')->willReturn(1800);
		$this->settings->method('getCodeValidMinutes')->willReturn(10); // 600s
		$this->codeStorage->method('secondsSinceLastCode')->willReturn(60);

		// min(1800, 600) - 60 = 540
		$this->assertSame(540, $this->challenge->secondsUntilResendAllowed($this->mockUser()));
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testSendChallengeIssuesAndStoresACodeWhenNoneIsStored(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn(null);
		$this->codeGenerator->method('generateChallengeCode')->willReturn('123456');
		$this->emailSender->expects($this->once())->method('sendChallengeEMail')->with($user, '123456');
		$this->hasher->method('hash')->with('123456')->willReturn('hashed');
		$this->codeStorage->expects($this->once())->method('writeCode')->with('alice', 'hashed');

		$this->assertTrue($this->challenge->sendChallenge($user));
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 * @throws Exception
	 */
	public function testSendChallengeSkipsWhileAValidCodeStillExists(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn('existing-hash');
		$this->emailSender->expects($this->never())->method('sendChallengeEMail');
		$this->codeStorage->expects($this->never())->method('writeCode');

		$this->assertFalse($this->challenge->sendChallenge($user));
	}

	/**
	 * @throws Exception
	 */
	public function testVerifyChallengeAcceptsAValidCodeAndDeletesIt(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn('stored-hash');
		$this->hasher->method('verify')->with('123456', 'stored-hash')->willReturn(true);
		$this->codeStorage->expects($this->once())->method('deleteCode')->with('alice');

		$this->assertTrue($this->challenge->verifyChallenge($user, '123456'));
	}

	/**
	 * @throws Exception
	 */
	public function testVerifyChallengeRejectsAWrongCodeAndKeepsItForRetry(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn('stored-hash');
		$this->hasher->method('verify')->with('123456', 'stored-hash')->willReturn(false);
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->assertFalse($this->challenge->verifyChallenge($user, '123456'));
	}

	/**
	 * @throws Exception
	 */
	public function testVerifyChallengeRejectsWhenNoCodeIsStored(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn(null);
		$this->hasher->expects($this->never())->method('verify');
		$this->codeStorage->expects($this->never())->method('deleteCode');

		$this->assertFalse($this->challenge->verifyChallenge($user, '123456'));
	}

	/**
	 * @throws Exception
	 */
	public function testVerifyChallengeTrimsTheSubmittedCode(): void {
		$user = $this->mockUser();
		$this->codeStorage->method('readCode')->with('alice')->willReturn('stored-hash');
		// Surrounding whitespace must be stripped before verifying
		$this->hasher->expects($this->once())->method('verify')->with('123456', 'stored-hash')->willReturn(true);

		$this->assertTrue($this->challenge->verifyChallenge($user, "  123456\n"));
	}
}

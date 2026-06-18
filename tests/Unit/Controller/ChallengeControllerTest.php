<?php

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Controller;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Controller\ChallengeController;
use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ChallengeControllerTest extends TestCase {
	private IUserSession&MockObject $userSession;
	private ILoginChallenge&MockObject $challenge;
	private IStateManager&MockObject $stateManager;

	private ChallengeController $controller;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->challenge = $this->createMock(ILoginChallenge::class);
		$this->stateManager = $this->createMock(IStateManager::class);

		$this->controller = new ChallengeController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->userSession,
			$this->challenge,
			$this->stateManager,
		);
	}

	/**
	 * @throws Exception
	 */
	private function withEnabledUser(): void {
		$this->userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$this->stateManager->method('isEnabled')->willReturn(true);
	}

	/**
	 * @throws Exception
	 */
	public function testResendSendsCode(): void {
		$this->withEnabledUser();
		$this->challenge->expects($this->once())->method('resendChallenge');

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_OK, $response->getStatus());
		$this->assertEquals(['status' => 'sent'], $response->getData());
	}

	/**
	 * @throws Exception
	 */
	public function testResendReportsCooldown(): void {
		$this->withEnabledUser();
		$this->challenge->method('resendChallenge')->willThrowException(new ResendTooSoon(42));

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_TOO_MANY_REQUESTS, $response->getStatus());
		$this->assertEquals(['error' => 'too-soon', 'retryAfter' => 42], $response->getData());
	}

	/**
	 * @throws Exception
	 */
	public function testResendRejectedWhenEmailProviderDisabled(): void {
		$this->userSession->method('getUser')->willReturn($this->createMock(IUser::class));
		$this->stateManager->method('isEnabled')->willReturn(false);
		$this->challenge->expects($this->never())->method('resendChallenge');

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'not-enabled'], $response->getData());
	}

	public function testResendWithoutUser(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	/**
	 * @throws Exception
	 */
	public function testResendReportsMissingEmail(): void {
		$this->withEnabledUser();
		$this->challenge->method('resendChallenge')
			->willThrowException(new EMailNotSet($this->createMock(IUser::class)));

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertEquals(['error' => 'no-email'], $response->getData());
	}

	/**
	 * @throws Exception
	 */
	public function testResendReportsSendFailure(): void {
		$this->withEnabledUser();
		$this->challenge->method('resendChallenge')->willThrowException(new SendEMailFailed());

		$response = $this->controller->resend();

		$this->assertEquals(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertEquals(['error' => 'send-failed'], $response->getData());
	}
}

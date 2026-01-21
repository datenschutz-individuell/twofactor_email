<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\RateLimitExceededException;
use OCP\IUser;
use Psr\Log\LoggerInterface;

class ChallengeService implements IChallengeService {
	public function __construct(
		private ICodeGenerator $codeGenerator,
		private ICodeStorage $codeStorage,
		private IEMailSender $emailSender,
		private LoggerInterface $logger,
	) {
	}

	public function sendChallenge(IUser $user): void {
		$userId = $user->getUID();

		// Check rate limiting before sending
		if (!$this->codeStorage->canSendCode($userId)) {
			$secondsRemaining = $this->codeStorage->getSecondsUntilCanResend($userId);
			$this->logger->warning('2FA email rate limit exceeded', [
				'userId' => $userId,
				'secondsRemaining' => $secondsRemaining,
			]);
			throw new RateLimitExceededException($secondsRemaining);
		}

		// Generate and store code
		$code = $this->codeGenerator->generateChallengeCode();
		$this->codeStorage->writeCode($userId, $code);
		$this->codeStorage->recordSendAttempt($userId);

		// Send email
		$this->emailSender->sendChallengeEMail($user, $code);

		// Audit log: code sent
		$this->logger->info('2FA email code sent', [
			'userId' => $userId,
			'event' => 'twofactor_email_code_sent',
		]);
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$userId = $user->getUID();
		$submittedCode = trim($submittedCode);

		// Use timing-safe verification from storage
		$isValid = $this->codeStorage->verifyCode($userId, $submittedCode);

		if ($isValid) {
			// Invalidate code after successful use to prevent reuse
			$this->codeStorage->deleteCode($userId);

			// Audit log: successful verification
			$this->logger->info('2FA email verification successful', [
				'userId' => $userId,
				'event' => 'twofactor_email_verify_success',
			]);
			return true;
		}

		// Audit log: failed verification
		$this->logger->warning('2FA email verification failed', [
			'userId' => $userId,
			'event' => 'twofactor_email_verify_failed',
		]);
		return false;
	}

	public function canSendChallenge(IUser $user): bool {
		return $this->codeStorage->canSendCode($user->getUID());
	}

	public function getSecondsUntilCanResend(IUser $user): int {
		return $this->codeStorage->getSecondsUntilCanResend($user->getUID());
	}
}

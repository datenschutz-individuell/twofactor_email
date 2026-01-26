<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Exception\RateLimitExceededException;
use OCP\IUser;
use OCP\Notification\IManager as NotificationManager;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use Psr\Log\LoggerInterface;

class ChallengeService implements IChallengeService {
	public function __construct(
		private ICodeGenerator $codeGenerator,
		private ICodeStorage $codeStorage,
		private IApplicationSettings $settings,
		private ILimiter $limiter,
		private IEMailSender $emailSender,
		private NotificationManager $notificationManager,
		private LoggerInterface $logger,
	) {
	}

	public function sendChallenge(IUser $user): void {
		$userId = $user->getUID();

		// Rate limit send attempts using Nextcloud limiter
		try {
			$this->limiter->registerUserRequest(
				'twofactor_email.send',
				$this->settings->getMaxResendAttempts(),
				$this->settings->getResendWindowSeconds(),
				$user
			);
		} catch (IRateLimitExceededException $exception) {
			$this->logger->warning('2FA email rate limit exceeded', [
				'userId' => $userId,
			]);
			throw new RateLimitExceededException();
		}

		// Generate and store code
		$code = $this->codeGenerator->generateChallengeCode();
		$this->codeStorage->writeCode($userId, $code);

		// Send email
		$this->emailSender->sendChallengeEMail($user, $code);

		// Send browser notification to alert user of login attempt
		$this->sendLoginAttemptNotification($user);

		// Audit log: code sent
		$this->logger->info('2FA email code sent', [
			'userId' => $userId,
			'event' => 'twofactor_email_code_sent',
		]);
	}

	private function sendLoginAttemptNotification(IUser $user): void {
		// First dismiss any existing login attempt notifications for this user
		$this->dismissLoginAttemptNotifications($user);

		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($user->getUID())
			->setDateTime(new \DateTime())
			->setObject('login_attempt', $user->getUID())
			->setSubject('login_attempt');
		$this->notificationManager->notify($notification);
	}

	private function dismissLoginAttemptNotifications(IUser $user): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($user->getUID())
			->setObject('login_attempt', $user->getUID());
		$this->notificationManager->markProcessed($notification);
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$userId = $user->getUID();
		$submittedCode = trim($submittedCode);

		$isValid = false;
		$createdAt = $this->codeStorage->getCodeCreatedAt($userId);
		if ($createdAt !== 0) {
			$expiresBefore = time() - $this->settings->getCodeValidSeconds();
			if ($createdAt < $expiresBefore) {
				$this->codeStorage->deleteCode($userId);
			} else {
				$storedHash = $this->codeStorage->getCodeHash($userId);
				if ($storedHash !== '') {
					// Timing-safe verification using password_verify (bcrypt)
					// This prevents timing attacks as bcrypt comparison is constant-time
					$isValid = password_verify($submittedCode, $storedHash);
				}
			}
		}

		if ($isValid) {
			// Invalidate code after successful use to prevent reuse
			$this->codeStorage->deleteCode($userId);

			// Dismiss the login attempt notification
			$this->dismissLoginAttemptNotifications($user);

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

}

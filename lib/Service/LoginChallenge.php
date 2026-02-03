<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCP\IUser;
use OCP\Security\IHasher;

final class LoginChallenge implements ILoginChallenge {
	public function __construct(
		private ICodeGenerator $codeGenerator,
		private ICodeStorage $codeStorage,
		private IEMailSender $emailSender,
		private IHasher $hasher,
		private IVerificationAttemptTracker $attemptTracker,
		private IAppSettings $settings,
	) {
	}

	/**
	 * Store code securely (and resistent to time-based attacks) in case an attacker managed to elevate his privileges.
	 *
	 * Login retry throttling is done by Nextcloud, but re-loading the form would generate and send new codes.
	 * This is not handled by the brute force protection. We could skip sending emails once a rate limit is reached,
	 * see https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/security.html#rate-limiting
	 * Instead, we don't send a new code if we can read the code from storage (which means that there's a code that
	 * is still valid).
	 *
	 * One could always delete the code after verification. Allowing retries is more convenient for users (mistype).
	 */
	public function sendChallenge(IUser $user): bool {
		$userId = $user->getUID();

		// If there is a still valid code, don't generate and send another.
		// This prevents a DoS attack vector and thus obsoletes the use of ILimiter in EMailSender.
		if (! is_null($this->codeStorage->readCode($userId))) {
			return false; // No new e-mail sent
		}

		$newCode = $this->codeGenerator->generateChallengeCode();
		try {
			$this->emailSender->sendChallengeEMail($user, $newCode);
			// Only store the code and reset failed attempts if it could be sent.
			$this->codeStorage->writeCode($userId, $this->hasher->hash($newCode));
			$this->attemptTracker->resetAttempts($userId);
			return true; // New code sent by e-mail
		} catch (EMailNotSet|SendEMailFailed) {
			return false; // E-Mail could not be sent
		}
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$userId = $user->getUID();
		// Normalize: trim whitespace and convert to uppercase for case-insensitive comparison
		$submittedCode = strtoupper(trim($submittedCode));
		$storedCodeHash = $this->codeStorage->readCode($userId);

		if (is_null($storedCodeHash)) {
			return false; // There was no still valid code stored
		}

		if ($this->hasher->verify($submittedCode, $storedCodeHash)) {
			// Successful - delete the code and reset attempts
			$this->codeStorage->deleteCode($userId);
			$this->attemptTracker->resetAttempts($userId);
			return true; // The code the user entered matched the stored one
		} else {
			// Failed - record attempt, delete code if limit was hit
			if ($this->attemptTracker->recordFailedAttempt($userId)) {
				$this->codeStorage->deleteCode($userId);
			}
			return false; // The code the user entered didn't match the stored one
		}
	}
}

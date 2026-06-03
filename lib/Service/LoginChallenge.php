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
		private readonly ICodeGenerator $codeGenerator,
		private readonly ICodeStorage $codeStorage,
		private readonly IEMailSender $emailSender,
		private readonly IHasher $hasher,
	) {
	}

	public function sendChallenge(IUser $user): bool {
		/**
		 * Store code securely and time-based attack resistent in case an attacker managed to elevate his privileges.
		 */
		$storedCodeHash = $this->codeStorage->readCode($user->getUID());

		/**
		 * Login retry throttling is done by Nextcloud, but re-loading the form would generate and send new codes.
		 * This is not handled by the brute force protection. We could skip sending emails once a rate limit is reached,
		 * see https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/security.html#rate-limiting
		 * Instead, we don't generate and send a new code as long as we can read a code from the user's preferences.
		 * We can only successfully read a code if there is one stored and while that one is still valid.
		 */
		if (!is_null($storedCodeHash)) {
			return false;
		}

		$generatedCode = $this->codeGenerator->generateChallengeCode();
		try {
			$this->emailSender->sendChallengeEMail($user, $generatedCode);

			// Only store the code if it could be sent.
			$this->codeStorage->writeCode($user->getUID(), $this->hasher->hash($generatedCode));
			return true;
		} catch (EMailNotSet|SendEMailFailed) {
			return false;
		}
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$submittedCode = trim($submittedCode);
		$storedCodeHash = $this->codeStorage->readCode($user->getUID());
		if (is_null($storedCodeHash)) {
			$isValid = false;
		} else {
			$isValid = $this->hasher->verify($submittedCode, $storedCodeHash);
		}

		/*
		 * We currently only delete the code if it was successfully used (and the user is verified / logged in).
		 * We could always delete the code, even if the verification failed. That would be more secure but less
		 * convenient. We want users to be able to retry in case the mistyped their code.
		 */
		if ($isValid) {
			$this->codeStorage->deleteCode($user->getUID());
		}
		return $isValid;
	}
}

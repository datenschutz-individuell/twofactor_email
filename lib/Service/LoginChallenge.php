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
		// If there is still a valid code stored, don't generate and send another.
		$storedCodeHash = $this->codeStorage->readCode($user->getUID());
		if (! is_null($storedCodeHash)) {
			return false;
		}

		$generatedCode = $this->codeGenerator->generateChallengeCode();
		try {
			$this->emailSender->sendChallengeEMail($user, $generatedCode);

			// Only store the code if it could be sent.
			$this->codeStorage->writeCode($user->getUID(), $this->hasher->hash($generatedCode));
			return true;
		} catch (EMailNotSet | SendEMailFailed) {
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

		if ($isValid) {
			$this->codeStorage->deleteCode($user->getUID());
		}
		return $isValid;
	}
}

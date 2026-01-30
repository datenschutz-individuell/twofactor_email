<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

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

	public function sendChallenge(IUser $user): void {
		$generatedCode = $this->codeGenerator->generateChallengeCode();
		// to harden in case of privilege escalation, store code as hash using a method resistent to time-based attacks
		$this->codeStorage->writeCode($user->getUID(), $this->hasher->hash($generatedCode));
		$this->emailSender->sendChallengeEMail($user, $generatedCode);
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$submittedCode = trim($submittedCode);
		$storedCodeHash = $this->codeStorage->readCode($user->getUID());
		if (is_null($storedCodeHash)) {
			$isValid = false;
		} else {
			$isValid = $this->hasher->verify($submittedCode, $storedCodeHash);
		}

		// We could always delete the code here but this way it is more convenient for users (in case of a mistype).
		if ($isValid) {
			$this->codeStorage->deleteCode($user->getUID());
		}
		return $isValid;
	}
}

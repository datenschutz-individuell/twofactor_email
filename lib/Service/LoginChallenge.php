<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\IUser;

final class LoginChallenge implements ILoginChallenge {
	public function __construct(
		private ICodeGenerator $codeGenerator,
		private ICodeStorage $codeStorage,
		private IEMailSender $emailSender,
	) {
	}

	public function sendChallenge(IUser $user): void {
		$generatedCode = $this->codeGenerator->generateChallengeCode();

		// Is it OK to define the algo and data structure here in these two functions?
		// We want to keep it simple, and to pull from AppSettings seems a lot of overhead!

		// the code was stored as plain text (6 digits) before, without a delimiter
		// we deem all such codes invalid and ignore them

		// We now use a structured string to denote in which form the code was stored, with ':' as delimiter.
		// code := [algorithm identifier].':'.[representation of the code, e.g. its hash]

		// list of yet defined algorithm identifiers:
		//   PBC = password_hash($code, PASSWORD_BCRYPT) – currently in use

		$hashedCode = 'PBC:' . password_hash($generatedCode, PASSWORD_BCRYPT);

		$this->codeStorage->writeCode($user->getUID(), $hashedCode);
		$this->emailSender->sendChallengeEMail($user, $generatedCode);
	}

	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$submittedCode = trim($submittedCode);
		$storedCode = $this->codeStorage->readCode($user->getUID());

		if (! is_null($storedCode)) {
			$array = preg_split(':', $storedCode, -1, PREG_SPLIT_NO_EMPTY);
			if ($array) {
				$isValid = match ($array[0]) {
					'PBC' => password_hash($submittedCode, PASSWORD_BCRYPT) === $storedCode,
					default => false, // unknown algorithm identifier → discard
				};
			} else {
				// preg_split either did not find a delimiter (":") in the stored string or one field was empty
				$isValid = false;
				// ToDo: Should The user be notified? Should these cases then be handled differently?
			}
		} else {
			$isValid = false; // readCode did not return a code
			// ToDo: This should never happen since a code should have been saved prior to verifying it
			//       So should this be logged as warning* Should the user be notified (about what)?
		}

		// ToDo: Should we delete the code as soon as it was used once?The user could re-login…
		//       The user would then need to start over the whole login process …
		if ($isValid) {
			$this->codeStorage->deleteCode($user->getUID());
		}
		return $isValid;
	}
}

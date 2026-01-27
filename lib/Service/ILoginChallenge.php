<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCP\IUser;

interface ILoginChallenge {
	/**
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	/**
	 * Generate a challenge code and send it to the user via e-mail
	 */public function sendChallenge(IUser $user): void;

	/**
	 * Verify the challenge code entered by the user against the one stored upon sending it
	 * @return bool True if code is valid
	 */public function verifyChallenge(IUser $user, string $submittedCode): bool;
}

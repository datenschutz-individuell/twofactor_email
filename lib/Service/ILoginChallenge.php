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
	 * Generate a challenge code and send it to the user via e-mail.
	 *
	 * @param IUser $user UID
	 * @return bool true if a new code was sent
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	public function sendChallenge(IUser $user): bool;

	/**
	 * Verify the challenge code sent to the user by e-mail against the one stored upon sending it.
	 *
	 * @param IUser $user UID
	 * @param string $submittedCode Authentication code received by e-mail
	 * @return bool True if code is valid
	 */
	public function verifyChallenge(IUser $user, string $submittedCode): bool;
}

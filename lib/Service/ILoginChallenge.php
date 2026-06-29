<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCP\IUser;

interface ILoginChallenge {
	/**
	 * Generate a challenge code and send it to the user via email.
	 *
	 * @param IUser $user UID
	 * @return bool true if a new code was sent
	 *
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	public function sendChallenge(IUser $user): bool;

	/**
	 * Discard the current code and send a fresh one on the user's explicit
	 * request, throttled by the configured resend cooldown.
	 *
	 * @param IUser $user UID
	 *
	 * @throws ResendTooSoon if the cooldown has not elapsed yet
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	public function resendChallenge(IUser $user): void;

	/**
	 * Seconds the user still has to wait before a resend is allowed (0 if a
	 * new code can be requested right now). Lets the challenge dialog show a
	 * live countdown instead of letting the user click and fail.
	 *
	 * @param IUser $user UID
	 */
	public function secondsUntilResendAllowed(IUser $user): int;

	/**
	 * Verify the challenge code sent to the user by email against the one stored upon sending it.
	 *
	 * @param IUser $user UID
	 * @param string $submittedCode Authentication code received by email
	 * @return bool True if code is valid
	 */
	public function verifyChallenge(IUser $user, string $submittedCode): bool;
}

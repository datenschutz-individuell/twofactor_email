<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\IUser;

/**
 * The one place that answers where a challenge goes.
 *
 * Everything that needs the address asks here: the sender, to deliver; the login
 * challenge, to bind a code to the mailbox it went to; and the screens and the
 * listener, which all ask the same question — is there a mailbox, and which one.
 * With a single source they cannot drift apart, and a change of where the app
 * takes the address from is one edit.
 */
final readonly class EMailAddressSource {
	/**
	 * The address the app delivers to, or null when the account has none.
	 *
	 * Nextcloud answers with the primary address when the account has one, and
	 * with the account's address otherwise.
	 */
	public function getAddress(IUser $user): ?string {
		return $user->getEMailAddress();
	}
}

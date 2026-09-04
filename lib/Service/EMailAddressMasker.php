<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

final class EMailAddressMasker implements IEMailAddressMasker {
	public function maskForUI(string $emailAddress): string {
		if (!preg_match('/^([^@\s]+)@([^@\s]+)$/', $emailAddress, $m)) {
			// Anything this cannot take apart is hidden whole. A quoted local
			// part may hold a space or a second '@', and an address written
			// through occ passes no validation at all — neither may end up
			// readable on the login screen. An empty address stays empty, so
			// that callers can tell "no address" from "an address we cannot
			// name": only the first of the two is worth a message of its own.
			return $emailAddress === '' ? '' : self::HIDDEN;
		}

		$local = $m[1];
		$domain = $m[2];

		$firstChar = mb_strlen($local) > 0 ? mb_substr($local, 0, 1) : '*';
		$domainParts = explode('.', $domain);

		if (count($domainParts) === 1) {
			return $firstChar . '*@*';
		}

		$tld = $domainParts[count($domainParts) - 1];
		return $firstChar . '*@*.' . $tld;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Exception;

use Exception;

/**
 * Exception thrown when user's email domain is not in the allowed domains list.
 */
final class InvalidEmailDomain extends Exception {
	public function __construct(string $email, array $allowedDomains) {
		$atPos = strrpos($email, '@');
		$domain = $atPos !== false ? substr($email, $atPos + 1) : 'unknown';
		$allowed = implode(', ', $allowedDomains);
		parent::__construct("Email domain '$domain' is not allowed. Allowed domains: $allowed");
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\Security\ISecureRandom;

/**
 * Generates secure verification codes.
 * Supports both numeric-only and alphanumeric codes based on settings.
 */
final class CodeGenerator implements ICodeGenerator {
	// Uppercase letters and digits, excluding confusing characters (0/O, 1/I/L)
	private const ALPHANUMERIC_CHARS = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

	public function __construct(
		private ISecureRandom $secureRandom,
		private IAppSettings $settings,
	) {
	}

	public function generateChallengeCode(): string {
		$length = $this->settings->getCodeLength();

		if ($this->settings->useAlphanumericCodes()) {
			return $this->secureRandom->generate($length, self::ALPHANUMERIC_CHARS);
		}

		return $this->secureRandom->generate($length, ISecureRandom::CHAR_DIGITS);
	}
}

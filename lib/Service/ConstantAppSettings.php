<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

/**
 * Hardcoded default settings. Used as fallback or for testing.
 * In production, use ConfigurableAppSettings instead.
 */
final class ConstantAppSettings implements IAppSettings {
	public function getCodeLength(): int {
		return 6;
	}

	public function getCodeValidSeconds(): int {
		return 60 * 10; // 10 minutes
	}

	public function getMaxVerificationAttempts(): int {
		return 3;
	}

	public function useAlphanumericCodes(): bool {
		return false;
	}

	public function getSendRateLimitAttempts(): int {
		return 10;
	}

	public function getSendRateLimitPeriodSeconds(): int {
		return 60 * 10; // 10 minutes
	}

	public function includeEmailHeader(): bool {
		return true;
	}

	public function getAllowedDomains(): array {
		return [];
	}

	public function preferLdapEmail(): bool {
		return false;
	}
}

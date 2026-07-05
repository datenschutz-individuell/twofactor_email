<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

/**
 * Validates the admin settings. Used by the admin settings web UI and the
 * occ command, so both enforce the same limits.
 */
final class SettingsValidator {

	// Allowed range for the number of digits in a 2FA code
	public const MIN_CODE_LENGTH = 4;
	public const MAX_CODE_LENGTH = 16;

	// Allowed range for code validity in minutes
	public const MIN_CODE_VALID_MINUTES = 1;
	public const MAX_CODE_VALID_MINUTES = 44640; // 1 month

	// Allowed range for the resend cooldown in minutes
	public const MIN_RESEND_MINUTES = 1;
	public const MAX_RESEND_MINUTES = 60; // 1 hour

	// Maximum allowed lengths for the email template parts in characters
	public const MAX_EMAIL_SUBJECT_LENGTH = 255;
	public const MAX_EMAIL_TEMPLATE_LENGTH = 10000;

	/**
	 * Validates the given admin settings.
	 * Returns an array of error keys, or an empty array if all values are valid.
	 *
	 * @return string[]
	 */
	public function validate(
		int $codeLength,
		int $codeValidMinutes,
		int $resendMinutes,
		string $eMailSubject,
		string $eMailTemplate,
	): array {
		$errors = [];
		if ($codeLength < self::MIN_CODE_LENGTH || $codeLength > self::MAX_CODE_LENGTH) {
			$errors[] = 'code-length-out-of-range';
		}
		if ($codeValidMinutes < self::MIN_CODE_VALID_MINUTES || $codeValidMinutes > self::MAX_CODE_VALID_MINUTES) {
			$errors[] = 'code-valid-minutes-out-of-range';
		}
		if ($resendMinutes < self::MIN_RESEND_MINUTES || $resendMinutes > self::MAX_RESEND_MINUTES) {
			$errors[] = 'resend-minutes-out-of-range';
		}
		if (strlen($eMailSubject) > self::MAX_EMAIL_SUBJECT_LENGTH) {
			$errors[] = 'email-subject-too-long';
		}
		// Guard against header injection — the subject must stay a single line
		if (preg_match('/[\r\n]/', $eMailSubject) === 1) {
			$errors[] = 'email-subject-must-be-single-line';
		}
		if (strlen($eMailTemplate) > self::MAX_EMAIL_TEMPLATE_LENGTH) {
			$errors[] = 'email-template-too-long';
		}
		// The code must reach the user: an empty body falls back to the default
		// which contains {code}, so only a customized body can lose it.
		if ($eMailTemplate !== '' && !str_contains($eMailTemplate, '{code}')) {
			$errors[] = 'email-code-placeholder-missing';
		}
		return $errors;
	}
}

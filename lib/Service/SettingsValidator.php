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
	public const MAX_CODE_VALID_MINUTES = 1440; // 1 day

	// Allowed range for the resend cooldown in minutes
	public const MIN_RESEND_MINUTES = 1;
	public const MAX_RESEND_MINUTES = 60; // 1 hour

	// Maximum allowed lengths for the email template parts in characters
	public const MAX_EMAIL_SUBJECT_LENGTH = 255;
	public const MAX_EMAIL_TEMPLATE_LENGTH = 10000;

	/**
	 * Returns the numeric limits per settings field, so the web UI can name
	 * them in its validation messages without duplicating the values.
	 *
	 * @return array<string, array{min?: int, max: int}>
	 */
	public static function getLimits(): array {
		return [
			'codeLength' => ['min' => self::MIN_CODE_LENGTH, 'max' => self::MAX_CODE_LENGTH],
			'codeValidMinutes' => ['min' => self::MIN_CODE_VALID_MINUTES, 'max' => self::MAX_CODE_VALID_MINUTES],
			'codeResendMinutes' => ['min' => self::MIN_RESEND_MINUTES, 'max' => self::MAX_RESEND_MINUTES],
			'eMailSubject' => ['max' => self::MAX_EMAIL_SUBJECT_LENGTH],
			'eMailTemplate' => ['max' => self::MAX_EMAIL_TEMPLATE_LENGTH],
		];
	}

	/**
	 * Validates the given admin settings.
	 * Returns a map of field name to error code, or an empty array if all
	 * values are valid. The field names match the settings keys used by the
	 * web UI, so callers can flag the offending field without knowing which
	 * code belongs to which field. A field that trips more than one check
	 * keeps its last error.
	 *
	 * @return array<string, string>
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
			$errors['codeLength'] = 'code-length-out-of-range';
		}
		if ($codeValidMinutes < self::MIN_CODE_VALID_MINUTES || $codeValidMinutes > self::MAX_CODE_VALID_MINUTES) {
			$errors['codeValidMinutes'] = 'code-valid-minutes-out-of-range';
		}
		if ($resendMinutes < self::MIN_RESEND_MINUTES || $resendMinutes > self::MAX_RESEND_MINUTES) {
			$errors['codeResendMinutes'] = 'resend-minutes-out-of-range';
		}
		if (strlen($eMailSubject) > self::MAX_EMAIL_SUBJECT_LENGTH) {
			$errors['eMailSubject'] = 'email-subject-too-long';
		}
		// Guard against header injection — the subject must stay a single line
		if (preg_match('/[\r\n]/', $eMailSubject) === 1) {
			$errors['eMailSubject'] = 'email-subject-must-be-single-line';
		}
		if (strlen($eMailTemplate) > self::MAX_EMAIL_TEMPLATE_LENGTH) {
			$errors['eMailTemplate'] = 'email-template-too-long';
		}
		// The code must reach the user: an empty body falls back to the default
		// which contains {code}, so only a customized body can lose it.
		if ($eMailTemplate !== '' && !str_contains($eMailTemplate, '{code}')) {
			$errors['eMailTemplate'] = 'email-code-placeholder-missing';
		}
		return $errors;
	}
}

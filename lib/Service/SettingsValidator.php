<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Mail\TemplateRenderer;

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
	 * code belongs to which field. A field that trips more than one check keeps
	 * its FIRST error, so the checks are ordered by what the admin has to fix
	 * first — a new one belongs where it ranks, not at the end.
	 *
	 * The stored texts are the baseline. A text that is unchanged may keep a
	 * placeholder inside a web address, because an instance can carry one without
	 * anyone having typed it here and it must not block the other settings. Every
	 * other error still blocks, and so does a changed text.
	 *
	 * @return array<string, string>
	 */
	public function validate(
		int $codeLength,
		int $codeValidMinutes,
		int $resendMinutes,
		string $eMailSubject,
		string $eMailTemplate,
		string $storedSubject = '',
		string $storedTemplate = '',
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
		// AppSettings discards such a text on every read, so accepting it here would
		// report a setting that never takes effect.
		if (!mb_check_encoding($eMailSubject, 'UTF-8')) {
			$errors['eMailSubject'] = 'email-subject-not-valid-text';
		}
		if (!mb_check_encoding($eMailTemplate, 'UTF-8')) {
			$errors['eMailTemplate'] = 'email-template-not-valid-text';
		}
		if (!isset($errors['eMailSubject']) && mb_strlen($eMailSubject) > self::MAX_EMAIL_SUBJECT_LENGTH) {
			$errors['eMailSubject'] = 'email-subject-too-long';
		}
		// Guard against header injection — the subject must stay a single line
		if (!isset($errors['eMailSubject']) && preg_match('/[\r\n]/', $eMailSubject) === 1) {
			$errors['eMailSubject'] = 'email-subject-must-be-single-line';
		}
		// Only when nothing worse was found: a line break in a header outranks this,
		// and overwriting it would hide it until the admin has fixed the URL.
		// The baseline serves the occ command, which sets one key at a time and must
		// not refuse an unrelated key because a stored text is faulty.
		if (!isset($errors['eMailSubject'])
			&& TemplateRenderer::hasPlaceholderInUrl($eMailSubject)
			&& !self::unchanged($eMailSubject, $storedSubject)) {
			$errors['eMailSubject'] = 'email-subject-placeholder-in-url';
		}
		if (!isset($errors['eMailTemplate']) && mb_strlen($eMailTemplate) > self::MAX_EMAIL_TEMPLATE_LENGTH) {
			$errors['eMailTemplate'] = 'email-template-too-long';
		}
		// The code must reach the user: an empty body falls back to the default
		// which contains {code}, so only a customized body can lose it.
		if (!isset($errors['eMailTemplate']) && $eMailTemplate !== '' && !str_contains($eMailTemplate, '{code}')) {
			$errors['eMailTemplate'] = 'email-code-placeholder-missing';
		}
		// Where {code} occurs decides whether it stays in the message: inside a URL it
		// would be substituted into a link that scanners fetch unattended. Again only
		// when nothing worse was found — a body without {code} delivers nothing.
		if (!isset($errors['eMailTemplate'])
			&& TemplateRenderer::hasPlaceholderInUrl($eMailTemplate)
			&& !self::unchanged($eMailTemplate, $storedTemplate)) {
			$errors['eMailTemplate'] = 'email-template-placeholder-in-url';
		}
		return $errors;
	}

	/**
	 * Browsers normalize line endings in a text area, so a text sent back with LF
	 * still counts as the stored CRLF text.
	 */
	private static function unchanged(string $submitted, string $stored): bool {
		$toLf = static fn (string $text): string => str_replace(["\r\n", "\r"], "\n", $text);
		return $toLf($submitted) === $toLf($stored);
	}
}

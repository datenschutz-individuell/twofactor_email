<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * This class may NOT be renamed to e.g. 'AdminSettings.php' since Nextcloud USES the class suffix 'Controller'.
 * See routes.php.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IRequest;

final class AdminSettingsController extends ALoginSetupController {

	// Allowed range for the number of digits in a 2FA code
	private const MIN_CODE_LENGTH = 4;
	private const MAX_CODE_LENGTH = 16;

	// Allowed range for code validity in minutes
	private const MIN_CODE_VALID_MINUTES = 1;
	private const MAX_CODE_VALID_MINUTES = 44640; // 1 month

	// Allowed range for the resend cooldown in minutes
	private const MIN_RESEND_MINUTES = 1;
	private const MAX_RESEND_MINUTES = 60; // 1 hour

	// Maximum allowed lengths for the email template parts in characters
	private const MAX_EMAIL_SUBJECT_LENGTH = 255;
	private const MAX_EMAIL_TEMPLATE_LENGTH = 10000;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppSettings $appSettings,
	) {
		parent::__construct($appName, $request);
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function save(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
		string $eMailSubject,
		int $resendMinutes,
	): JSONResponse {
		$errors = $this->validate($codeLength, $codeValidMinutes, $eMailTemplate, $eMailSubject, $resendMinutes);
		if (!empty($errors)) {
			return new JSONResponse(['error' => implode(', ', $errors)], Http::STATUS_BAD_REQUEST);
		}

		$this->appSettings->setCodeLength($codeLength);
		$this->appSettings->setCodeValidMinutes($codeValidMinutes);
		$this->appSettings->setResendMinMinutes($resendMinutes);
		$this->appSettings->setEMailSubject($eMailSubject);
		$this->appSettings->setEMailTemplate($eMailTemplate);

		return $this->currentSettingsResponse();
	}

	/**
	 * Validates the given admin settings.
	 * Returns an array of error strings, or an empty array if all values are valid.
	 *
	 * @param int $codeLength
	 * @param int $codeValidMinutes
	 * @param string $eMailTemplate
	 * @param string $eMailSubject
	 * @param int $resendMinutes
	 * @return string[]
	 */
	private function validate(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
		string $eMailSubject,
		int $resendMinutes,
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

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function reset(): JSONResponse {
		$this->appSettings->resetToDefaults();

		return $this->currentSettingsResponse();
	}

	private function currentSettingsResponse(): JSONResponse {
		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'codeResendMinutes' => $this->appSettings->getResendMinMinutes(),
			'eMailSubject' => $this->appSettings->getEMailSubject(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}
}

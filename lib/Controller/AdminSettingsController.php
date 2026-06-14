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

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\AppSettingsDefaults;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IAppConfig;
use OCP\IRequest;

final class AdminSettingsController extends ALoginSetupController {

	// Allowed range for the number of digits in a 2FA code
	private const MIN_CODE_LENGTH = 4;
	private const MAX_CODE_LENGTH = 16;

	// Allowed range for code validity in minutes
	private const MIN_CODE_VALID_MINUTES = 1;
	private const MAX_CODE_VALID_MINUTES = 44640; // 1 month

	// Maximum allowed lengths for the email template parts in characters
	private const MAX_EMAIL_SUBJECT_LENGTH = 255;
	private const MAX_EMAIL_TEMPLATE_LENGTH = 10000;

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly IAppSettings $appSettings,
	) {
		parent::__construct($appName, $request);
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function save(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
		string $eMailSubject = '',
	): JSONResponse {
		$errors = $this->validate($codeLength, $codeValidMinutes, $eMailTemplate, $eMailSubject);
		if (!empty($errors)) {
			return new JSONResponse(['error' => implode(', ', $errors)], Http::STATUS_BAD_REQUEST);
		}

		$this->appConfig->setValueInt(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_LENGTH, $codeLength);
		$this->appConfig->setValueInt(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_VALID_MINUTES, $codeValidMinutes);
		$this->appConfig->setValueString(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_SUBJECT, $eMailSubject);
		$this->appConfig->setValueString(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_TEMPLATE, $eMailTemplate);

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
	 * @return string[]
	 */
	private function validate(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
		string $eMailSubject,
	): array {
		$errors = [];
		if ($codeLength < self::MIN_CODE_LENGTH || $codeLength > self::MAX_CODE_LENGTH) {
			$errors[] = 'code-length-out-of-range';
		}
		if ($codeValidMinutes < self::MIN_CODE_VALID_MINUTES || $codeValidMinutes > self::MAX_CODE_VALID_MINUTES) {
			$errors[] = 'code-valid-minutes-out-of-range';
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
		// Delete all keys so the defaults take effect immediately
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_LENGTH);
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_VALID_MINUTES);
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_SUBJECT);
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_TEMPLATE);

		return $this->currentSettingsResponse();
	}

	private function currentSettingsResponse(): JSONResponse {
		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'eMailSubject' => $this->appSettings->getEMailSubject(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}
}

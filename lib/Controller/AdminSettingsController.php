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

	// Maximum allowed length for the email template in characters
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
	): JSONResponse {
		$errors = $this->validate($codeLength, $codeValidMinutes, $eMailTemplate);
		if (!empty($errors)) {
			return new JSONResponse(['error' => implode(', ', $errors)], Http::STATUS_BAD_REQUEST);
		}

		$this->appConfig->setValueInt(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_LENGTH, $codeLength);
		$this->appConfig->setValueInt(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_VALID_MINUTES, $codeValidMinutes);
		$this->appConfig->setValueString(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_TEMPLATE, $eMailTemplate);

		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}

	/**
	 * Validates the given admin settings.
	 * Returns an array of error strings, or an empty array if all values are valid.
	 *
	 * @param int $codeLength
	 * @param int $codeValidMinutes
	 * @param string $eMailTemplate
	 * @return string[]
	 */
	private function validate(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
	): array {
		$errors = [];
		if ($codeLength < self::MIN_CODE_LENGTH || $codeLength > self::MAX_CODE_LENGTH) {
			$errors[] = 'code-length-out-of-range';
		}
		if ($codeValidMinutes < self::MIN_CODE_VALID_MINUTES || $codeValidMinutes > self::MAX_CODE_VALID_MINUTES) {
			$errors[] = 'code-valid-minutes-out-of-range';
		}
		if (strlen($eMailTemplate) > self::MAX_EMAIL_TEMPLATE_LENGTH) {
			$errors[] = 'email-template-too-long';
		}
		return $errors;
	}

	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function reset(): JSONResponse {
		// Delete all keys so the defaults take effect immediately
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_LENGTH);
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_CODE_VALID_MINUTES);
		$this->appConfig->deleteKey(Application::APP_ID, AppSettingsDefaults::CONFIG_KEY_EMAIL_TEMPLATE);

		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}
}

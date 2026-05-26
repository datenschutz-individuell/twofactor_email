<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * This class may NOT be renamed to e.g. 'State.php' since Nextcloud USES the class suffix 'Controller'.
 * See routes.php.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\IAppSettings; // superseeds IAppConfig: loads settings from DB, else uses default
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IAppConfig; // (still) necessary to write (typed) settings since IAppConfig does not (yet) implement setters
use OCP\IRequest;

final class AdminSettingsController extends ALoginSetupController {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
		private IAppSettings $appSettings,
	) {
		parent::__construct($appName, $request);
	}

    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function update(
		int $codeLength,
		int $codeValidMinutes,
		int $sendRateLimitAttempts,
		int $sendRateLimitPeriodSeconds,
		string $eMailTemplate,
	): JSONResponse {
		$this->appConfig->setValueInt(Application::APP_ID, 'code_length', $codeLength);
		$this->appConfig->setValueInt(Application::APP_ID, 'code_valid_minutes', $codeValidMinutes);
		$this->appConfig->setValueInt(Application::APP_ID, 'send_rate_limit_attempts', $sendRateLimitAttempts);
		$this->appConfig->setValueInt(Application::APP_ID, 'send_rate_limit_period_seconds', $sendRateLimitPeriodSeconds);
		$this->appConfig->setValueString(Application::APP_ID, 'email_template', $eMailTemplate);

		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'sendRateLimitAttempts' => $this->appSettings->getSendRateLimitAttempts(),
			'sendRateLimitPeriodSeconds' => $this->appSettings->getSendRateLimitPeriodSeconds(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}

    #[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function reset(): JSONResponse {
		$this->appConfig->deleteKey(Application::APP_ID, 'code_length');
		$this->appConfig->deleteKey(Application::APP_ID, 'code_valid_minutes');
		$this->appConfig->deleteKey(Application::APP_ID, 'send_rate_limit_attempts');
		$this->appConfig->deleteKey(Application::APP_ID, 'send_rate_limit_period_seconds');
		$this->appConfig->deleteKey(Application::APP_ID, 'email_template');

		// Return the effective defaults so the frontend can update immediately
		return new JSONResponse([
			'codeLength' => $this->appSettings->getCodeLength(),
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
			'sendRateLimitAttempts' => $this->appSettings->getSendRateLimitAttempts(),
			'sendRateLimitPeriodSeconds' => $this->appSettings->getSendRateLimitPeriodSeconds(),
			'eMailTemplate' => $this->appSettings->getEMailTemplate(),
		]);
	}
}

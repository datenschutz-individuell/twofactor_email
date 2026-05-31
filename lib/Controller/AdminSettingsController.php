<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * This class may NOT be renamed to e.g. 'AdminSettings.php' since Nextcloud USES the class suffix 'Controller'.
 * See routes.php.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\AppInfo\Application;
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

	// Allowed range for the number of send attempts within the rate limit period
	private const MIN_SEND_RATE_LIMIT_ATTEMPTS = 1;
	private const MAX_SEND_RATE_LIMIT_ATTEMPTS = 1440; // 1/minute for 1 day

	// Allowed range for the rate limit period in seconds
	private const MIN_SEND_RATE_LIMIT_PERIOD_SECONDS = 1;
	private const MAX_SEND_RATE_LIMIT_PERIOD_SECONDS = 86400; // 1 day

	// Maximum allowed length for the email template in characters
	private const MAX_EMAIL_TEMPLATE_LENGTH = 10000;

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
		$errors = $this->validate($codeLength, $codeValidMinutes, $sendRateLimitAttempts, $sendRateLimitPeriodSeconds, $eMailTemplate);
		if (!empty($errors)) {
			return new JSONResponse(['error' => implode(', ', $errors)], Http::STATUS_BAD_REQUEST);
		}

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

	/**
	 * Validates the given admin settings.
	 * Returns an array of error strings, or an empty array if all values are valid.
	 *
	 * @param int $codeLength
	 * @param int $codeValidMinutes
	 * @param int $sendRateLimitAttempts
	 * @param int $sendRateLimitPeriodSeconds
	 * @param string $eMailTemplate
	 * @return string[]
	 */
	private function validate(
		int $codeLength,
		int $codeValidMinutes,
		int $sendRateLimitAttempts,
		int $sendRateLimitPeriodSeconds,
		string $eMailTemplate,
	): array {
		$errors = [];

		if ($codeLength < self::MIN_CODE_LENGTH || $codeLength > self::MAX_CODE_LENGTH) {
			$errors[] = 'code-length-out-of-range';
		}

		if ($codeValidMinutes < self::MIN_CODE_VALID_MINUTES || $codeValidMinutes > self::MAX_CODE_VALID_MINUTES) {
			$errors[] = 'code-valid-minutes-out-of-range';
		}

		if ($sendRateLimitAttempts < self::MIN_SEND_RATE_LIMIT_ATTEMPTS || $sendRateLimitAttempts > self::MAX_SEND_RATE_LIMIT_ATTEMPTS) {
			$errors[] = 'send-rate-limit-attempts-out-of-range';
		}

		if ($sendRateLimitPeriodSeconds < self::MIN_SEND_RATE_LIMIT_PERIOD_SECONDS || $sendRateLimitPeriodSeconds > self::MAX_SEND_RATE_LIMIT_PERIOD_SECONDS) {
			$errors[] = 'send-rate-limit-period-seconds-out-of-range';
		}

		if (strlen($eMailTemplate) > self::MAX_EMAIL_TEMPLATE_LENGTH) {
			$errors[] = 'email-template-too-long';
		}

		return $errors;
	}
}

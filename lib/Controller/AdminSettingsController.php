<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Service\ConfigurableAppSettings;
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class AdminSettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigurableAppSettings $settings,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get all admin settings.
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function get(): JSONResponse {
		return new JSONResponse($this->settings->getAllSettings());
	}

	/**
	 * Update admin settings.
	 */
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function update(
		?int $codeLength = null,
		?int $codeValidMinutes = null,
		?int $maxVerificationAttempts = null,
		?bool $useAlphanumericCodes = null,
		?int $rateLimitAttempts = null,
		?int $rateLimitPeriodMinutes = null,
		?bool $includeEmailHeader = null,
		?array $allowedDomains = null,
		?bool $preferLdapEmail = null,
	): JSONResponse {
		if ($codeLength !== null) {
			$this->settings->setCodeLength($codeLength);
		}
		if ($codeValidMinutes !== null) {
			$this->settings->setCodeValidSeconds($codeValidMinutes * 60);
		}
		if ($maxVerificationAttempts !== null) {
			$this->settings->setMaxVerificationAttempts($maxVerificationAttempts);
		}
		if ($useAlphanumericCodes !== null) {
			$this->settings->setUseAlphanumericCodes($useAlphanumericCodes);
		}
		if ($rateLimitAttempts !== null) {
			$this->settings->setSendRateLimitAttempts($rateLimitAttempts);
		}
		if ($rateLimitPeriodMinutes !== null) {
			$this->settings->setSendRateLimitPeriodSeconds($rateLimitPeriodMinutes * 60);
		}
		if ($includeEmailHeader !== null) {
			$this->settings->setIncludeEmailHeader($includeEmailHeader);
		}
		if ($allowedDomains !== null) {
			$this->settings->setAllowedDomains($allowedDomains);
		}
		if ($preferLdapEmail !== null) {
			$this->settings->setPreferLdapEmail($preferLdapEmail);
		}

		return new JSONResponse([
			'success' => true,
			'settings' => $this->settings->getAllSettings(),
		]);
	}
}

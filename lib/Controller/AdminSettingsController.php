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
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IRequest;

final class AdminSettingsController extends ALoginSetupController {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppSettings $appSettings,
		private readonly SettingsValidator $validator,
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
		$errors = $this->validator->validate($codeLength, $codeValidMinutes, $resendMinutes, $eMailSubject, $eMailTemplate);
		if (!empty($errors)) {
			return new JSONResponse(['errors' => $errors], Http::STATUS_BAD_REQUEST);
		}

		$this->appSettings->setCodeLength($codeLength);
		$this->appSettings->setCodeValidMinutes($codeValidMinutes);
		$this->appSettings->setResendMinMinutes($resendMinutes);
		$this->appSettings->setEMailSubject($eMailSubject);
		$this->appSettings->setEMailTemplate($eMailTemplate);

		return $this->currentSettingsResponse();
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

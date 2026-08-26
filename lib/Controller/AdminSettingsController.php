<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * This class may NOT be renamed to e.g. 'AdminSettings.php' since Nextcloud USES the class suffix 'Controller'.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCA\TwoFactorEMail\Settings\AdminSettings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Not an ALoginSetupController, although its two siblings are. That base class is
 * empty: its whole effect is that Nextcloud skips the two-factor gate for it while
 * a user who needs a second factor has no provider to complete it with. The
 * enrolment step needs that, the admin settings never do — and with it these routes
 * would be reachable with the password alone while an admin is still being set up.
 */
final class AdminSettingsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IAppSettings $appSettings,
		private readonly SettingsValidator $validator,
	) {
		parent::__construct($appName, $request);
	}

	#[FrontpageRoute(verb: 'POST', url: '/admin/save')]
	#[AuthorizedAdminSetting(settings: AdminSettings::class)]
	public function save(
		int $codeLength,
		int $codeValidMinutes,
		string $eMailTemplate,
		string $eMailSubject,
		int $resendMinutes,
	): JSONResponse {
		// No baseline: the form shows every field and sends every field, so a text that
		// arrives here was submitted by the admin looking at it, whether they typed it
		// or not. The occ command sets one key at a time and can tell the difference.
		$errors = $this->validator->validate(
			$codeLength,
			$codeValidMinutes,
			$resendMinutes,
			$eMailSubject,
			$eMailTemplate,
		);
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

	#[FrontpageRoute(verb: 'POST', url: '/admin/reset')]
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

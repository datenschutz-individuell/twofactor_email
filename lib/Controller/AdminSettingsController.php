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
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IAppConfig;
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

	#[AuthorizedAdminSetting(settings: 'OCA\TwoFactorEMail\Settings\AdminSettings')]
	public function update(int $codeValidMinutes): JSONResponse {
        $this->appConfig->setValueInt(Application::APP_ID, 'code_valid_minutes', $codeValidMinutes);

		return new JSONResponse([
			'codeValidMinutes' => $this->appSettings->getCodeValidMinutes(),
		]);
	}
}

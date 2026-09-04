<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * This class may NOT be renamed to e.g. 'State.php' since Nextcloud USES the class suffix 'Controller'.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Service\EMailAddressSource;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IRequest;
use OCP\IUserSession;

final class StateController extends ALoginSetupController {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IStateManager $stateManager,
		private readonly EMailAddressSource $addressSource,
	) {
		parent::__construct($appName, $request);
	}

	#[FrontpageRoute(verb: 'POST', url: '/state/save')]
	#[NoAdminRequired]
	#[PasswordConfirmationRequired]
	public function save(bool $state): JSONResponse {
		$user = $this->userSession->getUser();

		if ($user === null) {
			return new JSONResponse([
				'error' => 'no-user',
			], Http::STATUS_UNAUTHORIZED);
		}

		// Saving an unchanged state must not dispatch another StateChanged
		// event — each dispatch creates an activity entry.
		if ($state === $this->stateManager->isEnabled($user)) {
			return new JSONResponse([
				'enabled' => $state,
			]);
		}

		if ($state) {
			if ($this->addressSource->getAddress($user) === null) {
				return new JSONResponse([
					'enabled' => false,
					'error' => 'no-email',
				], Http::STATUS_PRECONDITION_FAILED);
			}
			$this->stateManager->enable($user);
		} else {
			$this->stateManager->disable($user);
		}

		return new JSONResponse([
			'enabled' => $state,
		]);
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * This class may NOT be renamed to e.g. 'Challenge.php' since Nextcloud USES the class suffix 'Controller'.
 * See routes.php.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoTwoFactorRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IRequest;
use OCP\IUserSession;

final class ChallengeController extends ALoginSetupController {

	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly ILoginChallenge $challenge,
		private readonly IStateManager $stateManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Send a fresh challenge code on the user's explicit request.
	 *
	 * Reachable during the half-authenticated 2FA-pending state: NoTwoFactorRequired
	 * lets the request pass TwoFactorMiddleware. Flooding is prevented by the resend
	 * cooldown in the service (no email is sent before it elapses), so no extra
	 * request rate limit is needed here.
	 */
	#[NoAdminRequired]
	#[NoTwoFactorRequired]
	public function resend(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'no-user'], Http::STATUS_UNAUTHORIZED);
		}
		// Only the email provider's own challenge may be resent here
		if (!$this->stateManager->isEnabled($user)) {
			return new JSONResponse(['error' => 'not-enabled'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->challenge->resendChallenge($user);
			return new JSONResponse(['status' => 'sent']);
		} catch (ResendTooSoon $e) {
			return new JSONResponse(
				['error' => 'too-soon', 'retryAfter' => $e->retryAfterSeconds],
				Http::STATUS_TOO_MANY_REQUESTS,
			);
		} catch (EMailNotSet) {
			return new JSONResponse(['error' => 'no-email'], Http::STATUS_BAD_REQUEST);
		} catch (SendEMailFailed) {
			return new JSONResponse(['error' => 'send-failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}

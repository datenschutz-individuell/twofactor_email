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
use OCA\TwoFactorEMail\Exception\SendRateLimited;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoTwoFactorRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Authentication\TwoFactorAuth\ALoginSetupController;
use OCP\IRequest;
use OCP\IUserSession;

final class ChallengeController extends ALoginSetupController {

	private const BRUTE_FORCE_ACTION = 'twofactorEmailResend';

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
	 * The service cooldown is the configurable source of truth. The rate limit
	 * is an atomic backstop against concurrent bursts: period 60s matches the
	 * minimum allowed resend cooldown (currently 1 minute), so it never rejects a
	 * legitimately allowed resend.
	 *
	 * The annotation below duplicates the #[NoTwoFactorRequired] attribute on purpose.
	 * Nextcloud 32 and 33 read the exemption from the docblock only. Their
	 * TwoFactorMiddleware calls hasAnnotation('NoTwoFactorRequired'). The attribute
	 * was added in Nextcloud 34. With the attribute alone, a resend on 32 or 33 is
	 * answered with a redirect to the provider selection, so no new code is sent.
	 *
	 * Do not remove the annotation on this branch. The 3.3 line exists to serve
	 * Nextcloud 32, so its lowest supported server will never be 34.
	 *
	 * @NoTwoFactorRequired
	 */
	#[NoAdminRequired]
	#[NoTwoFactorRequired]
	#[UserRateLimit(limit: 1, period: 60)]
	#[BruteForceProtection(action: self::BRUTE_FORCE_ACTION)]
	public function resend(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new JSONResponse(['error' => 'no-user'], Http::STATUS_UNAUTHORIZED);
		}
		if (!$this->stateManager->isEnabled($user)) {
			return new JSONResponse(['error' => 'not-enabled'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$this->challenge->resendChallenge($user);
			return new JSONResponse(['status' => 'sent']);
		} catch (ResendTooSoon $e) {
			$response = new JSONResponse(
				['error' => 'too-soon', 'retryAfter' => $e->retryAfterSeconds],
				Http::STATUS_TOO_MANY_REQUESTS,
			);
			$response->throttle(['action' => self::BRUTE_FORCE_ACTION]);
			return $response;
		} catch (SendRateLimited $e) {
			// No brute-force strike: the app declined to send, the request itself was
			// legitimate, and this endpoint already allows only one of them a minute.
			return new JSONResponse(
				['error' => 'too-soon', 'retryAfter' => $e->retryAfterSeconds],
				Http::STATUS_TOO_MANY_REQUESTS,
			);
		} catch (EMailNotSet) {
			return new JSONResponse(['error' => 'no-email'], Http::STATUS_BAD_REQUEST);
		} catch (SendEMailFailed) {
			$response = new JSONResponse(['error' => 'send-failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
			$response->throttle(['action' => self::BRUTE_FORCE_ACTION]);
			return $response;
		}
	}
}

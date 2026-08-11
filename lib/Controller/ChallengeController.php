<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/*
 * This class may NOT be renamed to e.g. 'Challenge.php' since Nextcloud USES the class suffix 'Controller'.
 */

namespace OCA\TwoFactorEMail\Controller;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
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
	 * The annotation duplicates the attribute below on purpose. Nextcloud 33
	 * exempts a route from the two-factor gate through the docblock only — its
	 * TwoFactorMiddleware asks hasAnnotation('NoTwoFactorRequired') — while the
	 * attribute exists from Nextcloud 34 on. With the attribute alone, a resend on
	 * Nextcloud 33 gets a redirect to the provider selection instead of a new code.
	 * Drop the annotation once the app requires Nextcloud 34.
	 *
	 * The attribute is the other half of the same shim, and it is why psalm.xml
	 * suppresses UndefinedAttributeClass for this one class: analysed against the OCP
	 * of the oldest supported server it does not exist yet. PHP resolves an attribute
	 * class only when something reflects on it, so Nextcloud 33 never asks and never
	 * fails. That suppression goes when this annotation does.
	 *
	 * @NoTwoFactorRequired
	 */
	#[FrontpageRoute(verb: 'POST', url: '/challenge/resend')]
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
		} catch (EMailNotSet) {
			return new JSONResponse(['error' => 'no-email'], Http::STATUS_BAD_REQUEST);
		} catch (SendEMailFailed) {
			$response = new JSONResponse(['error' => 'send-failed'], Http::STATUS_INTERNAL_SERVER_ERROR);
			$response->throttle(['action' => self::BRUTE_FORCE_ACTION]);
			return $response;
		}
	}
}

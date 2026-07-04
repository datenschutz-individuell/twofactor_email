<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Provider;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\IEMailAddressMasker;
use OCA\TwoFactorEMail\Service\ILoginChallenge;
use OCA\TwoFactorEMail\Service\IStateManager;
use OCA\TwoFactorEMail\Settings\PersonalSettings;
use OCP\AppFramework\Services\IInitialState;
use OCP\Authentication\TwoFactorAuth\IActivatableAtLogin;
use OCP\Authentication\TwoFactorAuth\IActivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\IDeactivatableByAdmin;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Authentication\TwoFactorAuth\IProvidesIcons;
use OCP\Authentication\TwoFactorAuth\IProvidesPersonalSettings;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use OCP\Template\TemplateNotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class TwoFactorEMail implements IProvider, IProvidesIcons, IProvidesPersonalSettings, IDeactivatableByAdmin, IActivatableByAdmin, IActivatableAtLogin {

	public function __construct(
		private readonly IEMailAddressMasker $emailAddressMasker,
		private readonly ITemplateManager $templateManager,
		private readonly IL10N $l10n,
		private readonly IInitialState $initialStateService,
		private readonly IURLGenerator $urlGenerator,
		private readonly ContainerInterface $container,
		private readonly ILoginChallenge $challengeService,
		private readonly IStateManager $stateManager,
		private readonly IAppSettings $settings,
	) {
	}

	public function getId(): string {
		return 'email';
	}

	public function getDisplayName(): string {
		return 'Email';
	}

	public function getDescription(): string {
		return $this->l10n->t('Authenticate by email');
	}

	/**
	 * Get the template for rendering the 2FA challenge view.
	 * Nextcloud calls this when a user with enabled email 2FA logs in.
	 * It sends a challenge code by email, unless a still-valid code exists —
	 * so reloading the page does not send another email.
	 */
	public function getTemplate(IUser $user): ITemplate {
		try {
			$template = $this->templateManager->getTemplate(Application::APP_ID, 'LoginChallenge');
		} catch (TemplateNotFoundException $e) {
			throw new RuntimeException('LoginChallenge template not found', previous: $e);
		}

		$newCodeWasSent = false;
		$error = null;

		try {
			$newCodeWasSent = $this->challengeService->sendChallenge($user);
		} catch (EMailNotSet) {
			$error = 'no-email';
		} catch (SendEMailFailed) {
			$error = 'send-failed';
		}

		$template->assign('codeLength', $this->settings->getCodeLength());
		$template->assign('newCodeWasSent', $newCodeWasSent);
		$template->assign('error', $error);
		$template->assign('resendCooldown', $this->settings->getResendCooldownSeconds());
		$template->assign('resendAvailableIn', $this->challengeService->secondsUntilResendAllowed($user));

		return $template;
	}

	public function verifyChallenge(IUser $user, string $challenge): bool {
		return $this->challengeService->verifyChallenge($user, $challenge);
	}

	/**
	 * Decides whether 2FA is enabled for the given user.
	 * Nextcloud keeps an enabled/disabled state for each two-factor provider and each user.
	 * They are read from and written to the DB table oc_twofactor_providers.
	 * If no entry exists for the provider "email" and the given user, this method is called during the login flow.
	 * Nextcloud then saves the current state.
	 */
	public function isTwoFactorAuthEnabledForUser(IUser $user): bool {
		return $this->stateManager->isEnabled($user);
	}

	public function getLightIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	public function getDarkIcon(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	public function getPersonalSettings(IUser $user): IPersonalProviderSettings {
		$email = $user->getEMailAddress() ?? '';
		$this->initialStateService->provideInitialState('enabled', $this->stateManager->isEnabled($user));
		$this->initialStateService->provideInitialState('hasEmail', $email !== '');
		$this->initialStateService->provideInitialState('email', $email);
		return new PersonalSettings(
			$this->templateManager,
		);
	}

	public function disableFor(IUser $user): void {
		$this->stateManager->disable($user, true);
	}

	public function enableFor(IUser $user): void {
		$this->stateManager->enable($user, true);
	}

	/**
	 * @throws NotFoundExceptionInterface
	 * @throws ContainerExceptionInterface
	 */
	public function getLoginSetup(IUser $user): ILoginSetupProvider {
		$maskedEmail = $this->emailAddressMasker->maskForUI($user->getEMailAddress() ?? '');
		$this->initialStateService->provideInitialState('maskedEmail', $maskedEmail);
		return $this->container->get(LoginSetup::class);
	}
}

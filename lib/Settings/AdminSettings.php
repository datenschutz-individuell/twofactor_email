<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\EMailDefaults;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

final class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private readonly IAppSettings $appSettings,
		private readonly EMailDefaults $eMailDefaults,
		private readonly IInitialState $initialState,
		private readonly IL10N $l10n,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('codeLength', $this->appSettings->getCodeLength());
		$this->initialState->provideInitialState('codeValidMinutes', $this->appSettings->getCodeValidMinutes());
		$this->initialState->provideInitialState('eMailSubject', $this->appSettings->getEMailSubject());
		$this->initialState->provideInitialState('eMailHeading', $this->appSettings->getEMailHeading());
		$this->initialState->provideInitialState('eMailTemplate', $this->appSettings->getEMailTemplate());
		$this->initialState->provideInitialState('eMailFooter', $this->appSettings->getEMailFooter());
		// Localized default texts, shown as placeholders in the empty form fields
		$this->initialState->provideInitialState('eMailDefaults', [
			'eMailSubject' => $this->eMailDefaults->subject(),
			'eMailHeading' => $this->eMailDefaults->heading(),
			'eMailTemplate' => $this->eMailDefaults->body(),
			'eMailFooter' => $this->l10n->t('Standard footer of this Nextcloud instance'),
		]);

		return new TemplateResponse(Application::APP_ID, 'AdminSettings', renderAs: TemplateResponse::RENDER_AS_BLANK);
	}

	public function getSection(): string {
		return 'security';
	}

	public function getPriority(): int {
		return 30;
	}

	public function getName(): ?string {
		return $this->l10n->t('Email');
	}

	// both required by Nextcloud at runtime via IDelegatedSettings
	/** @noinspection PhpUnused */
	public function getAuthorizedGroupIds(): array {
		return []; // real admins only
	}
	public function getAuthorizedAppConfig(): array {
		return [];  // no app config keys delegated to non-admins
	}
}

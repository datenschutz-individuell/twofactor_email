<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\AppSettingsDefaults;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

final class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private readonly IAppSettings $appSettings,
		private readonly AppSettingsDefaults $appSettingsDefaults,
		private readonly IInitialState $initialState,
		private readonly IL10N $l10n,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('codeLength', $this->appSettings->getCodeLength());
		$this->initialState->provideInitialState('codeValidMinutes', $this->appSettings->getCodeValidMinutes());
		$this->initialState->provideInitialState('eMailSubject', $this->appSettings->getEMailSubject());
		$this->initialState->provideInitialState('eMailTemplate', $this->appSettings->getEMailTemplate());
		$this->initialState->provideInitialState('eMailFooter', $this->appSettings->getEMailFooter());
		// Localized default texts, shown as placeholders in the empty form fields
		$this->initialState->provideInitialState('eMailDefaults', [
			'eMailSubject' => $this->appSettingsDefaults->eMailSubject(),
			'eMailTemplate' => $this->appSettingsDefaults->eMailBody(),
			'eMailFooter' => $this->appSettingsDefaults->eMailFooter(),
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

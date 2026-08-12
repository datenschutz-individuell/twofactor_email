<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

final readonly class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private IAppSettings $appSettings,
		private IInitialState $initialState,
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('codeLength', $this->appSettings->getCodeLength());
		$this->initialState->provideInitialState('codeValidMinutes', $this->appSettings->getCodeValidMinutes());
		$this->initialState->provideInitialState('codeResendMinutes', $this->appSettings->getResendMinMinutes());
		$this->initialState->provideInitialState('eMailSubject', $this->appSettings->getEMailSubject());
		$this->initialState->provideInitialState('eMailTemplate', $this->appSettings->getEMailTemplate());
		// Localized default texts, shown as placeholders in the empty form fields
		$this->initialState->provideInitialState('eMailSubjectDefault', $this->appSettings->getDefaultEMailSubject());
		$this->initialState->provideInitialState('eMailTemplateDefault', $this->appSettings->getDefaultEMailBody());
		// Numeric limits, so validation messages can name the allowed range
		$this->initialState->provideInitialState('limits', SettingsValidator::getLimits());

		return new TemplateResponse(Application::APP_ID, 'AdminSettings', renderAs: TemplateResponse::RENDER_AS_BLANK);
	}

	#[\Override]
	public function getSection(): string {
		return 'security';
	}

	#[\Override]
	public function getPriority(): int {
		return 30;
	}

	#[\Override]
	public function getName(): ?string {
		return $this->l10n->t('Email');
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return []; // no app config keys delegated to non-admins
	}
}

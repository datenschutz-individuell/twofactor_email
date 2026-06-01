<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private IAppSettings $appSettings,
		private IInitialState $initialState,
		private IL10N $l10n,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('codeLength', $this->appSettings->getCodeLength());
		$this->initialState->provideInitialState('codeValidMinutes', $this->appSettings->getCodeValidMinutes());
		$this->initialState->provideInitialState('sendRateLimitAttempts', $this->appSettings->getSendRateLimitAttempts());
		$this->initialState->provideInitialState('sendRateLimitPeriodSeconds', $this->appSettings->getSendRateLimitPeriodSeconds());
		$this->initialState->provideInitialState('eMailTemplate', $this->appSettings->getEMailTemplate());

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

	public function getAuthorizedGroupIds(): array {
		return []; // an empty array means "real admins only, no delegated/group admins"
	}

	public function getAuthorizedAppConfig(): array {
		return [];  // no app config keys delegated to non-admins
	}
}

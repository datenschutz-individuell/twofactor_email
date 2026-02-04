<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\ConfigurableAppSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IL10N;
use OCP\Settings\IDelegatedSettings;

class AdminSettings implements IDelegatedSettings {
	public function __construct(
		private ConfigurableAppSettings $settings,
		private IInitialState $initialState,
		private IL10N $l10n,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initialState->provideInitialState('adminSettings', $this->settings->getAllSettings());

		return new TemplateResponse(Application::APP_ID, 'AdminSettings');
	}

	public function getSection(): string {
		return 'security';
	}

	public function getPriority(): int {
		return 50;
	}

	public function getName(): ?string {
		return $this->l10n->t('Two-Factor Email');
	}

	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => ['/.*/'], // Allow all app config keys for this app
		];
	}
}

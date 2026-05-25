<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Service\ConstantAppSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {
    public function __construct(
        private ConstantAppSettings $constantAppSettings,
        private IInitialState $initialState,
        private IAppConfig $appConfig,
        private IL10N $l10n,
    ) {
    }

    public function getForm(): TemplateResponse {
        $this->initialState->provideInitialState('codeValidMinutes', $this->appConfig->getValueInt(Application::APP_ID, 'code_valid_minutes', $this->constantAppSettings->getCodeValidMinutes()));
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
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Settings;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Authentication\TwoFactorAuth\IPersonalProviderSettings;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use OCP\Template\TemplateNotFoundException;
use RuntimeException;

final class PersonalSettings implements IPersonalProviderSettings {

	public function __construct(
		private readonly ITemplateManager $templateManager,
	) {
	}

	public function getBody(): ITemplate {
		try {
			return $this->templateManager->getTemplate(Application::APP_ID, 'PersonalSettings');
		} catch (TemplateNotFoundException $e) {
			throw new RuntimeException('PersonalSettings template not found', previous: $e);
		}
	}
}

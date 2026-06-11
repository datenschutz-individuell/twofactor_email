<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Provider;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Authentication\TwoFactorAuth\ILoginSetupProvider;
use OCP\IURLGenerator;
use OCP\Template\ITemplate;
use OCP\Template\ITemplateManager;
use OCP\Template\TemplateNotFoundException;
use RuntimeException;

final class LoginSetup implements ILoginSetupProvider {

	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly ITemplateManager $templateManager,
	) {
	}

	public function getBody(): ITemplate {
		try {
			$template = $this->templateManager->getTemplate(Application::APP_ID, 'LoginSetup');
		} catch (TemplateNotFoundException $e) {
			throw new RuntimeException('PersonalSettings template not found', previous: $e);
		}
		$template->assign('urlGenerator', $this->urlGenerator);
		return $template;
	}
}

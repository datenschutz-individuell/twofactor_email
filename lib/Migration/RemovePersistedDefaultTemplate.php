<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Migration;

use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * In 3.1.x the admin UI showed the default email body as the field value and
 * saving any admin setting persisted it, so most instances stored the 3.1
 * default text without ever customizing it. Since 3.2.0 a non-empty stored
 * template suppresses the current default — and with it the {logo} token, so
 * the instance logo vanished from the challenge emails (issue #109).
 *
 * This step deletes the stored template if and only if it is byte-identical
 * to the 3.1 default text (which was never localized), so the current
 * default applies again. Real customizations are left untouched.
 */
final class RemovePersistedDefaultTemplate implements IRepairStep {

	// The hardcoded default body of twofactor_email 3.1.0 – 3.1.2, verbatim
	private const OLD_DEFAULT_TEMPLATE
		= "Your two-factor authentication code is: {code}\n\n"
		. 'If you tried to login, please enter that code on {cloud}. '
		. 'If you did not, somebody else did and knows your email address '
		. 'or username – and your password!';

	public function __construct(
		private readonly IAppSettings $appSettings,
	) {
	}

	public function getName(): string {
		return 'Remove the email template that 3.1.x persisted without customization';
	}

	public function run(IOutput $output): void {
		if ($this->appSettings->getEMailTemplate() !== self::OLD_DEFAULT_TEMPLATE) {
			return;
		}
		// Empty means: use the localized default (which contains {logo})
		$this->appSettings->setEMailTemplate('');
		$output->info('Removed the persisted 3.1 default email template; the current default (with {logo}) applies again.');
	}
}

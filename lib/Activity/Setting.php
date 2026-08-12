<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Activity;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Activity\ISetting;
use OCP\IL10N;

final readonly class Setting implements ISetting {

	public function __construct(
		private IL10N $l10n,
	) {
	}

	#[\Override]
	public function canChangeMail(): bool {
		return false;
	}

	#[\Override]
	public function canChangeStream(): bool {
		return false;
	}

	#[\Override]
	public function getIdentifier(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Email');
	}

	#[\Override]
	public function getPriority(): int {
		return 10;
	}

	#[\Override]
	public function isDefaultEnabledMail(): bool {
		return true;
	}

	#[\Override]
	public function isDefaultEnabledStream(): bool {
		return true;
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\IL10N;

/**
 * Localized default texts for the parts of the challenge email.
 *
 * These are used whenever the corresponding admin setting is empty, and they
 * are shown as placeholders in the admin settings form. All texts are
 * templates: the placeholders {code}, {user}, {cloud} and {validity} are
 * replaced when the email is sent.
 */
final class EMailDefaults {
	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	public function subject(): string {
		return $this->l10n->t('Login attempt for %s', ['{user} @ {cloud}']);
	}

	public function heading(): string {
		return $this->l10n->t('Your two-factor authentication code is: %s', ['{code}']);
	}

	public function body(): string {
		return $this->l10n->t("Your two-factor authentication code is: {code}\n\nIf you tried to login, please enter that code on {cloud}. If you did not, somebody else did and knows your email address or username – and your password!");
	}
}

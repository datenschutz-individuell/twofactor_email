<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

use OCP\IL10N;

final class AppSettingsDefaults {

	// Config keys used to store settings in the app config
	public const CONFIG_KEY_CODE_LENGTH = 'code_length';
	public const CONFIG_KEY_CODE_VALID_MINUTES = 'code_valid_minutes';
	public const CONFIG_KEY_EMAIL_SUBJECT = 'email_subject';
	public const CONFIG_KEY_EMAIL_TEMPLATE = 'email_template';
	public const CONFIG_KEY_EMAIL_FOOTER = 'email_footer';

	// Default values — used when no value has been stored in the app config
	public const CODE_LENGTH = 6;
	public const CODE_VALID_MINUTES = 10;

	// For all email template parts an empty string means: use the localized
	// default text (the methods below), or the theming footer respectively.
	public const EMAIL_SUBJECT = '';
	public const EMAIL_TEMPLATE = '';
	public const EMAIL_FOOTER = '';

	public function __construct(
		private readonly IL10N $l10n,
	) {
	}

	/*
	 * Localized default texts for the parts of the challenge email. They are
	 * used whenever the corresponding admin setting is empty, and they are
	 * shown as placeholders in the admin settings form. All texts are
	 * templates: the placeholders {code}, {user}, {cloud} and {validity} are
	 * replaced when the email is sent.
	 */

	public function eMailSubject(): string {
		return $this->l10n->t('Login attempt for %s', ['{user} @ {cloud}']);
	}

	public function eMailBody(): string {
		// The {logo} and {code} structure is kept outside of the translatable
		// strings so translations cannot break it
		return "{logo}\n\n"
			. $this->l10n->t('Someone is trying to log in to {cloud} with your account {user}. Since two-factor authentication is enabled for your account, a confirmation is required. Email was chosen as the second factor, so you are receiving this code:')
			. "\n\n{code}\n\n"
			. $this->l10n->t('This code is valid for {validity} minutes. Enter it only if you tried to log in yourself. Otherwise, treat this message as an attack attempt and inform your administrator.');
	}
}

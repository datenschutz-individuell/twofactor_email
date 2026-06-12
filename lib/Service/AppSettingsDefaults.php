<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

use OCP\Defaults;
use OCP\IL10N;
use OCP\L10N\IFactory;

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
		private readonly Defaults $themingDefaults,
		private readonly IFactory $l10nFactory,
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
		// strings so translations cannot break it; every chunk is a complete
		// sentence so each can be translated on its own.
		return "{logo}\n\n"
			. $this->l10n->t('Your two-factor authentication code for {cloud} is:')
			. "\n\n{code}\n\n"
			. $this->l10n->t('The code is valid for {validity} minutes.')
			. ' '
			. $this->l10n->t('If you did not try to log in, somebody else knows your username and your password — change your password and inform your administrator.');
	}

	/**
	 * The text the server renders when no custom footer is set — same
	 * composition as \OC\Mail\EMailTemplate::addFooter(), as a single line.
	 * Only used as a hint in the admin settings form; the actual default
	 * footer is rendered by the server.
	 */
	public function eMailFooter(): string {
		$slogan = $this->themingDefaults->getSlogan();
		return $this->themingDefaults->getName()
			. ($slogan !== '' ? ' - ' . $slogan : '')
			. ' – '
			// This sentence is part of the server's footer; reuse its translation
			. $this->l10nFactory->get('lib')->t('This is an automatically sent email, please do not reply.');
	}
}

<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IL10N;

final class AppSettings implements IAppSettings {

	// Config keys used to store the settings in the app config
	private const KEY_CODE_LENGTH = 'code_length';
	private const KEY_CODE_VALID_MINUTES = 'code_valid_minutes';
	private const KEY_RESEND_MIN_SECONDS = 'resend_min_seconds';
	private const KEY_EMAIL_SUBJECT = 'email_subject';
	private const KEY_EMAIL_TEMPLATE = 'email_template';

	// Default values — used when no value has been stored in the app config.
	// For the email template parts an empty string means: use the localized
	// default text (the getDefault* methods below).
	private const DEFAULT_CODE_LENGTH = 6;
	private const DEFAULT_CODE_VALID_MINUTES = 10;
	private const DEFAULT_RESEND_MIN_SECONDS = 60;
	private const DEFAULT_EMAIL_SUBJECT = '';
	private const DEFAULT_EMAIL_TEMPLATE = '';

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
	) {
	}

	public function getCodeLength(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, self::KEY_CODE_LENGTH, self::DEFAULT_CODE_LENGTH);
	}

	public function getCodeValidMinutes(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, self::KEY_CODE_VALID_MINUTES, self::DEFAULT_CODE_VALID_MINUTES);
	}

	public function getResendMinSeconds(): int {
		return $this->appConfig->getValueInt(Application::APP_ID, self::KEY_RESEND_MIN_SECONDS, self::DEFAULT_RESEND_MIN_SECONDS);
	}

	public function getEMailSubject(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_EMAIL_SUBJECT, self::DEFAULT_EMAIL_SUBJECT);
	}

	public function getEMailTemplate(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::KEY_EMAIL_TEMPLATE, self::DEFAULT_EMAIL_TEMPLATE);
	}

	public function getDefaultEMailSubject(): string {
		return $this->l10n->t('Login attempt for %s', ['{user} @ {cloud}']);
	}

	public function getDefaultEMailBody(): string {
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

	public function setCodeLength(int $codeLength): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_CODE_LENGTH, $codeLength);
	}

	public function setCodeValidMinutes(int $codeValidMinutes): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_CODE_VALID_MINUTES, $codeValidMinutes);
	}

	public function setResendMinSeconds(int $resendMinSeconds): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_RESEND_MIN_SECONDS, $resendMinSeconds);
	}

	public function setEMailSubject(string $subject): void {
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_EMAIL_SUBJECT, $subject);
	}

	public function setEMailTemplate(string $body): void {
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_EMAIL_TEMPLATE, $body);
	}

	public function resetToDefaults(): void {
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_CODE_LENGTH);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_CODE_VALID_MINUTES);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_RESEND_MIN_SECONDS);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_EMAIL_SUBJECT);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_EMAIL_TEMPLATE);
	}
}

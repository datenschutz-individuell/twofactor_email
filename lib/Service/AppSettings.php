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
use Psr\Log\LoggerInterface;

final class AppSettings implements IAppSettings {

	// Config keys used to store the settings in the app config. Public so the repair
	// step can read the raw values, which the getters here correct or hide.
	public const KEY_CODE_LENGTH = 'code_length';
	public const KEY_CODE_VALID_MINUTES = 'code_valid_minutes';
	public const KEY_RESEND_MIN_MINUTES = 'resend_min_minutes';
	public const KEY_EMAIL_SUBJECT = 'email_subject';
	public const KEY_EMAIL_TEMPLATE = 'email_template';

	// Default values — used when no value has been stored in the app config.
	// For the email template parts an empty string means: use the localized
	// default text (the getDefault* methods below).
	// The int defaults are public so the occ settings command can display them.
	public const DEFAULT_CODE_LENGTH = 6;
	public const DEFAULT_CODE_VALID_MINUTES = 10;
	public const DEFAULT_RESEND_MIN_MINUTES = 1;
	private const DEFAULT_EMAIL_SUBJECT = '';
	private const DEFAULT_EMAIL_TEMPLATE = '';

	/** @var array<string, true> conditions already reported for this instance */
	private array $reported = [];

	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * A stored value is read many times per request — the code validity alone once
	 * per rendered mail part. The condition is the same every time, so say it once.
	 */
	private function reportOnce(string $condition, string $message): void {
		if (isset($this->reported[$condition])) {
			return;
		}
		$this->reported[$condition] = true;
		$this->logger->warning($message);
	}

	/**
	 * Text that is not valid UTF-8 counts as unset, so the localized default applies.
	 * Such a text cannot be written through this app — SettingsValidator refuses it —
	 * so it comes from `occ config:app:set` or a restored database, and it would go
	 * out as broken characters in a mail nobody can read. This is the read-side bound
	 * of the numeric settings applied to the texts.
	 */
	private function usableText(string $value, string $setting): string {
		if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
			return $value;
		}
		$this->reportOnce(
			$setting,
			'The stored ' . $setting . ' is not valid UTF-8 and is ignored; the default text is used. '
			. 'Set it again with occ twofactor_email:settings.',
		);
		return '';
	}

	/**
	 * Clamped on READ: SettingsValidator cannot reach `occ config:app:set` or a
	 * database restored from a release with different bounds, and the value decides
	 * how strong the second factor is. At length 1 it has ten possible values.
	 */
	#[\Override]
	public function getCodeLength(): int {
		return $this->clamp(
			$this->appConfig->getValueInt(Application::APP_ID, self::KEY_CODE_LENGTH, self::DEFAULT_CODE_LENGTH),
			SettingsValidator::MIN_CODE_LENGTH,
			SettingsValidator::MAX_CODE_LENGTH,
			'code length',
		);
	}

	#[\Override]
	public function getCodeValidMinutes(): int {
		return $this->clamp(
			$this->appConfig->getValueInt(Application::APP_ID, self::KEY_CODE_VALID_MINUTES, self::DEFAULT_CODE_VALID_MINUTES),
			SettingsValidator::MIN_CODE_VALID_MINUTES,
			SettingsValidator::MAX_CODE_VALID_MINUTES,
			'code validity',
		);
	}

	#[\Override]
	public function getResendMinMinutes(): int {
		return $this->clamp(
			$this->appConfig->getValueInt(Application::APP_ID, self::KEY_RESEND_MIN_MINUTES, self::DEFAULT_RESEND_MIN_MINUTES),
			SettingsValidator::MIN_RESEND_MINUTES,
			SettingsValidator::MAX_RESEND_MINUTES,
			'resend cooldown',
		);
	}

	private function clamp(int $value, int $min, int $max, string $name): int {
		$clamped = max($min, min($max, $value));
		if ($clamped !== $value) {
			// Without this line, a value written past the validator is invisible:
			// every read, including occ twofactor_email:settings, shows the clamped one.
			$this->reportOnce(
				$name,
				'The stored ' . $name . ' (' . $value . ') is outside the allowed range; ' . $clamped
				. ' is used instead. Set it again with occ twofactor_email:settings.',
			);
		}
		return $clamped;
	}

	#[\Override]
	public function getResendCooldownSeconds(): int {
		return $this->getResendMinMinutes() * 60;
	}

	#[\Override]
	public function getEMailSubject(): string {
		return $this->usableText(
			$this->appConfig->getValueString(Application::APP_ID, self::KEY_EMAIL_SUBJECT, self::DEFAULT_EMAIL_SUBJECT),
			'email subject',
		);
	}

	#[\Override]
	public function getEMailTemplate(): string {
		return $this->usableText(
			$this->appConfig->getValueString(Application::APP_ID, self::KEY_EMAIL_TEMPLATE, self::DEFAULT_EMAIL_TEMPLATE),
			'email body',
		);
	}

	#[\Override]
	public function getDefaultEMailSubject(): string {
		return $this->l10n->t('Login attempt for %s', ['{user} @ {cloud}']);
	}

	#[\Override]
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

	#[\Override]
	public function setCodeLength(int $codeLength): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_CODE_LENGTH, $codeLength);
	}

	#[\Override]
	public function setCodeValidMinutes(int $codeValidMinutes): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_CODE_VALID_MINUTES, $codeValidMinutes);
	}

	#[\Override]
	public function setResendMinMinutes(int $resendMinutes): void {
		$this->appConfig->setValueInt(Application::APP_ID, self::KEY_RESEND_MIN_MINUTES, $resendMinutes);
	}

	#[\Override]
	public function setEMailSubject(string $subject): void {
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_EMAIL_SUBJECT, $subject);
	}

	#[\Override]
	public function setEMailTemplate(string $body): void {
		$this->appConfig->setValueString(Application::APP_ID, self::KEY_EMAIL_TEMPLATE, $body);
	}

	#[\Override]
	public function resetToDefaults(): void {
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_CODE_LENGTH);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_CODE_VALID_MINUTES);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_RESEND_MIN_MINUTES);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_EMAIL_SUBJECT);
		$this->appConfig->deleteKey(Application::APP_ID, self::KEY_EMAIL_TEMPLATE);
	}
}

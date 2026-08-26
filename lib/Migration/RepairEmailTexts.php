<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Migration;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCA\TwoFactorEMail\Mail\LinkScanner;
use OCA\TwoFactorEMail\Service\AppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Names the settings that do not do what their admin intended, and removes the one
 * kind that cannot work at all. Every setting is read here as it is stored, so a
 * value that the app corrects on every read is visible exactly once: here.
 *
 * A text that is not valid UTF-8 is deleted: nothing can be substituted in it, and
 * AppSettings hides it on every read anyway, so repairing the row once beats a
 * warning on every mail.
 *
 * Everything else is only reported. A placeholder inside a web address puts a value
 * into a link, so EMailSender falls back to the default text and the code still
 * arrives. A body without {code} delivers no code, but it did not before this
 * release either. Deleting either one would throw away the admin\'s text
 * irrecoverably, and there is no backup key.
 *
 * Reads the raw values, since AppSettings already hides an unusable one.
 */
final readonly class RepairEmailTexts implements IRepairStep {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Check the configured two-factor email texts';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$this->checkText($output, AppSettings::KEY_EMAIL_SUBJECT, 'subject', false);
		$this->checkText($output, AppSettings::KEY_EMAIL_TEMPLATE, 'body', true);
		$this->checkNumber($output, AppSettings::KEY_CODE_LENGTH, 'code length',
			AppSettings::DEFAULT_CODE_LENGTH, SettingsValidator::MIN_CODE_LENGTH, SettingsValidator::MAX_CODE_LENGTH);
		$this->checkNumber($output, AppSettings::KEY_CODE_VALID_MINUTES, 'code validity',
			AppSettings::DEFAULT_CODE_VALID_MINUTES, SettingsValidator::MIN_CODE_VALID_MINUTES, SettingsValidator::MAX_CODE_VALID_MINUTES);
		$this->checkNumber($output, AppSettings::KEY_RESEND_MIN_MINUTES, 'resend cooldown',
			AppSettings::DEFAULT_RESEND_MIN_MINUTES, SettingsValidator::MIN_RESEND_MINUTES, SettingsValidator::MAX_RESEND_MINUTES);
	}

	/**
	 * A value outside its range is corrected on every read, so the app works — but it
	 * does not do what the admin wrote, and every reading of it shows the corrected
	 * one. This output is the one place that can say so.
	 */
	private function checkNumber(IOutput $output, string $key, string $name, int $default, int $min, int $max): void {
		$stored = $this->appConfig->getValueInt(Application::APP_ID, $key, $default);
		if ($stored < $min || $stored > $max) {
			$output->warning(
				'The stored ' . $name . ' (' . $stored . ') is outside the allowed range of ' . $min . ' to '
				. $max . '. The nearest allowed value is used instead. Set it with occ twofactor_email:settings.',
			);
		}
	}

	private function checkText(IOutput $output, string $key, string $part, bool $needsCode): void {
		$stored = $this->appConfig->getValueString(Application::APP_ID, $key, '');
		if ($stored === '') {
			return;
		}

		if (!mb_check_encoding($stored, 'UTF-8')) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
			$output->warning('The email ' . $part . ' was not valid text and has been reset to the default.');
			return;
		}

		// Everything that applies is reported, not only the first thing found: the
		// messages ask for different repairs, and an admin who fixes one and hears
		// about the next only at the following upgrade has been told half the story.
		if ($needsCode && !str_contains($stored, '{code}')) {
			$output->warning(
				'The email ' . $part . ' does not contain {code}. No mail delivers a code in the ' . $part . ', '
				. 'and no settings can be saved until this is fixed. Edit it with occ twofactor_email:settings.',
			);
		}

		// The same conditions SettingsValidator refuses, because each one blocks every
		// settings change until it is fixed — and this output is where an admin looks.
		if (mb_strlen($stored) > ($needsCode ? SettingsValidator::MAX_EMAIL_TEMPLATE_LENGTH : SettingsValidator::MAX_EMAIL_SUBJECT_LENGTH)) {
			$output->warning(
				'The email ' . $part . ' is longer than allowed. No settings can be saved until this is '
				. 'fixed. Edit it with occ twofactor_email:settings.',
			);
		}

		if (!$needsCode && preg_match('/[\r\n]/', $stored) === 1) {
			$output->warning(
				'The email ' . $part . ' contains a line break, which is not allowed in a mail header. '
				. 'No settings can be saved until this is fixed. Edit it with occ twofactor_email:settings.',
			);
		}

		if (LinkScanner::hasPlaceholderInUrl($stored)) {
			$output->warning(
				'The email ' . $part . ' puts a placeholder inside a web address. Such a mail would carry the '
				. 'code in a link, so the default text is sent instead. Fix it with occ twofactor_email:settings.',
			);
		}
	}
}

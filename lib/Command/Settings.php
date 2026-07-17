<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Command;

use OCA\TwoFactorEMail\Service\AppSettings;
use OCA\TwoFactorEMail\Service\IAppSettings;
use OCA\TwoFactorEMail\Service\SettingsValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows or changes the app-wide admin settings, validated by the same
 * rules as the admin settings web UI.
 */
final class Settings extends Command {

	private const INT_SETTINGS = ['code_length', 'code_valid_minutes', 'resend_min_minutes'];
	private const STRING_SETTINGS = ['email_subject', 'email_template'];

	public function __construct(
		private readonly IAppSettings $appSettings,
		private readonly SettingsValidator $validator,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$settings = implode(', ', [...self::INT_SETTINGS, ...self::STRING_SETTINGS]);
		$this
			->setName('twofactor_email:settings')
			->setDescription('Show or change the app-wide settings of the Two-Factor Email provider.')
			->addArgument('key', InputArgument::OPTIONAL, 'Setting to show or change: ' . $settings)
			->addArgument('value', InputArgument::OPTIONAL, 'New value for the setting; an empty email_subject or email_template means: use the localized default text')
			->addOption('reset', null, InputOption::VALUE_NONE, 'Reset all settings to their defaults');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$io = new SymfonyStyle($input, $output);
		$key = $input->getArgument('key');
		$value = $input->getArgument('value');

		if ($input->getOption('reset')) {
			if ($key !== null) {
				$io->error('The --reset option resets all settings and cannot be combined with a key. To reset a single text setting, set it to an empty string; for the numeric settings, set the default value.');
				return Command::INVALID;
			}
			$this->appSettings->resetToDefaults();
			$io->success('All settings were reset to their defaults.');
			return Command::SUCCESS;
		}

		if ($key === null) {
			$this->listSettings($io);
			return Command::SUCCESS;
		}

		if (!in_array($key, self::INT_SETTINGS, true) && !in_array($key, self::STRING_SETTINGS, true)) {
			$io->error('Unknown setting "' . $key . '". Valid settings are: ' . implode(', ', [...self::INT_SETTINGS, ...self::STRING_SETTINGS]) . '.');
			return Command::INVALID;
		}

		if ($value === null) {
			$output->writeln((string)$this->currentValue($key));
			return Command::SUCCESS;
		}

		return $this->setValue($io, $key, $value);
	}

	private function listSettings(SymfonyStyle $io): void {
		$emptyMeansDefault = '(empty — the localized default text is used)';
		$io->table(['Setting', 'Value', 'Default'], [
			['code_length', $this->appSettings->getCodeLength(), AppSettings::DEFAULT_CODE_LENGTH],
			['code_valid_minutes', $this->appSettings->getCodeValidMinutes(), AppSettings::DEFAULT_CODE_VALID_MINUTES],
			['resend_min_minutes', $this->appSettings->getCodeResendMinutes(), AppSettings::DEFAULT_RESEND_MIN_MINUTES],
			['email_subject', $this->appSettings->getEMailSubject() ?: $emptyMeansDefault, $this->preview($this->appSettings->getDefaultEMailSubject())],
			['email_template', $this->preview($this->appSettings->getEMailTemplate()) ?: $emptyMeansDefault, $this->preview($this->appSettings->getDefaultEMailBody())],
		]);
	}

	/**
	 * Shortens a possibly long, multi-line text to a single table-friendly line.
	 */
	private function preview(string $text): string {
		$singleLine = str_replace(["\r\n", "\n"], ' ⏎ ', $text);
		if (mb_strlen($singleLine) > 60) {
			return mb_substr($singleLine, 0, 59) . '…';
		}
		return $singleLine;
	}

	private function currentValue(string $key): int|string {
		return match ($key) {
			'code_length' => $this->appSettings->getCodeLength(),
			'code_valid_minutes' => $this->appSettings->getCodeValidMinutes(),
			'resend_min_minutes' => $this->appSettings->getCodeResendMinutes(),
			'email_subject' => $this->appSettings->getEMailSubject(),
			'email_template' => $this->appSettings->getEMailTemplate(),
		};
	}

	private function setValue(SymfonyStyle $io, string $key, string $value): int {
		if (in_array($key, self::INT_SETTINGS, true) && preg_match('/^\d+$/', $value) !== 1) {
			$io->error($key . ' must be a non-negative integer.');
			return Command::INVALID;
		}

		// Validate the full settings set with the new value in place, so all
		// rules stay in one place (SettingsValidator, shared with the web UI).
		$codeLength = $this->appSettings->getCodeLength();
		$codeValidMinutes = $this->appSettings->getCodeValidMinutes();
		$codeResendMinutes = $this->appSettings->getCodeResendMinutes();
		$eMailSubject = $this->appSettings->getEMailSubject();
		$eMailTemplate = $this->appSettings->getEMailTemplate();
		match ($key) {
			'code_length' => $codeLength = (int)$value,
			'code_valid_minutes' => $codeValidMinutes = (int)$value,
			'resend_min_minutes' => $codeResendMinutes = (int)$value,
			'email_subject' => $eMailSubject = $value,
			'email_template' => $eMailTemplate = $value,
		};

		$errors = $this->validator->validate($codeLength, $codeValidMinutes, $codeResendMinutes, $eMailSubject, $eMailTemplate);
		if (!empty($errors)) {
			foreach ($errors as $error) {
				$io->error($this->errorMessage($error));
			}
			return Command::INVALID;
		}

		match ($key) {
			'code_length' => $this->appSettings->setCodeLength((int)$value),
			'code_valid_minutes' => $this->appSettings->setCodeValidMinutes((int)$value),
			'resend_min_minutes' => $this->appSettings->setCodeResendMinutes((int)$value),
			'email_subject' => $this->appSettings->setEMailSubject($value),
			'email_template' => $this->appSettings->setEMailTemplate($value),
		};
		$io->success($key . ' was set to "' . $value . '".');
		return Command::SUCCESS;
	}

	/**
	 * Turns a validation error key into a human-readable message.
	 */
	private function errorMessage(string $errorKey): string {
		return match ($errorKey) {
			'code-length-out-of-range' => sprintf('code_length must be between %d and %d.', SettingsValidator::MIN_CODE_LENGTH, SettingsValidator::MAX_CODE_LENGTH),
			'code-valid-minutes-out-of-range' => sprintf('code_valid_minutes must be between %d and %d.', SettingsValidator::MIN_CODE_VALID_MINUTES, SettingsValidator::MAX_CODE_VALID_MINUTES),
			'resend-minutes-out-of-range' => sprintf('resend_min_minutes must be between %d and %d.', SettingsValidator::MIN_RESEND_MINUTES, SettingsValidator::MAX_RESEND_MINUTES),
			'email-subject-too-long' => sprintf('email_subject must not exceed %d characters.', SettingsValidator::MAX_EMAIL_SUBJECT_LENGTH),
			'email-subject-must-be-single-line' => 'email_subject must be a single line.',
			'email-template-too-long' => sprintf('email_template must not exceed %d characters.', SettingsValidator::MAX_EMAIL_TEMPLATE_LENGTH),
			'email-code-placeholder-missing' => 'email_template must contain the {code} placeholder.',
			default => $errorKey,
		};
	}
}

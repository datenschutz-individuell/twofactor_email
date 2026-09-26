<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Config;

use OCP\Config\IUserConfig;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

/**
 * Declares every config key of this app, so occ shows type, default and meaning,
 * and a mistyped key is refused instead of stored silently.
 *
 * WARNING, not EXCEPTION: an undeclared key is refused and logged, but does not throw
 * inside the login flow. A declared key read with the wrong type throws either way. ConfigLexiconTest makes sure no key of the app is missing: every config call names
 * a ConfigLexicon key, and the declared type matches the call.
 *
 * The lexicon checks types and names only, not ranges. SettingsValidator and the
 * clamping in AppSettings stay in place.
 *
 * The keys and defaults live here, and AppSettings and CodeStorage take them from
 * here. Nextcloud reads the lexicon in the process that updates the app, where the
 * other classes of this app are still the previous version, so this class names
 * none of them. RepairStepProcessTest enforces that.
 */
final class ConfigLexicon implements ILexicon {
	public const KEY_CODE_LENGTH = 'code_length';
	public const KEY_CODE_VALID_MINUTES = 'code_valid_minutes';
	public const KEY_RESEND_MIN_MINUTES = 'resend_min_minutes';
	public const KEY_EMAIL_SUBJECT = 'email_subject';
	public const KEY_EMAIL_TEMPLATE = 'email_template';
	public const DEFAULT_CODE_LENGTH = 6;
	public const DEFAULT_CODE_VALID_MINUTES = 10;
	public const DEFAULT_RESEND_MIN_MINUTES = 1;

	public const KEY_CODE = 'code';
	public const KEY_CODE_CREATED_AT = 'code_created_at';
	public const KEY_CODE_ADDRESS_HASH = 'code_address_hash';

	// Stored by version 2. Version03000400Date20250829000000 reads it to discard old
	// codes. Declared, that read logs nothing, and it keeps working if the strictness
	// is ever raised.
	public const V2_KEY_CODE = 'authentication_code';

	private const NOTE_USE_SETTINGS = 'Use occ twofactor_email:settings instead. It checks the value before it is stored.';

	#[\Override]
	public function getStrictness(): Strictness {
		return Strictness::WARNING;
	}

	#[\Override]
	public function getAppConfigs(): array {
		return [
			new Entry(self::KEY_CODE_LENGTH, ValueType::INT,
				defaultRaw: self::DEFAULT_CODE_LENGTH,
				definition: 'Number of digits in a code',
				note: self::NOTE_USE_SETTINGS),
			new Entry(self::KEY_CODE_VALID_MINUTES, ValueType::INT,
				defaultRaw: self::DEFAULT_CODE_VALID_MINUTES,
				definition: 'Minutes a code stays valid',
				note: self::NOTE_USE_SETTINGS),
			new Entry(self::KEY_RESEND_MIN_MINUTES, ValueType::INT,
				defaultRaw: self::DEFAULT_RESEND_MIN_MINUTES,
				definition: 'Minutes before a user can request a new code',
				note: self::NOTE_USE_SETTINGS),
			// No default for the texts: an empty value means the localized default text.
			// Lazy, because only the mail and the settings need them, and the body can be
			// long. Nextcloud moves stored rows when it updates the app with this lexicon
			// registered, and until then still returns them from the fast cache.
			new Entry(self::KEY_EMAIL_SUBJECT, ValueType::STRING,
				lazy: true,
				definition: 'Subject of the code email. Empty means the localized default.',
				note: self::NOTE_USE_SETTINGS),
			new Entry(self::KEY_EMAIL_TEMPLATE, ValueType::STRING,
				lazy: true,
				definition: 'Body of the code email. Empty means the localized default.',
				note: self::NOTE_USE_SETTINGS),
		];
	}

	#[\Override]
	public function getUserConfigs(): array {
		return [
			// Both hashes are sensitive, so they are encrypted and masked in occ output and
			// support reports. An unsalted hash of an address confirms a guessed address.
			new Entry(self::KEY_CODE, ValueType::STRING,
				definition: 'Hash of the code last sent to the user',
				flags: IUserConfig::FLAG_SENSITIVE),
			new Entry(self::KEY_CODE_CREATED_AT, ValueType::INT,
				definition: 'When that code was created, as a Unix timestamp'),
			new Entry(self::KEY_CODE_ADDRESS_HASH, ValueType::STRING,
				definition: 'Hash of the address the code was sent to',
				flags: IUserConfig::FLAG_SENSITIVE),
			new Entry(self::V2_KEY_CODE, ValueType::STRING,
				definition: 'Code stored by version 2, discarded on upgrade',
				deprecated: true),
		];
	}
}

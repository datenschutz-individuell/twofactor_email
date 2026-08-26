<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Support;

/**
 * The translation files the app ships, read straight from `l10n/`, for tests that
 * have to hold for every language rather than for English alone.
 *
 * Deliberately not the server's loader: the point is what this repository ships,
 * so nothing here follows a theme or an installed instance.
 */
final class ShippedTranslations {

	/**
	 * Every shipped language, plus the untranslated English source, as data sets.
	 * Only the language, so a failing case names it instead of dumping every
	 * translated string.
	 *
	 * @return array<string, array{string}>
	 */
	public static function languages(): array {
		$cases = ['en' => ['en']];
		foreach (glob(self::directory() . '*.json') ?: [] as $file) {
			$language = basename($file, '.json');
			$cases[$language] = [$language];
		}
		return $cases;
	}

	/**
	 * @return array<string, string> source string => translation; empty for English
	 */
	public static function of(string $language): array {
		if ($language === 'en') {
			return [];
		}
		$contents = json_decode(
			(string)file_get_contents(self::directory() . $language . '.json'),
			true,
			512,
			JSON_THROW_ON_ERROR,
		);
		// Plural forms are arrays; a caller that needs one has to handle it there
		return array_filter($contents['translations'] ?? [], is_string(...));
	}

	private static function directory(): string {
		return dirname(__DIR__, 2) . '/l10n/';
	}
}

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Support;

use LogicException;
use OCP\IL10N;

/**
 * Translates the way the Nextcloud server does, for tests that render a text a
 * user would read. OCP ships IL10N as an interface only: the implementation is in
 * the server's lib/private, which an app must not use and which a standalone test
 * run does not even load — so this is the copy.
 *
 * KEEP IN SYNC WITH THE SERVER. t() below follows:
 *   - lib/private/L10N/L10N.php, t(): a single parameter is wrapped in an array
 *   - lib/private/L10N/L10NString.php, __toString(): the source string is looked
 *     up in the translations, then vsprintf'd — always, so a translation whose
 *     format markers do not match the parameters raises the same error a rendered
 *     mail would
 * Read from server 34 on 2026-08-26.
 *
 * Left out on purpose: the plural and %n handling of L10NString, and everything
 * around locales. The mail texts use none of it, so the methods below refuse
 * instead of guessing — copy the server's behaviour here when a test needs one.
 */
final readonly class ServerL10N implements IL10N {

	/**
	 * @param array<string, string> $translations source string => translation;
	 *                                            empty renders the English source
	 */
	public function __construct(
		private array $translations = [],
	) {
	}

	#[\Override]
	public function t(string $text, $parameters = []): string {
		if (!is_array($parameters)) {
			$parameters = [$parameters];
		}
		return vsprintf($this->translations[$text] ?? $text, $parameters);
	}

	#[\Override]
	public function n(string $text_singular, string $text_plural, int $count, array $parameters = []): string {
		throw new LogicException('Plural handling is not copied from the server; copy it before a test needs n().');
	}

	#[\Override]
	public function l(string $type, $data, array $options = []): string {
		throw new LogicException('Localized formatting is not copied from the server; copy it before a test needs l().');
	}

	#[\Override]
	public function getLanguageCode(): string {
		throw new LogicException('This double knows its translations, not a language code.');
	}

	#[\Override]
	public function getLocaleCode(): string {
		throw new LogicException('This double knows its translations, not a locale code.');
	}
}

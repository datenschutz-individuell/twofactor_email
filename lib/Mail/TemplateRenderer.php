<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Mail;

use OCA\TwoFactorEMail\Service\IAppSettings;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;

/**
 * Renders the admin-configurable template texts into the parts of the
 * challenge email.
 *
 * The templates support no markup, but their line structure and URLs
 * survive in the HTML variant of the email:
 *   - a blank line starts a new paragraph (own addBodyText call)
 *   - a single line break becomes <br>
 *   - http(s) URLs are detected and rendered as links — the URL itself
 *     stays the visible text; trailing sentence punctuation is not
 *     considered part of the URL
 *   - {logo} inserts the instance logo; it only appears in the HTML variant
 *   - all placeholders ({code}, {user}, {cloud}, {validity}) render bold
 *     and monospace in the HTML variant; in the plain text variant they are
 *     inserted bare, {code} with ">>> <<<" markers; inside URLs and in the
 *     subject all are inserted bare
 * Everything else is HTML-escaped — raw HTML is not possible.
 */
final class TemplateRenderer {

	private const URL_PATTERN = '~https?://[^\s<>"]+~i';

	public function __construct(
		private readonly Defaults $defaults,
		private readonly IURLGenerator $urlGenerator,
		private readonly IAppSettings $appSettings,
	) {
	}

	/**
	 * The subject is a single line of plain text — all placeholders are
	 * inserted bare.
	 */
	public function renderSubject(string $subject, IUser $user, string $code): string {
		return strtr($subject, $this->placeholderValues($user, $code));
	}

	/**
	 * Renders the body template into paragraphs, ready to be passed to
	 * IEMailTemplate::addBodyText().
	 *
	 * @return list<array{string, string|false}> [html, plain] per paragraph;
	 *                                           plain is false when the
	 *                                           paragraph has no plain text
	 *                                           counterpart
	 */
	public function renderBody(string $body, IUser $user, string $code): array {
		$values = $this->placeholderValues($user, $code);
		// The logo is solely controlled by the {logo} token — there is no
		// automatic logo header. Without that header the first paragraph would
		// stick to the top edge (the server's <p> only has a bottom margin),
		// so an empty paragraph provides the spacing.
		$rendered = [['&nbsp;', false]];
		foreach ($this->paragraphs($body) as $paragraph) {
			$plain = $this->toPlain(str_replace('{logo}', '', $paragraph), $values);
			// An empty plain text (e.g. a logo-only paragraph) must be passed
			// as false — with '' the server would fall back to escaping the
			// HTML.
			$rendered[] = [$this->toHtml($paragraph, $values), trim($plain) === '' ? false : $plain];
		}
		return $rendered;
	}

	/**
	 * @return array<string, string> placeholder => replacement value
	 */
	private function placeholderValues(IUser $user, string $code): array {
		return [
			'{code}' => $code,
			'{user}' => $user->getDisplayName(),
			'{cloud}' => $this->defaults->getName(),
			'{validity}' => (string)$this->appSettings->getCodeValidMinutes(),
		];
	}

	/**
	 * @return string[] non-empty paragraphs, split on blank lines
	 */
	private function paragraphs(string $text): array {
		$split = preg_split('/\R\s*\R/u', $text) ?: [];
		return array_values(array_filter(array_map(trim(...), $split), static fn (string $p): bool => $p !== ''));
	}

	/**
	 * Auto-links the URLs of the given text; the literal text segments and the
	 * URLs are escaped individually.
	 *
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toHtml(string $paragraph, array $values): string {
		$result = '';
		$offset = 0;
		while (preg_match(self::URL_PATTERN, $paragraph, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
			$position = $match[0][1];
			// Trailing punctuation usually ends the sentence, not the URL
			$url = rtrim($match[0][0], '.,;:!?)');
			$result .= $this->literal(substr($paragraph, $offset, $position - $offset), $values);
			// Inside URLs the placeholders are inserted bare — markup must not
			// end up in attributes
			$href = htmlspecialchars(strtr($url, $values));
			$result .= '<a href="' . $href . '">' . $href . '</a>';
			$offset = $position + strlen($url);
		}
		$result .= $this->literal(substr($paragraph, $offset), $values);
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], $result);
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toPlain(string $paragraph, array $values): string {
		// No styling in plain text — bare values, the code with markers.
		// strtr() replaces in a single pass, so placeholder-like fragments in
		// the inserted values (e.g. a display name containing "{code}") stay
		// as-is.
		return strtr($paragraph, ['{code}' => '>>> ' . $values['{code}'] . ' <<<'] + $values);
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function literal(string $text, array $values): string {
		$html = htmlspecialchars($text);
		if (str_contains($html, '{logo}')) {
			// Keep the logo small: at most 250px and 20% of the email width.
			// The doubled max-width is progressive enhancement — clients that
			// do not understand min() fall back to the plain 250px limit. A
			// percentage height cap is not enforceable in emails (no sized
			// parent), so the height is limited to 250px only.
			$html = str_replace(
				'{logo}',
				'<img src="' . htmlspecialchars($this->logoUrl()) . '" alt="' . htmlspecialchars($this->defaults->getName())
					. '" style="max-width:250px;max-width:min(250px, 20%);max-height:250px">',
				$html,
			);
		}
		// The placeholder values stand out: bold and monospace in the HTML variant
		$styled = [];
		foreach ($values as $placeholder => $value) {
			$styled[$placeholder] = '<strong style="font-family:monospace">' . htmlspecialchars($value) . '</strong>';
		}
		return strtr($html, $styled);
	}

	private function logoUrl(): string {
		// Same source as the server's own mail header (PNG variant for Outlook)
		return $this->urlGenerator->getAbsoluteURL($this->defaults->getLogo(false));
	}
}

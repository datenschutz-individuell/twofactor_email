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
 *     inserted bare, {code} with ">>> <<<" markers; in the subject bare
 *   - inside a URL the placeholders are inserted bare, so no markup can end
 *     up in an attribute
 * Everything else is HTML-escaped — raw HTML is not possible.
 *
 * Nothing here decides whether the one-time code may leave the system. That is
 * asked once, of the finished text, by EMailSender — see codeCouldBeFetched().
 */
final readonly class TemplateRenderer {

	// Detection for linking only. Where an address ends is a matter of taste, and
	// getting it wrong here costs a link, not a code.
	private const URL_PATTERN = '~https?://[^\s<>"]+~i';

	public function __construct(
		private Defaults $defaults,
		private IURLGenerator $urlGenerator,
		private IAppSettings $appSettings,
	) {
	}

	/**
	 * Whether any web address in the text contains a placeholder. Such a text renders
	 * a value into a link, so the mail would go out with the default text instead —
	 * SettingsValidator tells the admin before it comes to that.
	 *
	 * Reads an address the same way codeCouldBeFetched() does, and must keep doing so:
	 * anything that check would stop has to be refused when it is written, or the
	 * admin is told a setting was saved that then never takes effect.
	 */
	public static function hasPlaceholderInUrl(string $text): bool {
		foreach (self::linkableRuns($text) as $run) {
			foreach (self::VALUE_PLACEHOLDERS as $placeholder) {
				if (str_contains($run, $placeholder)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The parts of the text a link scanner would read as one address: separated by
	 * ASCII whitespace, containing a scheme or a leading "www.". Without the `u` flag,
	 * so unusual bytes cannot make it fail — and if the split ever did fail, the whole
	 * text counts as one address, which errs towards reporting.
	 *
	 * @return list<string>
	 */
	private static function linkableRuns(string $text): array {
		$runs = preg_split('~[ \t\r\n\x0B\f]+~', $text) ?: [$text];
		return array_values(array_filter($runs, static fn (string $run): bool => preg_match('~://|www\.~i', $run) === 1));
	}

	/**
	 * Whether a link scanner could fetch the code from this text: it sits in a run
	 * of characters that such a scanner would read as a web address.
	 *
	 * This asks about the finished text, not about the template, so it also sees an
	 * address that an inserted value built around the code. It is deliberately
	 * cruder than URL_PATTERN: it splits on ASCII whitespace only, treats every
	 * scheme and a leading "www." as linkable, and uses no `u` flag, so it cannot
	 * fail on unusual bytes. When in doubt it should say yes: sending the default
	 * text without need is better than letting one code slip through.
	 */
	public static function codeCouldBeFetched(string $text, string $code): bool {
		if ($code === '') {
			return false;
		}
		foreach (self::linkableRuns($text) as $run) {
			if (str_contains($run, $code)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The subject is a single line of plain text — all placeholders are
	 * inserted bare. Line breaks are replaced after the substitution: the
	 * admin text is validated single-line, but placeholder values like the
	 * display name are not, and a line break in a mail header must never
	 * reach the mailer (header injection, defense in depth).
	 */
	public function renderSubject(string $subject, IUser $user, string $code): string {
		// {logo} has no text form, so it drops out here, as in the plain text body.
		$rendered = strtr($subject, ['{logo}' => ''] + $this->placeholderValues($user, $code));
		return str_replace(["\r\n", "\r", "\n"], ' ', $rendered);
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
			$plain = $this->toPlain($paragraph, $values);
			// An empty plain text (e.g. a logo-only paragraph) must be passed
			// as false — with '' the server would fall back to escaping the
			// HTML.
			$rendered[] = [$this->toHtml($paragraph, $values), trim($plain) === '' ? false : $plain];
		}
		return $rendered;
	}

	/**
	 * The placeholders that insert a value. Only these matter inside a web address:
	 * {logo} expands to an image tag or to nothing, so it hands no data to anyone.
	 * A test pins this list against placeholderValues().
	 */
	private const VALUE_PLACEHOLDERS = ['{code}', '{user}', '{cloud}', '{validity}'];

	/**
	 * Every placeholder this class expands. A test pins it against placeholderValues()
	 * and literal(). The admin settings hint lists them again inside its translated
	 * sentences, which this constant cannot fill in without hurting the translation —
	 * so a new placeholder has to be added there by hand.
	 */
	public const PLACEHOLDERS = [...self::VALUE_PLACEHOLDERS, '{logo}'];

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
		$split = preg_split('/\R\s*\R/u', $text) ?: [$text];
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
		return str_replace(["\r\n", "\n", "\r"], '<br>', $result);
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toPlain(string $paragraph, array $values): string {
		// Single pass, so a "{code}" inside an inserted display name stays as-is.
		// {logo} has no text form and drops out here.
		return strtr($paragraph, ['{code}' => '>>> ' . $values['{code}'] . ' <<<', '{logo}' => ''] + $values);
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function literal(string $text, array $values): string {
		$html = htmlspecialchars($text);
		// The placeholder values stand out: bold and monospace in the HTML variant
		$styled = [];
		foreach ($values as $placeholder => $value) {
			$styled[$placeholder] = '<strong style="font-family:monospace">' . htmlspecialchars($value) . '</strong>';
		}
		if (str_contains($html, '{logo}')) {
			// In the same single pass: strtr never scans a replacement, so a
			// placeholder inside the instance name (the alt text) stays literal.
			// Keep the logo small: at most 250px and 20% of the email width.
			// The doubled max-width is progressive enhancement — clients that
			// do not understand min() fall back to the plain 250px limit. A
			// percentage height cap is not enforceable in emails (no sized
			// parent), so the height is limited to 250px only.
			$styled['{logo}'] = '<img src="' . htmlspecialchars($this->logoUrl()) . '" alt="' . htmlspecialchars($this->defaults->getName())
				. '" style="max-width:min(250px, 20%);max-height:250px">';
		}
		return strtr($html, $styled);
	}

	private function logoUrl(): string {
		// Same source as the server's own mail header (PNG variant for Outlook)
		return $this->urlGenerator->getAbsoluteURL($this->defaults->getLogo(false));
	}
}

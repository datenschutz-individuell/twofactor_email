<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Mail;

/**
 * Reads a text the way a link scanner in a mail gateway or client does: it looks
 * for runs of characters such a scanner would fetch on its own, and answers what
 * sits inside them. This is where the one-time code must never end up.
 *
 * Deliberately cruder than the URL detection in TemplateRenderer, which serves the
 * auto-linking, where a wrong boundary costs a link and nothing else. Here a wrong
 * boundary would cost the code, so when in doubt this says yes: sending the default
 * text without need is better than letting one code slip through.
 *
 * The text-level checks work on any text; couldLeakCode() additionally knows the
 * shape of TemplateRenderer::renderBody()'s output, because the rendered mail is
 * what it has to answer for.
 */
final class LinkScanner {

	/**
	 * Whether any web address in the text contains a placeholder that inserts a value.
	 * Such a text renders a value into a link, so the mail would go out with the
	 * default text instead — SettingsValidator tells the admin before it comes to that.
	 * {logo} is not on the list: it expands to an image tag or to nothing and hands no
	 * data to whoever owns the address.
	 *
	 * Reads an address the same way couldLeakCode() does, through the same function,
	 * and must keep doing so: anything the send-side check would stop has to be
	 * refused when it is written, or the admin is told a setting was saved that then
	 * never takes effect.
	 */
	public static function hasPlaceholderInUrl(string $text): bool {
		foreach (self::linkableRuns($text) as $run) {
			foreach (TemplateRenderer::VALUE_PLACEHOLDERS as $placeholder) {
				if (str_contains($run, $placeholder)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a link scanner could fetch the code from the finished mail. Asks about
	 * the rendered text, not about the template, so it also sees an address that an
	 * inserted value built around the code — a display name of
	 * "https://evil.example/?c=" in front of the code, say, which no check on the
	 * template could see.
	 *
	 * @param string $subject the rendered subject
	 * @param list<array{string, string|false}> $parts the rendered body parts, [html, plain]
	 */
	public static function couldLeakCode(string $subject, array $parts, string $code): bool {
		if (self::codeCouldBeFetched($subject, $code)) {
			return true;
		}
		foreach ($parts as [$html, $plain]) {
			if ($plain !== false && self::codeCouldBeFetched($plain, $code)) {
				return true;
			}
			// The link targets are ours, so they are read back the way they were
			// written. A code in one of them is fetched without any scanner.
			if (preg_match_all('~href="([^"]*)"~', $html, $matches) === false) {
				return true;
			}
			foreach ($matches[1] as $href) {
				if (str_contains(htmlspecialchars_decode($href), $code)) {
					return true;
				}
			}
			// What the reader sees, without the markup between the words: a client
			// that links the rendered text does not see the tags either. A <br>
			// becomes a separator, because dropping it would glue the code to the
			// address on the next line; an image becomes its alt text, without a
			// separator, because a client with images blocked renders that text
			// exactly where the image stood.
			$visible = preg_replace_callback('~<img\b[^>]*>~i', self::altText(...), $html);
			$visible = $visible === null ? null : preg_replace('~<br\s*/?>~i', ' ', $visible);
			if ($visible === null) {
				// A failed replacement leaves no text to judge, and this check must
				// not pass by accident.
				return true;
			}
			if (self::codeCouldBeFetched(htmlspecialchars_decode(strip_tags($visible)), $code)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What a client with images blocked shows in place of the image: its alt text,
	 * which here is the instance name — a value an admin sets in the web UI. Nothing
	 * is put around it, because such a client renders it exactly where the image
	 * stood, so the name can end up directly in front of the code.
	 *
	 * @param array<int, string> $match
	 */
	private static function altText(array $match): string {
		return preg_match('~\balt="([^"]*)"~i', $match[0], $alt) === 1
			? htmlspecialchars_decode($alt[1])
			: ' ';
	}

	/**
	 * Whether the code sits in a run of characters that a link scanner would read as
	 * a web address.
	 */
	private static function codeCouldBeFetched(string $text, string $code): bool {
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
}

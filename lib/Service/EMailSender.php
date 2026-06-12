<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use Exception;
use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCP\Defaults;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

final class EMailSender implements IEMailSender {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IMailer $mailer,
		private readonly Defaults $defaults,
		private readonly IURLGenerator $urlGenerator,
		private readonly IAppSettings $appSettings,
		private readonly AppSettingsDefaults $appSettingsDefaults,
	) {
	}

	public function sendChallengeEMail(IUser $user, string $code): void {
		$email = $user->getEMailAddress();
		if ($email === null) {
			throw new EMailNotSet($user);
		}

		$this->logger->debug("sending email message to $email.");

		// For every part an empty admin setting means: use the localized default
		$subject = $this->appSettings->getEMailSubject() ?: $this->appSettingsDefaults->eMailSubject();
		$customBody = $this->appSettings->getEMailTemplate();
		$body = $customBody ?: $this->appSettingsDefaults->eMailBody();
		$footer = $this->appSettings->getEMailFooter();

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$template->setSubject($this->replacePlaceholders($subject, $user, $code));
		if ($customBody === '') {
			// Default body: classic email layout with the standard logo header.
			// A customized body controls the logo itself via the {logo} token.
			$template->addHeader();
		}
		// In the body, the placeholders are replaced during rendering: bold and
		// monospace in the HTML variant, bare values in the plain text variant
		// ({code} additionally with ">>> <<<" markers there).
		$values = $this->placeholderValues($user, $code);
		foreach ($this->paragraphs($body) as $paragraph) {
			$plain = $this->toPlain(str_replace('{logo}', '', $paragraph), $values);
			// An empty plain text (e.g. a logo-only paragraph) must be passed as
			// false — with '' the server would fall back to escaping the HTML.
			$template->addBodyText($this->toHtml($paragraph, $values), trim($plain) === '' ? false : $plain);
		}
		if ($footer === '') {
			// Standard footer of this Nextcloud instance (theming slogan)
			$template->addFooter();
		} else {
			// In the footer all placeholders are inserted bare (replaced before
			// rendering, so neither styling nor markers apply)
			$template->addFooter($this->toFooterHtml($this->replacePlaceholders($footer, $user, $code), $values));
		}

		$message = $this->mailer->createMessage();
		$message->setTo([$email => $user->getDisplayName()]);
		$message->useTemplate($template);

		try {
			$this->mailer->send($message);
		} catch (Exception $e) {
			$this->logger->error('failed sending email message to user ' . $user->getUID() . '.', ['exception' => $e]);
			throw new SendEMailFailed(previous: $e);
		}
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

	private function replacePlaceholders(string $text, IUser $user, string $code): string {
		// strtr() replaces in a single pass — placeholder-like fragments in the
		// inserted values (e.g. a display name containing "{code}") stay as-is
		return strtr($text, $this->placeholderValues($user, $code));
	}

	/*
	 * The template texts support a minimal markup so that line structure
	 * survives in the HTML variant of the email:
	 *   - a blank line starts a new paragraph (own addBodyText call)
	 *   - a single line break becomes <br>
	 *   - [URL="https://example.org"]Text[/URL] (quotes optional) or
	 *     [URL]https://example.org[/URL] becomes a clickable link (http, https
	 *     and mailto only); in the plain text variant and in the footer it is
	 *     rendered as "Text (URL)"
	 *   - [IMG="https://example.org/image.png"]Description[/IMG] or
	 *     [IMG]https://example.org/image.png[/IMG] embeds a remote image
	 *     (https only, body HTML variant only); elsewhere it is rendered as
	 *     "Description (URL)"
	 *   - {logo} (body only) inserts the instance logo; it only appears in the
	 *     HTML variant
	 *   - all placeholders ({code}, {user}, {cloud}, {validity}) render bold
	 *     and monospace in the HTML variant of the body; in its plain text
	 *     variant they are inserted bare, {code} with ">>> <<<" markers; inside
	 *     tags, in the subject and in the footer all are inserted bare
	 * Tags are case-insensitive. Invalid markup (unsupported scheme, missing
	 * URL or text) stays literally. Everything else is HTML-escaped — raw HTML
	 * is not possible.
	 */

	private const TAG_PATTERN = '~\[(URL|IMG)(?:="?([^"\]]*)"?)?\](.*?)\[/\1\]~is';
	private const ALLOWED_LINK_SCHEMES = '~^(https?://|mailto:)~i';
	private const ALLOWED_IMAGE_SCHEMES = '~^https://~i';

	/**
	 * @return string[] non-empty paragraphs, split on blank lines
	 */
	private function paragraphs(string $text): array {
		$split = preg_split('/\R\s*\R/u', $text) ?: [];
		return array_values(array_filter(array_map(trim(...), $split), static fn (string $p): bool => $p !== ''));
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toHtml(string $paragraph, array $values): string {
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], $this->renderTags($paragraph, true, $values));
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toPlain(string $paragraph, array $values): string {
		return $this->renderTags($paragraph, false, $values);
	}

	/**
	 * Renders the [URL] and [IMG] tags of the given text into the HTML or the
	 * plain text variant. The markup is parsed on the raw text; the literal
	 * text segments and all tag parts are escaped individually for HTML.
	 *
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function renderTags(string $text, bool $asHtml, array $values): string {
		$result = '';
		$offset = 0;
		while (preg_match(self::TAG_PATTERN, $text, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
			$position = $match[0][1];
			$result .= $this->literal(substr($text, $offset, $position - $offset), $asHtml, $values);
			$result .= $this->renderTag(strtoupper($match[1][0]), $match[2][0], $match[3][0], $asHtml, $values)
				?? $this->literal($match[0][0], $asHtml, $values);
			$offset = $position + strlen($match[0][0]);
		}
		return $result . $this->literal(substr($text, $offset), $asHtml, $values);
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 * @return string|null the rendered tag, or null if the markup is invalid
	 *                     and shall stay literally
	 */
	private function renderTag(string $tag, string $attribute, string $content, bool $asHtml, array $values): ?string {
		// Inside tags the placeholders are always inserted bare — also in the
		// HTML variant, since markup must not end up in URLs or attributes
		$attribute = strtr($attribute, $values);
		$content = strtr($content, $values);
		if ($tag === 'URL') {
			$url = $attribute !== '' ? $attribute : trim($content);
			$text = $attribute !== '' ? $content : $url;
			if ($url === '' || $text === '' || preg_match(self::ALLOWED_LINK_SCHEMES, $url) !== 1) {
				return null;
			}
			if ($asHtml) {
				return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($text) . '</a>';
			}
			return $text === $url ? $url : $text . ' (' . $url . ')';
		}
		// IMG
		$src = $attribute !== '' ? $attribute : trim($content);
		$alt = $attribute !== '' ? $content : '';
		if ($src === '' || preg_match(self::ALLOWED_IMAGE_SCHEMES, $src) !== 1) {
			return null;
		}
		if ($asHtml) {
			return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" style="max-width:100%">';
		}
		return $alt === '' ? $src : $alt . ' (' . $src . ')';
	}

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function literal(string $text, bool $asHtml, array $values): string {
		if (!$asHtml) {
			// No styling in plain text — bare values, the code with markers
			return strtr($text, ['{code}' => '>>> ' . $values['{code}'] . ' <<<'] + $values);
		}
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

	/**
	 * @param array<string, string> $values placeholder => replacement value
	 */
	private function toFooterHtml(string $footer, array $values): string {
		// The footer has no paragraph concept and the server derives its plain
		// text variant from the HTML by replacing <br>, so links and images are
		// rendered in their "Text (URL)" form and all placeholders stay bare
		// (they are already replaced before rendering).
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], htmlspecialchars($this->toPlain($footer, $values)));
	}

	private function logoUrl(): string {
		// Same source as the server's own mail header (PNG variant for Outlook)
		return $this->urlGenerator->getAbsoluteURL($this->defaults->getLogo(false));
	}
}

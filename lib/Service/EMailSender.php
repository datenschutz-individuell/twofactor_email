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
		// In the body, {code} is replaced during rendering: bold and monospace
		// in the HTML variant, the bare code in the plain text variant.
		foreach ($this->paragraphs($this->replaceTextPlaceholders($body, $user)) as $paragraph) {
			$plain = $this->toPlain(str_replace('{logo}', '', $paragraph), $code);
			// An empty plain text (e.g. a logo-only paragraph) must be passed as
			// false — with '' the server would fall back to escaping the HTML.
			$template->addBodyText($this->toHtml($paragraph, $code), trim($plain) === '' ? false : $plain);
		}
		if ($footer === '') {
			// Standard footer of this Nextcloud instance (theming slogan)
			$template->addFooter();
		} else {
			$template->addFooter($this->toFooterHtml($this->replaceTextPlaceholders($footer, $user), $code));
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

	private function replacePlaceholders(string $text, IUser $user, string $code): string {
		return str_replace('{code}', $code, $this->replaceTextPlaceholders($text, $user));
	}

	private function replaceTextPlaceholders(string $text, IUser $user): string {
		return str_replace(
			['{user}', '{cloud}', '{validity}'],
			[$user->getDisplayName(), $this->defaults->getName(), (string)$this->appSettings->getCodeValidMinutes()],
			$text,
		);
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
	 *   - {code} (body only) renders bold and monospace in the HTML variant;
	 *     inside tags, in the plain text variant, in subject and footer it is
	 *     inserted bare
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

	private function toHtml(string $paragraph, string $code): string {
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], $this->renderTags($paragraph, true, $code));
	}

	private function toPlain(string $paragraph, string $code): string {
		return $this->renderTags($paragraph, false, $code);
	}

	/**
	 * Renders the [URL] and [IMG] tags of the given text into the HTML or the
	 * plain text variant. The markup is parsed on the raw text; the literal
	 * text segments and all tag parts are escaped individually for HTML.
	 */
	private function renderTags(string $text, bool $asHtml, string $code): string {
		$result = '';
		$offset = 0;
		while (preg_match(self::TAG_PATTERN, $text, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
			$position = $match[0][1];
			$result .= $this->literal(substr($text, $offset, $position - $offset), $asHtml, $code);
			$result .= $this->renderTag(strtoupper($match[1][0]), $match[2][0], $match[3][0], $asHtml, $code)
				?? $this->literal($match[0][0], $asHtml, $code);
			$offset = $position + strlen($match[0][0]);
		}
		return $result . $this->literal(substr($text, $offset), $asHtml, $code);
	}

	/**
	 * @return string|null the rendered tag, or null if the markup is invalid
	 *                     and shall stay literally
	 */
	private function renderTag(string $tag, string $attribute, string $content, bool $asHtml, string $code): ?string {
		// Inside tags the code is always inserted bare — also in the HTML
		// variant, since markup must not end up in URLs or attributes
		$attribute = str_replace('{code}', $code, $attribute);
		$content = str_replace('{code}', $code, $content);
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

	private function literal(string $text, bool $asHtml, string $code): string {
		if (!$asHtml) {
			return str_replace('{code}', $code, $text);
		}
		$html = htmlspecialchars($text);
		if (str_contains($html, '{logo}')) {
			$html = str_replace(
				'{logo}',
				'<img src="' . htmlspecialchars($this->logoUrl()) . '" alt="' . htmlspecialchars($this->defaults->getName()) . '" style="max-width:100%">',
				$html,
			);
		}
		// The code stands out: bold and monospace in the HTML variant
		return str_replace(
			'{code}',
			'<strong style="font-family:monospace">' . htmlspecialchars($code) . '</strong>',
			$html,
		);
	}

	private function toFooterHtml(string $footer, string $code): string {
		// The footer has no paragraph concept and the server derives its plain
		// text variant from the HTML by replacing <br>, so links and images are
		// rendered in their "Text (URL)" form and the code stays bare here.
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], htmlspecialchars($this->toPlain($footer, $code)));
	}

	private function logoUrl(): string {
		// Same source as the server's own mail header (PNG variant for Outlook)
		return $this->urlGenerator->getAbsoluteURL($this->defaults->getLogo(false));
	}
}

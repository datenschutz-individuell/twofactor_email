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
		$body = $this->appSettings->getEMailTemplate() ?: $this->appSettingsDefaults->eMailBody();

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$template->setSubject($this->replacePlaceholders($subject, $user, $code));
		// The logo is solely controlled by the {logo} token in the body (the
		// default body starts with it) — no automatic logo header. Without
		// that header the first paragraph would stick to the top edge (the
		// server's <p> only has a bottom margin), so add an empty paragraph
		// as spacing. It has no plain text counterpart.
		$template->addBodyText('&nbsp;', false);
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
		// Standard footer of this Nextcloud instance (theming slogan)
		$template->addFooter();

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
	 * The template texts support no markup, but their line structure and URLs
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

	private const URL_PATTERN = '~https?://[^\s<>"]+~i';

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
		// No styling in plain text — bare values, the code with markers
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

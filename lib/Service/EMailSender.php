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
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

final class EMailSender implements IEMailSender {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IMailer $mailer,
		private readonly Defaults $defaults,
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
		$footer = $this->appSettings->getEMailFooter();

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$template->setSubject($this->replacePlaceholders($subject, $user, $code));
		$template->addHeader();
		foreach ($this->paragraphs($this->replacePlaceholders($body, $user, $code)) as $paragraph) {
			$template->addBodyText($this->toHtml($paragraph), $this->toPlain($paragraph));
		}
		if ($footer === '') {
			// Standard footer of this Nextcloud instance (theming slogan)
			$template->addFooter();
		} else {
			$template->addFooter($this->toFooterHtml($this->replacePlaceholders($footer, $user, $code)));
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
		return str_replace(
			['{code}', '{user}', '{cloud}', '{validity}'],
			[$code, $user->getDisplayName(), $this->defaults->getName(), (string)$this->appSettings->getCodeValidMinutes()],
			$text,
		);
	}

	/*
	 * The template texts support a minimal markup so that line structure
	 * survives in the HTML variant of the email:
	 *   - a blank line starts a new paragraph (own addBodyText call)
	 *   - a single line break becomes <br>
	 *   - [Text](https://example.org) becomes a clickable link (http, https
	 *     and mailto only); in the plain text variant and in the footer it is
	 *     rendered as "Text (URL)"
	 * Everything else is HTML-escaped — raw HTML is not possible.
	 */

	private const LINK_PATTERN = '/\[([^\]]+)\]\(([^)\s]+)\)/';
	private const ALLOWED_LINK_SCHEMES = '~^(https?://|mailto:)~i';

	/**
	 * @return string[] non-empty paragraphs, split on blank lines
	 */
	private function paragraphs(string $text): array {
		$split = preg_split('/\R\s*\R/u', $text) ?: [];
		return array_values(array_filter(array_map(trim(...), $split), static fn (string $p): bool => $p !== ''));
	}

	private function toHtml(string $paragraph): string {
		$html = htmlspecialchars($paragraph);
		$html = preg_replace_callback(self::LINK_PATTERN, static function (array $match): string {
			if (preg_match(self::ALLOWED_LINK_SCHEMES, $match[2]) !== 1) {
				return $match[0]; // unsupported scheme: keep the markup literally
			}
			return '<a href="' . $match[2] . '">' . $match[1] . '</a>';
		}, $html) ?? $html;
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], $html);
	}

	private function toPlain(string $paragraph): string {
		return preg_replace_callback(self::LINK_PATTERN, static function (array $match): string {
			if (preg_match(self::ALLOWED_LINK_SCHEMES, $match[2]) !== 1) {
				return $match[0]; // unsupported scheme: keep the markup literally
			}
			return $match[1] === $match[2] ? $match[2] : $match[1] . ' (' . $match[2] . ')';
		}, $paragraph) ?? $paragraph;
	}

	private function toFooterHtml(string $footer): string {
		// The footer has no paragraph concept and the server derives its plain
		// text variant from the HTML by replacing <br>, so links are rendered
		// in their "Text (URL)" form here.
		return str_replace(["\r\n", "\n"], ['<br>', '<br>'], htmlspecialchars($this->toPlain($footer)));
	}
}

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
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

final readonly class EMailSender implements IEMailSender {
	public function __construct(
		private LoggerInterface $logger,
		private IMailer $mailer,
		private IAppSettings $appSettings,
		private TemplateRenderer $templateRenderer,
	) {
	}

	public function sendChallengeEMail(IUser $user, string $code): void {
		$email = $user->getEMailAddress();
		if ($email === null) {
			throw new EMailNotSet($user);
		}

		// Deliberately logs the UID, not the email address (data minimization)
		$this->logger->debug('sending email message to user ' . $user->getUID() . '.');

		// For every part an empty admin setting means: use the localized default
		$subject = $this->appSettings->getEMailSubject() ?: $this->appSettings->getDefaultEMailSubject();
		$body = $this->appSettings->getEMailTemplate() ?: $this->appSettings->getDefaultEMailBody();

		$renderedSubject = $this->templateRenderer->renderSubject($subject, $user, $code);
		$parts = $this->templateRenderer->renderBody($body, $user, $code);

		// The last check before the code leaves this system, on the finished text.
		// Everything before it works on the template, where an inserted value is not
		// yet visible — a display name can build a web address around the code.
		if ($this->couldLeakCode($renderedSubject, $parts, $code)) {
			$this->logger->warning(
				'The configured email text would have put the code into a web address. The default text '
				. 'was used instead. Fix it with occ twofactor_email:settings.',
			);
			$renderedSubject = $this->templateRenderer->renderSubject($this->appSettings->getDefaultEMailSubject(), $user, $code);
			$parts = $this->templateRenderer->renderBody($this->appSettings->getDefaultEMailBody(), $user, $code);
			if ($this->couldLeakCode($renderedSubject, $parts, $code)) {
				// Only reachable through an inserted value, so no text can repair it.
				// Not sending keeps the user out; sending would hand the code away.
				$this->logger->error(
					'Even the default email text would have put the code into a web address, most likely '
					. 'through the display name. No mail was sent.',
				);
				throw new SendEMailFailed();
			}
		}

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$template->setSubject($renderedSubject);
		foreach ($parts as [$html, $plain]) {
			$template->addBodyText($html, $plain);
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
	 * @param list<array{string, string|false}> $parts
	 */
	private function couldLeakCode(string $subject, array $parts, string $code): bool {
		if (TemplateRenderer::codeCouldBeFetched($subject, $code)) {
			return true;
		}
		foreach ($parts as [$html, $plain]) {
			if ($plain !== false && TemplateRenderer::codeCouldBeFetched($plain, $code)) {
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
			// that links the rendered text does not see the tags either. Only the
			// tags that break the line become a separator — dropping <br> silently
			// would glue the code to the address on the next line.
			$visible = preg_replace('~<br\s*/?>|<img\b[^>]*>~i', ' ', $html) ?? $html;
			if (TemplateRenderer::codeCouldBeFetched(htmlspecialchars_decode(strip_tags($visible)), $code)) {
				return true;
			}
		}
		return false;
	}
}

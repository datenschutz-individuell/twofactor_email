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

final class EMailSender implements IEMailSender {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IMailer $mailer,
		private readonly IAppSettings $appSettings,
		private readonly AppSettingsDefaults $appSettingsDefaults,
		private readonly TemplateRenderer $templateRenderer,
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
		$template->setSubject($this->templateRenderer->renderSubject($subject, $user, $code));
		foreach ($this->templateRenderer->renderBody($body, $user, $code) as [$html, $plain]) {
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
}

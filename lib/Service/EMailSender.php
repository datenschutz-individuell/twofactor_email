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
		$template->addBodyText($this->replacePlaceholders($body, $user, $code));
		if ($footer === '') {
			// Standard footer of this Nextcloud instance (theming slogan)
			$template->addFooter();
		} else {
			$template->addFooter($this->replacePlaceholders($footer, $user, $code));
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
}

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
use OCP\IL10N;
use OCP\IUser;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

final class EMailSender implements IEMailSender {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IL10N $l10n,
		private readonly IMailer $mailer,
		private readonly Defaults $defaults,
		private readonly IAppSettings $appSettings,
	) {
	}

	public function sendChallengeEMail(IUser $user, string $code): void {
		$email = $user->getEMailAddress();
		if ($email === null) {
			throw new EMailNotSet($user);
		}

		$this->logger->debug("sending email message to $email.");

		$cloud = $this->defaults->getName();
		$userAtCloud = $user->getDisplayName() . ' @ ' . $cloud;

		// Replace placeholders in the admin-configurable template
		$bodyText = str_replace(
			['{code}', '{user}', '{cloud}'],
			[$code, $user->getDisplayName(), $cloud],
			$this->appSettings->getEMailTemplate()
		);

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$template->setSubject($this->l10n->t('Login attempt for %s', [$userAtCloud]));
		$template->addHeader();
		$template->addHeading($this->l10n->t('Your two-factor authentication code is: %s', [$code]));
		$template->addBodyText($bodyText);
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

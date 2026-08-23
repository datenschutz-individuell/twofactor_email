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
use OCA\TwoFactorEMail\Exception\SendRateLimited;
use OCA\TwoFactorEMail\Mail\TemplateRenderer;
use OCP\IUser;
use OCP\Mail\IMailer;
use OCP\Security\RateLimiting\ILimiter;
use OCP\Security\RateLimiting\IRateLimitExceededException;
use Psr\Log\LoggerInterface;

final class EMailSender implements IEMailSender {
	/**
	 * How often one account may make the app open a connection to the mail
	 * server, and over which period in seconds. The period is what makes the cap
	 * reachable while a mail server hangs: Nextcloud waits `mail_smtptimeout`
	 * seconds for one, ten by default, so ten attempts take about a hundred
	 * seconds and a shorter window would expire before the tenth. Ten in five
	 * minutes is out of reach for anyone logging in.
	 */
	private const SEND_LIMIT = 10;
	private const SEND_PERIOD = 300;
	private const SEND_LIMIT_IDENTIFIER = 'twofactor_email-send';

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IMailer $mailer,
		private readonly IAppSettings $appSettings,
		private readonly TemplateRenderer $templateRenderer,
		private readonly ILimiter $limiter,
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

		$this->throttle($user);

		try {
			$failedRecipients = $this->mailer->send($message);
		} catch (Exception $e) {
			$this->logger->error('failed sending email message to user ' . $user->getUID() . '.', ['exception' => $e]);
			throw new SendEMailFailed(previous: $e);
		}

		// Nextcloud does not throw when it cannot deliver: it catches the transport error
		// and returns the addresses it refused. Without this check the app would store
		// the code and report it as sent while nothing went out.
		if ($failedRecipients !== []) {
			// Deliberately without the address (data minimization)
			$this->logger->error('failed sending email message to user ' . $user->getUID() . ': the mailer refused the recipient.');
			throw new SendEMailFailed('The mailer refused the recipient address');
		}
	}

	/**
	 * Caps how often one account can make the app open a connection to the mail
	 * server. This cap counts under its own identifier; the rate limit on the
	 * resend endpoint is a separate budget, even though both are kept by the same
	 * Nextcloud limiter.
	 *
	 * A code that was sent is stored, and a stored code stops the challenge page
	 * from sending another, so a working mail path passes here about once per
	 * validity period. A mail server that refuses or cannot be reached leaves
	 * nothing stored, and every reload of the page would open another connection:
	 * the page is one of Nextcloud's own routes and carries no rate limit.
	 *
	 * This sits after the address check on purpose. Counting an account that has
	 * no address at all would spend the budget on a send that never happens, and
	 * would then report a missing address as a mail server that did not answer.
	 *
	 * @throws SendRateLimited when the account has reached the cap. The whole
	 *                         period is the longest it can have to wait, so it is
	 *                         a safe answer to give without asking the limiter.
	 * @throws SendEMailFailed when the limiter cannot answer at all
	 */
	private function throttle(IUser $user): void {
		try {
			$this->limiter->registerUserRequest(self::SEND_LIMIT_IDENTIFIER, self::SEND_LIMIT, self::SEND_PERIOD, $user);
		} catch (IRateLimitExceededException $e) {
			throw new SendRateLimited(self::SEND_PERIOD, previous: $e);
		} catch (Exception $e) {
			// The limiter counts in the database and fails with it. Every caller here
			// handles a failed send; an error from the counter would reach the login
			// page as an exception instead of the message that no code went out.
			$this->logger->error('failed to check the send rate limit for user ' . $user->getUID() . '.', ['exception' => $e]);
			throw new SendEMailFailed(previous: $e);
		}
	}
}

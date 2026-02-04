<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use Exception;
use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\InvalidEmailDomain;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IUser;
use OCP\Mail\IEmailValidator;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;

final class EMailSender implements IEMailSender {
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IMailer $mailer,
		private Defaults $defaults,
		private IAppSettings $settings,
		private IEmailValidator $emailValidator,
	) {
	}

	public function sendChallengeEMail(IUser $user, string $code): void {
		$email = $this->getEmailAddress($user);
		if ($email === null || $email === '') {
			throw new EMailNotSet($user);
		}

		// Validate email format using Nextcloud's validator
		if (!$this->emailValidator->isValid($email)) {
			throw new EMailNotSet($user);
		}

		// Validate email domain if restrictions are configured
		$this->validateEmailDomain($email);

		$this->logger->debug('sending e-mail message to user ' . $user->getUID() . '.');

		$template = $this->mailer->createEMailTemplate('twofactor_email.send');
		$user_at_cloud = $user->getDisplayName() . ' @ ' . $this->defaults->getName();
		$template->setSubject($this->l10n->t('Login attempt for %s', [$user_at_cloud]));

		// Conditionally include header with logo
		if ($this->settings->includeEmailHeader()) {
			$template->addHeader();
		}

		$template->addHeading($this->l10n->t('Your two-factor authentication code is: %s', [$code]));
		$template->addBodyText($this->l10n->t('If you tried to login, please enter that code on %s. If you did not, somebody else did and knows your your e-mail address or username – and your password!', [$this->defaults->getName()]));
		$template->addFooter();

		$message = $this->mailer->createMessage();
		$message->setTo([$email => $user->getDisplayName()]);
		$message->useTemplate($template);

		try {
			$this->mailer->send($message);
		} catch (Exception $e) {
			$this->logger->error('failed sending e-mail message to user ' . $user->getUID() . '.', ['exception' => $e]);
			throw new SendEMailFailed(previous: $e);
		}
	}

	/**
	 * Get the email address to use for 2FA.
	 * If preferLdapEmail is enabled, tries to get email from LDAP backend first.
	 */
	private function getEmailAddress(IUser $user): ?string {
		// For now, always use the primary email
		// LDAP email would require checking the user backend
		// This can be extended to check $user->getBackendClassName() === 'LDAP'
		// and then query the LDAP mail attribute
		return $user->getEMailAddress();
	}

	/**
	 * Validate that the email domain is in the allowed list.
	 *
	 * @throws InvalidEmailDomain if domain is not allowed
	 */
	private function validateEmailDomain(string $email): void {
		$allowedDomains = $this->settings->getAllowedDomains();

		// If no domains are configured, all are allowed
		if (empty($allowedDomains)) {
			return;
		}

		// Extract domain from validated email (email is already validated above)
		$atPosition = strrpos($email, '@');
		if ($atPosition === false) {
			throw new InvalidEmailDomain($email, $allowedDomains);
		}
		$emailDomain = strtolower(substr($email, $atPosition + 1));

		// Check if the email domain is in the allowed list (already lowercase from settings)
		foreach ($allowedDomains as $allowedDomain) {
			if ($emailDomain === $allowedDomain) {
				return;
			}
		}

		throw new InvalidEmailDomain($email, $allowedDomains);
	}
}

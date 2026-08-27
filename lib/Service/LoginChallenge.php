<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\Exception\EMailNotSet;
use OCA\TwoFactorEMail\Exception\ResendTooSoon;
use OCA\TwoFactorEMail\Exception\SendEMailFailed;
use OCA\TwoFactorEMail\Exception\SendRateLimited;
use OCP\IUser;
use OCP\Security\IHasher;
use Psr\Log\LoggerInterface;

final readonly class LoginChallenge implements ILoginChallenge {
	public function __construct(
		private ICodeGenerator $codeGenerator,
		private ICodeStorage $codeStorage,
		private IEMailSender $emailSender,
		private EMailAddressSource $addressSource,
		private IHasher $hasher,
		private IAppSettings $settings,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @throws SendEMailFailed
	 * @throws EMailNotSet
	 */
	#[\Override]
	public function sendChallenge(IUser $user): bool {
		/**
		 * The code is stored hashed, so it stays secret and resistant to timing
		 * attacks even if an attacker managed to elevate their privileges.
		 */
		$address = $this->addressSource->getAddress($user);
		$storedCodeHash = $this->codeStorage->readCode($user->getUID(), $address);

		/**
		 * Nextcloud throttles login retries, but not a reload of the challenge page,
		 * which would otherwise generate and send a new code every time. A stored code
		 * stops that: it can only be read while one exists, is still valid, and was
		 * sent to the address delivery would use now. Because a send that failed stores
		 * nothing, that alone is not enough, so EMailSender also asks Nextcloud's rate
		 * limiter before it opens a connection.
		 */
		if (!is_null($storedCodeHash)) {
			return false;
		}

		$this->issueCode($user, $address);
		return true;
	}

	/**
	 * Send a fresh code on the user's explicit request, throttled by the configured resend cooldown. The existing code
	 * is replaced only after the new one has been sent successfully, so a failed send leaves the current code valid.
	 *
	 * @throws ResendTooSoon if the cooldown since the last code has not elapsed
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	#[\Override]
	public function resendChallenge(IUser $user): void {
		$address = $this->addressSource->getAddress($user);
		$elapsed = $this->codeStorage->secondsSinceLastCode($user->getUID(), $address);
		$cooldown = $this->settings->getResendCooldownSeconds();
		if ($elapsed !== null && $elapsed < $cooldown) {
			throw new ResendTooSoon($cooldown - $elapsed);
		}

		$this->issueCode($user, $address);
	}

	#[\Override]
	public function secondsUntilResendAllowed(IUser $user): int {
		$elapsed = $this->codeStorage->secondsSinceLastCode($user->getUID(), $this->addressSource->getAddress($user));
		if ($elapsed === null) {
			return 0;
		}
		// Cap at the code's remaining validity: once the code expires, a resend is
		// allowed anyway, so the countdown must not outlast the code (relevant
		// only if an admin sets a cooldown longer than the validity).
		$cooldown = min($this->settings->getResendCooldownSeconds(), $this->settings->getCodeValidMinutes() * 60);
		return max(0, $cooldown - $elapsed);
	}

	#[\Override]
	public function verifyChallenge(IUser $user, string $submittedCode): bool {
		$submittedCode = trim($submittedCode);
		$storedCodeHash = $this->codeStorage->readCode($user->getUID(), $this->addressSource->getAddress($user));
		// Accepted residual timing side channel: returning early when no code is
		// stored is measurably faster than the hash comparison, so the response
		// time reveals whether an unexpired code currently exists — but only to
		// someone who already passed the first factor, and the comparison itself
		// stays constant-time (IHasher::verify). A decoy hash comparison on the
		// miss path was deliberately left out; the leak does not justify it.
		if (is_null($storedCodeHash)) {
			$isValid = false;
		} else {
			$isValid = $this->hasher->verify($submittedCode, $storedCodeHash);
		}

		/*
		 * We currently only delete the code if it was successfully used (and the user is verified / logged in).
		 * We could always delete the code, even if the verification failed. That would be more secure but less
		 * convenient. We want users to be able to retry in case they mistyped their code.
		 */
		if ($isValid) {
			$this->codeStorage->deleteCode($user->getUID());
		}
		return $isValid;
	}

	/**
	 * Sends a fresh code and only then persists it, overwriting any existing
	 * code. If sending fails, the previously stored code stays valid.
	 *
	 * @param string|null $address the address this account delivers to, as
	 *                             EMailAddressSource named it for the caller
	 * @throws EMailNotSet
	 * @throws SendEMailFailed
	 */
	private function issueCode(IUser $user, ?string $address): void {
		$generatedCode = $this->codeGenerator->generateChallengeCode();
		try {
			// A code can only be stored against an address, so an account without one
			// stops here — before the sender, which refuses it as well.
			if ($address === null) {
				throw new EMailNotSet($user);
			}
			$this->emailSender->sendChallengeEMail($user, $generatedCode);
			// Only store the code if it could be sent. The sender reads the address from
			// the same source, so the two agree within a request; if they ever did not,
			// the code would be refused rather than accepted for the wrong mailbox.
			$this->codeStorage->writeCode($user->getUID(), $this->hasher->hash($generatedCode), $address);
		} catch (EMailNotSet $e) {
			$this->logger->warning('Could not send 2FA challenge: No email address configured for user.', [
				'exception' => $e,
				'app' => 'twofactor_email',
			]);
			throw $e;
		} catch (SendRateLimited $e) {
			$this->logger->warning('Not sending a 2FA challenge email: the account reached the send rate limit.', [
				'exception' => $e,
				'app' => 'twofactor_email',
			]);
			throw $e;
		} catch (SendEMailFailed $e) {
			$this->logger->error('Failed to send 2FA challenge email due to a mailer error.', [
				'exception' => $e,
				'app' => 'twofactor_email',
			]);
			throw $e;
		}
	}
}

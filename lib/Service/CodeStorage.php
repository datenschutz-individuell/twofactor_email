<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCA\TwoFactorEMail\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\Config\ValueType;

final readonly class CodeStorage implements ICodeStorage {
	private const KEY_CODE = 'code';
	private const KEY_CREATED_AT = 'code_created_at';
	private const KEY_ADDRESS_HASH = 'code_address_hash';

	public function __construct(
		private IAppSettings $settings,
		private IUserConfig $config,
	) {
	}

	#[\Override]
	public function readCode(string $userId, ?string $address): ?string {
		$expiresBefore = time() - $this->settings->getCodeValidMinutes() * 60;
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		if ($createdAt < $expiresBefore) {
			$this->deleteCode($userId);
			return null;
		}

		$code = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE);
		if ($code === '') {
			$this->deleteCode($userId);
			return null;
		}

		$storedHash = $this->config->getValueString($userId, Application::APP_ID, self::KEY_ADDRESS_HASH);
		// An empty stored hash is not a match either: it is what a code written by an
		// earlier version of the app looks like, and one written without an address
		// cannot exist. Both end here, which costs a login in progress one extra code.
		if ($storedHash === '' || !hash_equals($storedHash, $this->addressHash($address))) {
			$this->deleteCode($userId);
			return null;
		}
		return $code;
	}

	#[\Override]
	public function secondsSinceLastCode(string $userId, ?string $address): ?int {
		// Only a still-valid code counts: an expired one is treated as "none"
		// so the user may request a fresh code without waiting. A code for another
		// address counts as none as well, which is what lets the user ask for one
		// at the new address right away.
		if ($this->readCode($userId, $address) === null) {
			return null;
		}
		$createdAt = $this->config->getValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT);
		return max(0, time() - $createdAt);
	}

	#[\Override]
	public function deleteCode(string $userId): bool {
		$existed = $this->config->getValueString($userId, Application::APP_ID, self::KEY_CODE) !== '';
		// A timestamp or a fingerprint without a code still has to go, or a row nothing
		// else looks for would stay behind for good: deleteExpired() finds users by the
		// timestamp alone and would report a removal that never happened, and readCode()
		// gives up before the fingerprint once the code is gone. Reads are cached, the
		// deletes below are not, and readCode() lands here for every user who has no code
		// at all — a missing timestamp reads as 0, which always counts as expired. Asking
		// whether the key exists is what separates that from a stored 0, which has to stay
		// deletable or it would be counted as removed on every run and never go away.
		$hasLeftovers = $this->config->hasKey($userId, Application::APP_ID, self::KEY_CODE)
			|| $this->config->hasKey($userId, Application::APP_ID, self::KEY_CREATED_AT)
			|| $this->config->hasKey($userId, Application::APP_ID, self::KEY_ADDRESS_HASH);
		if (!$existed && !$hasLeftovers) {
			return false;
		}

		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CODE);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_CREATED_AT);
		$this->config->deleteUserConfig($userId, Application::APP_ID, self::KEY_ADDRESS_HASH);
		return $existed;
	}

	#[\Override]
	public function writeCode(string $userId, string $code, string $address, ?int $createdAt = null): void {
		$createdAt ??= time();
		// The stored value is a hash, but flag it sensitive so it is masked in
		// occ config:list and system/support reports.
		$this->config->setValueString($userId, Application::APP_ID, self::KEY_CODE, $code, flags: IUserConfig::FLAG_SENSITIVE);
		$this->config->setValueInt($userId, Application::APP_ID, self::KEY_CREATED_AT, $createdAt);
		// Flagged like the code itself: an unsalted hash of an address confirms a
		// guessed address to anyone reading occ config:list or a support report, and
		// the app keeps addresses out of its own log for the same reason.
		$this->config->setValueString($userId, Application::APP_ID, self::KEY_ADDRESS_HASH, $this->addressHash($address), flags: IUserConfig::FLAG_SENSITIVE);
	}

	#[\Override]
	public function deleteAllCodes(): int {
		$count = count($this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT));
		$this->config->deleteKey(Application::APP_ID, self::KEY_CODE);
		$this->config->deleteKey(Application::APP_ID, self::KEY_CREATED_AT);
		$this->config->deleteKey(Application::APP_ID, self::KEY_ADDRESS_HASH);
		return $count;
	}

	#[\Override]
	public function deleteExpired(): int {
		$expiresBefore = time() - $this->settings->getCodeValidMinutes() * 60;
		$creationTime = $this->config->getValuesByUsers(Application::APP_ID, self::KEY_CREATED_AT, ValueType::INT);

		$count = 0;
		// A user id of digits only arrives as an int: PHP converts numeric array
		// keys, and Nextcloud allows such ids.
		foreach ($creationTime as $userId => $createdAt) {
			if ($createdAt < $expiresBefore) {
				$this->deleteCode((string)$userId);
				$count++;
			}
		}
		return $count;
	}

	/**
	 * A fingerprint of the address a code was sent to, so a stored code can be
	 * checked against the address in force now.
	 *
	 * A plain hash is enough and a hash is what this stores: the address itself
	 * already sits in Nextcloud's own user values, so keeping a second copy here
	 * would add a place to leak without adding anything to read. It is a
	 * comparison value, never a delivery target — the app still asks Nextcloud
	 * where to send, every time.
	 *
	 * Nextcloud lower-cases and trims an address before storing it, and this does
	 * the same rather than relying on it: an address that only differs in case
	 * must not read as a changed one.
	 */
	private function addressHash(?string $address): string {
		return hash('sha256', mb_strtolower(trim($address ?? '')));
	}
}

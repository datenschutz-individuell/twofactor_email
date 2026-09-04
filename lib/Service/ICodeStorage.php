<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

interface ICodeStorage {
	/**
	 * The stored code, or null if none is valid for this address.
	 *
	 * A code counts as valid only while its validity period has not elapsed *and*
	 * the address it was sent to is still the one delivery would use. Passing the
	 * address the caller would deliver to now is therefore not optional — it is
	 * what keeps a code from outliving the mailbox it went to. Whatever fails
	 * either test is deleted on the way out.
	 *
	 * @param string|null $address the address delivery would use now, null when
	 *                             the account has none
	 */
	public function readCode(string $userId, ?string $address): ?string;

	/**
	 * Seconds elapsed since the currently valid code was stored, or null if no
	 * valid code exists for this address. Used to enforce the resend cooldown.
	 */
	public function secondsSinceLastCode(string $userId, ?string $address): ?int;

	/**
	 * @param string $address the address the code was sent to
	 */
	public function writeCode(string $userId, string $code, string $address, ?int $createdAt = null): void;

	/**
	 * Deletes the user's stored code.
	 *
	 * @return bool whether a code was stored (an expired one still counts)
	 */
	public function deleteCode(string $userId): bool;

	/**
	 * Deletes the stored codes of all users.
	 *
	 * @return int the number of users that had a code stored
	 */
	public function deleteAllCodes(): int;

	/**
	 * Deletes all codes whose validity has elapsed.
	 *
	 * @return int the number of expired codes removed
	 */
	public function deleteExpired(): int;
}

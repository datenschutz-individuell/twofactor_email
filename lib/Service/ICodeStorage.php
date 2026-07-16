<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

interface ICodeStorage {
	public function readCode(string $userId): ?string;

	/**
	 * Seconds elapsed since the currently valid code was stored, or null if no
	 * valid code exists. Used to enforce the resend cooldown.
	 */
	public function secondsSinceLastCode(string $userId): ?int;

	public function writeCode(string $userId, string $code, ?int $createdAt = null): void;

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

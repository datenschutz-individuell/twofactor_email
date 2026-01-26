<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

interface ICodeStorage {
	/**
	 * Get stored hash for the user (empty string if none)
	 */
	public function getCodeHash(string $userId): string;

	/**
	 * Get code creation time (0 if none)
	 */
	public function getCodeCreatedAt(string $userId): int;

	/**
	 * Store a hashed code for the user
	 */
	public function writeCode(string $userId, string $code, ?int $createdAt = null): void;

	/**
	 * Delete stored code for user
	 */
	public function deleteCode(string $userId): void;

	/**
	 * Delete all expired codes
	 */
	public function deleteExpired(): void;
}

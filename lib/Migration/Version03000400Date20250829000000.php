<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Migration;

use Closure;
use OCP\Config\IUserConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

/*
 * twofactor_email v2 saved codes without a timestamp to limit their validity. It also did not delete codes.
 * So most codes stored were obsolete since they probably were issued long ago and used already. Nevertheless,
 * twofactor_email from 3.0.3-beta.2 (2025-08-19) to 3.1.0 (2026-05-31) migrated ALL v2 authentication codes.
 *
 * Not deleting codes in v2 was not a security issue since it was not possible to use the 2FA login flow with
 * twofactor_email without creating, storing (overwriting) and sending a new code. However, codes are no longer
 * sent every time a user logs in and chooses the Email Two-Factor provider. We implemented rate limiting to
 * thwart DoS attacks. Since 3.0.5-beta.4 (2026-01-31) codes are only created and sent if no valid code exists.
 *
 * If an instance was migrated from 2.x.x to 3.0.5-beta.4 … 3.1.0, all (already-used and not-yet-used) codes
 * were migrated and stored in the new v3 key format along with the codeCreatedAt timestamp which was set to
 * the time the migration was executed. For these versions, users may not send a code from the update until
 * 10 minutes after (due to implicit rate limiting by the default validity).
 *
 * Since there is no way to fix this in an additional migration, we modified this migration. By that, users
 * will no longer be impacted when updating from v2 to ≥3.1.1.
 *
 * Since we changed this migration due to reasons explained above, we also removed a superfluous changeSchema:
 * We only had a few test users trying out a very early alpha release of this app. Only in these alphas, we
 * introduced a new DB scheme. There is no point in dropping a table that never existed for regular users.
 */

final class Version03000400Date20250829000000 extends SimpleMigrationStep {
	private const APP_ID = 'twofactor_email';
	private const V2_KEY_CODE = 'authentication_code';

	public function __construct(
		private readonly IUserConfig $userConfig,
	) {
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		// Count all users that have a v2 authentication code stored
		try {
			$legacyCodeCount = count($this->userConfig->getValuesByUsers(self::APP_ID, self::V2_KEY_CODE));
		} catch (Throwable $e) {
			$output->warning('Failed to find users with legacy authentication codes: ' . $e->getMessage());
			return;
		}

		// Gracefully exit if no v2 authentication codes are found
		if ($legacyCodeCount === 0) {
			$output->info('No legacy authentication codes found; nothing to migrate.');
			return;
		}

		// Delete all twofactor_email user settings for all users at once
		try {
			$this->userConfig->deleteApp(self::APP_ID);
			$output->info("Discarded $legacyCodeCount legacy authentication codes.");
		} catch (Throwable $e) {
			$output->warning('Failed to delete legacy app settings: ' . $e->getMessage());
		}
	}
}

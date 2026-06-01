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
 * twofactor_email from 3.0.3-beta.2 (2025-08-19) to 3.1.0 (2026-05-31) migrated v2 authentication codes.
 * Thanks to a user issue report, we got aware that there is a logic error and that we over-engineered:
 * v2 saved codes without timestamp to limit their validity. It also did not delete codes, not even after use.
 * We migrated all saved codes. Most of them probably were issued long ago and used already.
 *
 * Not deleting codes in v2 was not a security issue since it was not possible to use the 2FA login flow with
 * twofactor_email without creating, storing (overwriting) and sending a new code.
 * However, since 3.0.5-beta.4 (2026-01-31) codes are no longer sent every time a user logs in and chooses
 * Email as Two-Factor provider. We implemented rate limiting to thwart DoS attacks. Codes are now only created
 * and sent after SendRateLimitPeriodSeconds seconds (which defaults to 600).
 *
 * If an instance was migrated from 2.x.x to 3.0.5-beta.4 … 3.1.0, all (already-used and not-yet-used) codes
 * were migrated and stored in the new v3 key format along with the codeCreatedAt timestamp which was set to
 * the time the migration was executed. For these version users may not be sent a code from the update until
 * 10 minutes after (due to rate limiting).
 *
 * Since there is no way to fix this in an additional migration (which is the way migrations are meant to be
 * used), we modified this migration to at least reduce impact for updates/migrations from v2 to v3 that happen
 * in the future, upgrading to a version ≥3.1.1.
 *
 * We only had a few users trying out the very early stage of this app. Only there we introduced a new DB scheme.
 * There is no point in dropping a table that never existed for regular users. Since we change this migration due
 * to reasons explained above anyway, we also remove the superfluous changeSchema.
 */
final class Version03000400Date20250829000000 extends SimpleMigrationStep {
	private const APP_ID = 'twofactor_email';
	private const V2_KEY_CODE = 'authentication_code';

	public function __construct(
		private IUserConfig $userConfig,
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

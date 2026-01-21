<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\TwoFactorEMail\Service;

class ConstantApplicationSettings implements IApplicationSettings
{
	public function getCodeValidSeconds(): int
	{
		return 60 * 10; // 10 minutes
	}

	public function getMaxResendAttempts(): int
	{
		return 3; // Max 3 codes per window
	}

	public function getResendWindowSeconds(): int
	{
		return 60 * 5; // 5 minute window
	}

	public function getCodeLength(): int
	{
		return 8; // 8 digits for higher entropy (100M combinations)
	}
}

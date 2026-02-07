<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

declare(strict_types=1);

namespace OCA\TwoFactorEMail\Service;

final class ConstantAppSettings implements IAppSettings {
	public function getCodeLength(): int {
		return 6;
	}
	public function getCodeValidSeconds(): int {
		return 60 * 10; // 10 minutes
	}
}

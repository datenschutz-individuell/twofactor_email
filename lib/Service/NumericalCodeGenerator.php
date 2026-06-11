<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\Security\ISecureRandom;

final class NumericalCodeGenerator implements ICodeGenerator {
	public function __construct(
		private readonly ISecureRandom $secureRandom,
		private readonly IAppSettings $settings,
	) {
	}

	public function generateChallengeCode(): string {
		return $this->secureRandom->generate(
			$this->settings->getCodeLength(),
			ISecureRandom::CHAR_DIGITS,
		);
	}
}

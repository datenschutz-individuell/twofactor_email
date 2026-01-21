<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

use OCP\Security\ISecureRandom;

class NumericalCodeGenerator implements ICodeGenerator {
	public function __construct(
		private ISecureRandom $secureRandom,
		private IApplicationSettings $settings,
	) {
	}

	public function generateChallengeCode(): string {
		$length = $this->settings->getCodeLength();
		return $this->secureRandom->generate($length, ISecureRandom::CHAR_DIGITS);
	}
}

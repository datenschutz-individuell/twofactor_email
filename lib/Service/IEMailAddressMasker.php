<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Service;

interface IEMailAddressMasker {
	/**
	 * What maskForUI() returns for an address it cannot take apart. It names no
	 * address, so a caller that wants to show one has nothing to show.
	 */
	public const HIDDEN = '*@*';

	public function maskForUI(string $emailAddress): string;
}

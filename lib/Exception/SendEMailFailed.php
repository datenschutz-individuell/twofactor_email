<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Exception;

use Exception;

/**
 * Not final: SendRateLimited is the one case that needs to be told apart while
 * still being treated as a failed send everywhere a code was expected.
 */
class SendEMailFailed extends Exception {
}

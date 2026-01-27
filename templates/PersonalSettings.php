<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCP\Util;

// Without addScript, the settings section E-Mail remains empty.
Util::addScript('twofactor_email', 'twofactor_email-personal_settings');
// Without addStyle, the switch is rendered weirdly.
Util::addStyle('twofactor_email', 'twofactor_email-personal_settings');
// It was not sufficient to just addScript or addStyle without file, or with arbitrary file name,
// the file parameter must be exactly the … div id? There seems to be some NC magic here…
?>

<div id="twofactor_email-personal_settings"></div>

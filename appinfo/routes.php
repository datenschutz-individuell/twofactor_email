<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		[
			// Nextcloud expects a class StateController in 'StateController.php' with a 'save' method.
			'name' => 'State#save',
			'url' => '/state/save',
			'verb' => 'POST',
		],
		[
			// ChallengeController#resend: user-requested resend of the login code
			'name' => 'Challenge#resend',
			'url' => '/challenge/resend',
			'verb' => 'POST',
		],
		[
			// Nextcloud expects a class AdminSettingsController in 'AdminSettingsController.php' with a 'save' method.
			'name' => 'AdminSettings#save',
			'url' => '/admin/save',
			'verb' => 'POST',
		],
		[
			// Nextcloud expects a class AdminSettingsController in 'AdminSettingsController.php' with a 'reset' method.
			'name' => 'AdminSettings#reset',
			'url' => '/admin/reset',
			'verb' => 'POST'
		],
	]
];

<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

/*
 * Nextcloud USES the class 'StateController(.php)' as the 'name' refers to 'State' here. So they must match, and
 * the controller MUST be named <(route)name>Controller.php. 'update' is a method thereof.
 */

return [
	'routes' => [
        [
            'name' => 'State#update',
            'url' => '/personal/save',
            'verb' => 'POST',
        ],
        [
            'name' => 'AdminSettings#update',
            'url' => '/admin/save',
            'verb' => 'POST',
        ],
        [
            'name' => 'AdminSettings#reset',
            'url' => '/admin/reset',
            'verb' => 'POST'
        ],
	]
];

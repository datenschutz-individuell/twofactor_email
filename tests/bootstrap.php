<?php

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-only
 */

if (file_exists(__DIR__ . '/../../../lib/base.php')) {
	require_once __DIR__ . '/../../../lib/base.php';
	require_once __DIR__ . '/../../../tests/bootstrap.php';

	\OC_App::loadApp('twofactor_email');
} else {
	spl_autoload_register(function (string $class) {
		if (str_starts_with($class, 'OCP\\') || str_starts_with($class, 'NCU\\')) {
			include_once __DIR__ . '/../vendor/nextcloud/ocp/' . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
		}
	});
}

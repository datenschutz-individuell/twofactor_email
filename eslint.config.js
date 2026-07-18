/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommendedJavascript } from '@nextcloud/eslint-config'

// noinspection JSUnusedGlobalSymbols -- consumed by ESLint itself, never imported
export default [
	...recommendedJavascript,
	{
		// Test files are handled by @nextcloud/eslint-config without the jsdoc
		// plugin, so scoping this rule out of them keeps its plugin reference valid.
		ignores: ['**/*.test.js', '**/*.spec.js'],
		rules: {
			'jsdoc/require-jsdoc': [
				'warn',
				{
					publicOnly: {
						ancestorsOnly: true,
					},
				},
			],
		},
	},
]

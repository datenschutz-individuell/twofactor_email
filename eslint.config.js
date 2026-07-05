/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { recommendedJavascript } from '@nextcloud/eslint-config'

// noinspection JSUnusedGlobalSymbols -- consumed by ESLint itself, never imported
export default [
	...recommendedJavascript,
	{
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

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vitest/config'

// noinspection JSUnusedGlobalSymbols
export default defineConfig({
	// The Vue plugin compiles single-file components so component tests can
	// mount them; logic-only tests are unaffected.
	plugins: [vue()],
	test: {
		// Logic tests default to the lightweight node environment; component
		// tests opt into happy-dom per file via `// @vitest-environment happy-dom`.
		environment: 'node',
		include: ['src/**/*.test.js'],
		coverage: {
			provider: 'v8',
			reporter: ['text', 'lcov'],
			reportsDirectory: './coverage',
		},
	},
})

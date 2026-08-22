/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createAppConfig } from '@nextcloud/vite-config'

// noinspection JSUnusedGlobalSymbols
export default createAppConfig({
	personal_settings: 'src/PersonalSettings.js',
	admin_settings: 'src/AdminSettings.js',
	login_setup: 'src/LoginSetup.js',
	login_challenge: 'src/LoginChallenge.js',
}, {
	extractLicenseInformation: {
		validateLicenses: true,
	},
	// The default of @nextcloud/vite-config puts everything two entry points
	// share into a single chunk. That chunk then carries the settings pages
	// into the login challenge, the one page every user loads on every login:
	// 87 kB gzipped instead of 39 kB. Splitting per set of entry points first
	// keeps the login challenge down to what it actually uses.
	//
	// These groups are the upstream default without its leading `shared` group,
	// numbers included. Compare them whenever @nextcloud/vite-config moves to
	// another pre-release, or to a new minor or major: a retuned threshold or a
	// new group would otherwise be dropped silently, and nothing here measures
	// the login challenge. A patch release of a stable line can be trusted.
	codeSplitting: {
		groups: [
			{ name: 'common', entriesAware: true, entriesAwareMergeThreshold: 90_000, minSize: 70_000 },
			{ name: 'remain' },
		],
	},
})

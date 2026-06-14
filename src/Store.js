/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { persistAdminSettings, persistState, resetAdminSettings } from './services/StateManager.js'

export const pinia = createPinia()

export const usePersonalSettingsStore = defineStore('personalSettings', {
	state: () => ({
		enabled: false,
		hasEmail: false,
		maskedEmail: 'UNEXPECTED ERROR: no email address set',
		email: 'UNEXPECTED ERROR: no email address set',
		error: false,
	}),
	actions: {
		/**
		 * Loads the initial state from Nextcloud into the Store.
		 * Only tries to fetch the given keys.
		 * All initial state keys must be the same as in the store.
		 *
		 * @param {string} keys keys to load from initial state
		 */
		loadInitialState(...keys) {
			const initialState = {}
			for (const key of keys) {
				initialState[key] = loadState('twofactor_email', key)
			}
			this.$patch(initialState)
		},
		async save() {
			const previousState = this.enabled
			const result = await persistState(this.enabled)

			this.$patch({
				// Reset the switch on error
				enabled: result.error ? !previousState : (result.enabled ?? this.enabled),
				error: result.error,
			})
		},

		async enable() {
			this.enabled = true
			await this.save()
		},
	},
})

export const useAdminSettingsStore = defineStore('adminSettings', {
	state: () => ({
		codeLength: null,
		codeValidMinutes: null,
		eMailSubject: null,
		eMailTemplate: null,
		error: false,
	}),
	actions: {
		/**
		 * Loads the initial state from Nextcloud into the Store.
		 * Only tries to fetch the given keys.
		 * All initial state keys must be the same as in the store.
		 *
		 * @param {string} keys keys to load from initial state
		 */
		loadInitialState(...keys) {
			const initialState = {}
			for (const key of keys) {
				initialState[key] = loadState('twofactor_email', key)
			}
			this.$patch(initialState)
		},
		async save() {
			const result = await persistAdminSettings({
				codeLength: this.codeLength,
				codeValidMinutes: this.codeValidMinutes,
				eMailSubject: this.eMailSubject,
				eMailTemplate: this.eMailTemplate,
			})

			this.$patch({
				codeLength: result.codeLength ?? this.codeLength,
				codeValidMinutes: result.codeValidMinutes ?? this.codeValidMinutes,
				eMailSubject: result.eMailSubject ?? this.eMailSubject,
				eMailTemplate: result.eMailTemplate ?? this.eMailTemplate,
				error: result.error,
			})

			// Return result so useFieldWithAutosave can evaluate success/error per field
			return result
		},
		async reset() {
			const result = await resetAdminSettings()
			if (typeof result.error !== 'string') {
				this.$patch({
					codeLength: result.codeLength,
					codeValidMinutes: result.codeValidMinutes,
					eMailSubject: result.eMailSubject,
					eMailTemplate: result.eMailTemplate,
					error: null,
				})
			}
			return result
		},
	},
})

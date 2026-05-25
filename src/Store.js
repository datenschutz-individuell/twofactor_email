/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { persist, persistAdminSettings, resetAdminSettings } from './services/StateManager.js'

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
			const result = await persist(this.enabled)
			this.$patch({
				enabled: result.enabled ?? this.enabled,
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
		codeLength:                 null,
		codeValidMinutes:           null,
		sendRateLimitAttempts:      null,
		sendRateLimitPeriodSeconds: null,
		eMailTemplate:              null,
		error:                      false,
		// success removed: managed per field in useFieldWithAutosave
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
				codeLength:                 this.codeLength,
				codeValidMinutes:           this.codeValidMinutes,
				sendRateLimitAttempts:      this.sendRateLimitAttempts,
				sendRateLimitPeriodSeconds: this.sendRateLimitPeriodSeconds,
				eMailTemplate:              this.eMailTemplate,
			})

			this.$patch({
				codeLength:                 result.codeLength                 ?? this.codeLength,
				codeValidMinutes:           result.codeValidMinutes           ?? this.codeValidMinutes,
				sendRateLimitAttempts:      result.sendRateLimitAttempts      ?? this.sendRateLimitAttempts,
				sendRateLimitPeriodSeconds: result.sendRateLimitPeriodSeconds ?? this.sendRateLimitPeriodSeconds,
				eMailTemplate:              result.eMailTemplate              ?? this.eMailTemplate,
				error:                      result.error,
			})

			// Return result so useFieldWithAutosave can evaluate success/error per field
			return result
		},
		async reset() {
			console.log('store.reset called')
			const result = await resetAdminSettings()
			console.log('resetAdminSettings result:', result)
			if (typeof result.error !== 'string') {
				this.$patch({
					codeLength:                 result.codeLength,
					codeValidMinutes:           result.codeValidMinutes,
					sendRateLimitAttempts:      result.sendRateLimitAttempts,
					sendRateLimitPeriodSeconds: result.sendRateLimitPeriodSeconds,
					eMailTemplate:              result.eMailTemplate,
					error:                      null,
				})
			}
			return result
		},
	},
})

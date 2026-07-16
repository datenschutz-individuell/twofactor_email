/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { nextTick, reactive, ref, watch } from 'vue'
import Logger from '../Logger.js'

/**
 * Manages a single shared debounced timer and loading state across all admin
 * settings fields, with per-field success feedback. Eliminates race conditions
 * that arise when each field triggers its own independent save request.
 *
 * All fields share one save cycle: the last keystroke in any field restarts
 * the shared timer, and exactly one request is in flight at a time.
 *
 * @param {object} store       - Pinia store with .save() and the field keys
 * @param {string[]} fieldKeys - Names of all store fields to manage
 * @param {number} debounceMs  - Debounce delay in milliseconds
 * @param {number} successMs   - How long the success indicator remains visible
 */
export function useAdminSettings(store, fieldKeys, debounceMs = 1500, successMs = 1200) {
	// One shared loading state — only one request in flight at a time
	const loading = ref(false)

	// Per-field input values and success indicators.
	// reactive() wrapping enables automatic ref unwrapping in templates
	// and allows watch(() => inputValues[key]) to track changes correctly.
	const inputValues = reactive(Object.fromEntries(fieldKeys.map((key) => [key, store[key]])))
	const successRefs = reactive(Object.fromEntries(fieldKeys.map((key) => [key, null])))
	const errorMessages = reactive(Object.fromEntries(fieldKeys.map((key) => [key, ''])))
	const successTimers = Object.fromEntries(fieldKeys.map((key) => [key, null]))

	// Numeric limits per field, provided by the server, so the messages below
	// can name the allowed range instead of duplicating the values.
	const limits = loadState('twofactor_email', 'limits', {})

	// User-facing message per validation error code. Built here (not at module
	// load) so t() runs once l10n is available.
	const errorMessageByCode = {
		'code-length-out-of-range': t('twofactor_email', 'The code length must be between {min} and {max} characters.', { min: limits.codeLength?.min, max: limits.codeLength?.max }),
		'code-valid-minutes-out-of-range': t('twofactor_email', 'The validity must be between {min} and {max} minutes.', { min: limits.codeValidMinutes?.min, max: limits.codeValidMinutes?.max }),
		'resend-minutes-out-of-range': t('twofactor_email', 'The resend cooldown must be between {min} and {max} minutes.', { min: limits.codeResendMinutes?.min, max: limits.codeResendMinutes?.max }),
		'email-subject-too-long': t('twofactor_email', 'The subject must not exceed {max} characters.', { max: limits.eMailSubject?.max }),
		'email-subject-must-be-single-line': t('twofactor_email', 'The subject must be a single line.'),
		'email-template-too-long': t('twofactor_email', 'The body must not exceed {max} characters.', { max: limits.eMailTemplate?.max }),
		'email-code-placeholder-missing': t('twofactor_email', 'The body must contain the {code} placeholder.'),
	}

	/**
	 * Flags each field named in the given field->code map with its message;
	 * all other fields go idle, since nothing is saved on an error.
	 *
	 * @param {Object<string, string>} fieldErrors - field name to error code
	 */
	function applyErrors(fieldErrors) {
		for (const key of fieldKeys) {
			clearTimeout(successTimers[key])
			const code = fieldErrors[key]
			successRefs[key] = code ? false : null
			errorMessages[key] = code ? (errorMessageByCode[code] ?? '') : ''
		}
	}

	// Shared debounce timer — restarted by any field change
	let debounceTimer = null

	// Sync inputValues once the store is populated by loadInitialState.
	// The watch on inputValues is deferred to nextTick so that this initial
	// sync does not trigger scheduleSave.
	for (const key of fieldKeys) {
		watch(
			() => store[key],
			(val) => {
				if (inputValues[key] === null && val !== null) {
					inputValues[key] = val
				}
			},
			{ immediate: true },
		)
	}

	// Register user-change watches only after the initial render cycle
	nextTick().then(() => {
		for (const key of fieldKeys) {
			watch(() => inputValues[key], () => scheduleSave())
		}
	})

	/**
	 * Runs scheduleAndSave and logs any unexpected errors.
	 * Used instead of bare scheduleAndSave() calls to avoid ignored Promise warnings.
	 */
	function triggerSave() {
		scheduleAndSave().catch((e) => Logger.error('Unexpected error in scheduleAndSave', e))
	}

	/**
	 * Schedules a save after the debounced delay.
	 * Any field change restarts the shared timer, so only one request fires after the last keystroke.
	 */
	function scheduleSave() {
		clearTimeout(debounceTimer)
		debounceTimer = setTimeout(() => {
			if (loading.value) {
				// A save is already in flight; re-schedule after it completes
				const unwatch = watch(loading, (isLoading) => {
					if (!isLoading) {
						unwatch()
						triggerSave()
					}
				})
				return
			}
			triggerSave()
		}, debounceMs)
	}

	async function scheduleAndSave() {
		loading.value = true
		store.$patch({ error: null })

		try {
			// Write all current inputValues into the store before saving
			for (const key of fieldKeys) {
				store[key] = inputValues[key]
			}
			// The backend validates every field and returns the full field->code
			// map, so all currently invalid fields stay flagged (not just the
			// one last edited).
			const result = await store.save()
			if (result?.errors && typeof result.errors === 'object') {
				// Backend rejected specific fields — flag only those
				applyErrors(result.errors)
			} else if (typeof result?.error === 'string') {
				// Unexpected failure (network etc.) — flag all fields, no message
				for (const key of fieldKeys) {
					successRefs[key] = false
					errorMessages[key] = ''
				}
			} else {
				// Success — every field was saved
				for (const key of fieldKeys) {
					clearTimeout(successTimers[key])
					successRefs[key] = true
					errorMessages[key] = ''
					successTimers[key] = setTimeout(() => {
						successRefs[key] = null
					}, successMs)
				}
			}
		} catch (saveError) {
			store.$patch({ error: 'save-failed' })
			for (const key of fieldKeys) {
				successRefs[key] = false
				errorMessages[key] = ''
			}
			Logger.error('Could not persist admin settings', saveError)
		} finally {
			loading.value = false
		}
	}

	return { inputValues, loading, successRefs, errorMessages }
}

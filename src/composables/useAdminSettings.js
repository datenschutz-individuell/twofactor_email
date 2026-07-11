/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { nextTick, reactive, ref, watch } from 'vue'
import Logger from '../Logger.js'

// Maps each backend validation error code to the settings field it concerns,
// so a rejected save highlights only the offending field(s).
const ERROR_FIELD_BY_CODE = {
	'code-length-out-of-range': 'codeLength',
	'code-valid-minutes-out-of-range': 'codeValidMinutes',
	'resend-minutes-out-of-range': 'codeResendMinutes',
	'email-subject-too-long': 'eMailSubject',
	'email-subject-must-be-single-line': 'eMailSubject',
	'email-template-too-long': 'eMailTemplate',
	'email-code-placeholder-missing': 'eMailTemplate',
}

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
	const successTimers = Object.fromEntries(fieldKeys.map((key) => [key, null]))

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

	/**
	 * Validates all input values before saving.
	 * Returns an array of field keys that failed validation,
	 * or an empty array if all values are valid.
	 *
	 * @return {string[]} field keys with validation errors
	 */
	function validate() {
		const errors = []
		// The code must reach the user: an empty body falls back to the default
		// which contains {code}, so only a customized body can lose it.
		const body = inputValues.eMailTemplate ?? ''
		if (body !== '' && !body.includes('{code}')) {
			errors.push('eMailTemplate')
			Logger.warn('Email body does not contain the {code} placeholder')
		}
		// The subject must stay a single line (email header)
		if (/[\r\n]/.test(inputValues.eMailSubject ?? '')) {
			errors.push('eMailSubject')
			Logger.warn('Email subject must not contain line breaks')
		}
		return errors
	}

	async function scheduleAndSave() {
		loading.value = true
		store.$patch({ error: null })

		// Validate before saving — set error state on failing fields and abort
		const invalidFields = validate()
		if (invalidFields.length > 0) {
			for (const key of invalidFields) {
				successRefs[key] = false
			}
			loading.value = false
			return
		}

		try {
			// Write all current inputValues into the store before saving
			for (const key of fieldKeys) {
				store[key] = inputValues[key]
			}
			const result = await store.save()
			if (Array.isArray(result?.errors)) {
				// Backend rejected specific fields — flag only those; the rest
				// stay idle since nothing is saved on a validation error.
				const failed = new Set(result.errors.map((code) => ERROR_FIELD_BY_CODE[code]).filter(Boolean))
				for (const key of fieldKeys) {
					clearTimeout(successTimers[key])
					successRefs[key] = failed.has(key) ? false : null
				}
			} else if (typeof result?.error === 'string') {
				// Unexpected failure (network etc.) — flag all fields
				for (const key of fieldKeys) {
					successRefs[key] = false
				}
			} else {
				// Success — every field was saved
				for (const key of fieldKeys) {
					clearTimeout(successTimers[key])
					successRefs[key] = true
					successTimers[key] = setTimeout(() => {
						successRefs[key] = null
					}, successMs)
				}
			}
		} catch (saveError) {
			store.$patch({ error: 'save-failed' })
			for (const key of fieldKeys) {
				successRefs[key] = false
			}
			Logger.error('Could not persist admin settings', saveError)
		} finally {
			loading.value = false
		}
	}

	return { inputValues, loading, successRefs }
}

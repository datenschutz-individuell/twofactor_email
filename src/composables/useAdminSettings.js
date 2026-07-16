/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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

	// User-facing message per validation error code. Built here (not at module
	// load) so t() runs once l10n is available.
	const errorMessageByCode = {
		'code-length-out-of-range': t('twofactor_email', 'The code length is outside the allowed range.'),
		'code-valid-minutes-out-of-range': t('twofactor_email', 'The validity is outside the allowed range.'),
		'resend-minutes-out-of-range': t('twofactor_email', 'The resend cooldown is outside the allowed range.'),
		'email-subject-too-long': t('twofactor_email', 'The subject is too long.'),
		'email-subject-must-be-single-line': t('twofactor_email', 'The subject must be a single line.'),
		'email-template-too-long': t('twofactor_email', 'The body is too long.'),
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

	/**
	 * Validates all input values before saving.
	 * Returns a field->code map (same vocabulary as the backend), or an empty
	 * object if all values are valid.
	 *
	 * @return {Object<string, string>} field name to validation error code
	 */
	function validate() {
		const errors = {}
		// The code must reach the user: an empty body falls back to the default
		// which contains {code}, so only a customized body can lose it.
		const body = inputValues.eMailTemplate ?? ''
		if (body !== '' && !body.includes('{code}')) {
			errors.eMailTemplate = 'email-code-placeholder-missing'
			Logger.warn('Email body does not contain the {code} placeholder')
		}
		// The subject must stay a single line (email header)
		if (/[\r\n]/.test(inputValues.eMailSubject ?? '')) {
			errors.eMailSubject = 'email-subject-must-be-single-line'
			Logger.warn('Email subject must not contain line breaks')
		}
		return errors
	}

	async function scheduleAndSave() {
		loading.value = true
		store.$patch({ error: null })

		// Validate before saving — flag failing fields and abort
		const invalidFields = validate()
		if (Object.keys(invalidFields).length > 0) {
			applyErrors(invalidFields)
			loading.value = false
			return
		}

		try {
			// Write all current inputValues into the store before saving
			for (const key of fieldKeys) {
				store[key] = inputValues[key]
			}
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

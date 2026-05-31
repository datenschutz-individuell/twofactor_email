/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref, watch } from 'vue'
import Logger from '../Logger.js'

/**
 * Manages a single shared debounce timer and loading state across all admin
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
export function useAdminSettings(store, fieldKeys, debounceMs = 1500, successMs = 1200) {  // ← renamed + new signature
    // One shared loading state — only one request in flight at a time
    const loading = ref(false)

    // Per-field input values and success indicators
    const inputValues = Object.fromEntries(
        fieldKeys.map(key => [key, ref(store[key])])
    )
    const successRefs = Object.fromEntries(
        fieldKeys.map(key => [key, ref(null)])
    )
    let successTimers = Object.fromEntries(
        fieldKeys.map(key => [key, null])
    )

    // Shared debounce timer — restarted by any field change
    let debounceTimer = null

    /**
     * Schedules a save after the debounce delay. Any field change restarts
     * the shared timer, so only one request fires after the last keystroke.
     */
    function scheduleSave() {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            if (loading.value) {
                // A save is already in flight; re-schedule after it completes
                const unwatch = watch(loading, (isLoading) => {
                    if (!isLoading) {
                        unwatch()
                        scheduleAndSave()
                    }
                })
                return
            }
            scheduleAndSave()
        }, debounceMs)
    }

    async function scheduleAndSave() {
        loading.value = true
        store.$patch({ error: null })
        try {
            // Write all current inputValues into the store before saving
            for (const key of fieldKeys) {
                store[key] = inputValues[key].value
            }
            const result = await store.save()
            // Set per-field success based on shared result
            for (const key of fieldKeys) {
                if (typeof result?.error !== 'string') {
                    clearTimeout(successTimers[key])
                    successRefs[key].value = true
                    successTimers[key] = setTimeout(() => {
                        successRefs[key].value = null
                    }, successMs)
                } else {
                    successRefs[key].value = false
                }
            }
        } catch (saveError) {
            store.$patch({ error: 'save-failed' })
            for (const key of fieldKeys) {
                successRefs[key].value = false
            }
            Logger.error('Could not persist admin settings', saveError)
        } finally {
            loading.value = false
        }
    }

    // Watch each input value and restart the shared debounce timer
    for (const key of fieldKeys) {
        watch(inputValues[key], () => schedulesave())
    }

    return { inputValues, loading, successRefs }
}

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref, watch } from 'vue'
import { useDebouncedRef } from './useDebounce.js'
import Logger from '../Logger.js'

/**
 * Manages debounce, loading state, per-field success feedback, and save logic
 * for a single auto-saving admin settings field.
 *
 * @param {object} store       - Pinia store with .save() and the field
 * @param {string} field       - Name of the store field (e.g. 'codeValidMinutes')
 * @param {number} debounceMs  - Debounce delay in milliseconds
 * @param {number} successMs   - How long the success indicator remains visible
 */
export function useFieldWithAutosave(store, field, debounceMs = 1500, successMs = 1200) {
    const loading = ref(false)
    const success = ref(null)  // Managed per field, not in the store
    let successTimer = null

    const { immediate: inputValue, debounced: debouncedValue } =
        useDebouncedRef(store[field], debounceMs)

    async function save() {
        loading.value = true
        store.$patch({ error: null })
        try {
            store[field] = debouncedValue.value
            const result = await store.save()
            if (typeof result?.error !== 'string') {
                clearTimeout(successTimer)
                success.value = true
                successTimer = setTimeout(() => { success.value = null }, successMs)
            } else {
                success.value = false
            }
        } catch (saveError) {
            store.$patch({ error: 'save-failed' })
            success.value = false
            Logger.error(`Could not persist field "${field}"`, saveError)
        } finally {
            loading.value = false
        }
    }

    watch(debouncedValue, async () => {
        if (loading.value) {
            const unwatch = watch(loading, async (isLoading) => {
                if (!isLoading) {
                    unwatch()
                    await save()
                }
            })
            return
        }
        await save()
    })

    return { inputValue, loading, success }
}

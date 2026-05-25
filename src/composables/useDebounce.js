/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { ref, watch } from 'vue'

export function useDebouncedRef(initialValue, delay = 1000) {
    const immediate = ref(initialValue)
    const debounced = ref(initialValue)
    let timer = null

    watch(immediate, (newVal) => {
        clearTimeout(timer)
        timer = setTimeout(() => {
            debounced.value = newVal
        }, delay)
    })

    return { immediate, debounced }
}

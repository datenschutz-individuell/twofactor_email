<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
  <div id="twofactor_email-admin_settings">
    <NcSettingsSection :name="t('twofactor_email', 'Two-Factor email provider')">
      <NcTextField v-model="inputValue"
                   label="Code Validity in Minutes"
                   type="number"
                   :loading="loading"
                   :success="store.success === true"
                   :error="store.success === false">
      </NcTextField>
    </NcSettingsSection>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'
import { useDebouncedRef } from '../composables/useDebounce.js'
import Logger from '../Logger.js'
import { useAdminSettingsStore } from '../Store.js'

const loading = ref(false)
const store = useAdminSettingsStore()
store.loadInitialState('codeValidMinutes')
const { immediate: inputValue, debounced: debouncedValue } = useDebouncedRef(store.codeValidMinutes, 1500)
let successTimer = null

watch(debouncedValue, async (val) => {
  store.codeValidMinutes = val
  if (loading.value) {
    const unwatch = watch(loading, async (isLoading) => {
      if (!isLoading) {
        unwatch()
        await onUpdate()
      }
    })
    return
  }

  await onUpdate()
})

async function onUpdate() {
  loading.value = true
  store.$patch({ error: null })
  try {
    await store.save()
    if (store.success === true) {
      clearTimeout(successTimer)
      successTimer = setTimeout(() => store.$patch({ success: null }), 1200)
    }
  } catch (saveError) {
    // Backend error while trying to persist
    store.$patch({ error: 'save-failed' })
    Logger.error('Could not persist settings', saveError)
  } finally {
    loading.value = false
  }
}
</script>

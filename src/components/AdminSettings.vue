<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
  <div id="twofactor_email-admin_settings">
    <NcSettingsSection :name="t('twofactor_email', 'Two-Factor email provider')">
      <NcTextField v-model="store.codeValidMinutes" type="number" label="Code Validity in Minutes"></NcTextField>
    </NcSettingsSection>
  </div>
</template>

<script setup>
import { ref } from "vue";
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '@nextcloud/l10n'

import Logger from '../Logger.js'
import { useAdminSettingsStore } from "../Store.js"

const store = useAdminSettingsStore()
store.loadInitialState('codeValidMinutes')

const loading = ref(false)

async function onUpdate() {
  if (loading.value) {
    Logger.debug('still loading -> ignoring event')
    return
  }
  loading.value = true

  // Reset possible previous errors upon consecutive retries
  store.$patch({ error: null })

  try {
    await store.save()
  } catch (saveError) {
    // backend error while trying to persist
    store.$patch({ error: 'save-failed' })
    Logger.error('Could not persist settings', saveError)
  } finally {
    loading.value = false
  }
}
</script>

<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
  <div id="twofactor_email-admin_settings">
    <NcSettingsSection :name="t('twofactor_email', 'Two-Factor email provider')"
					   :description="t('twofactor_email', 'These system wide settings are saved automatically shortly after the last keypress.')">

      <!-- Numeric fields: min="1" prevents zero and negative values at the browser level;
           @keydown guard blocks direct keyboard entry of '-' and 'e' (scientific notation) -->
      <div class="numeric-fields-grid">
        <NcTextField v-for="field in numericFields"
                     :key="field.key"
                     v-model="inputValues[field.key]"
                     :label="field.label"
                     type="number"
                     min="1"
                     :loading="loading"
                     :success="successRefs[field.key] === true"
                     :error="successRefs[field.key] === false"
                     @keydown="blockInvalidNumericInput" />
      </div>

      <!-- Email template: monospace textarea with code-editor appearance -->
      <div class="email-template-field">
        <NcTextArea v-model="inputValues.eMailTemplate"
                    class="email-template-field__textarea"
                    :label="t('twofactor_email', 'Email Template')"
                    :hide-label="true"
                    :loading="loading"
                    :success="successRefs.eMailTemplate === true"
                    :error="successRefs.eMailTemplate === false" />
      </div>

      <div class="reset-section">
        <NcButton type="tertiary-no-background"
                  :disabled="resetting"
                  @click="onReset">
          <template #icon>
            <NcIconSvgWrapper :path="mdiUndo" :size="20" />
          </template>
          {{ t('twofactor_email', 'Reset all Two-Factor email app-wide admin settings to their defaults') }}
        </NcButton>
      </div>

    </NcSettingsSection>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { mdiUndo } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { useAdminSettingsStore } from '../Store.js'
import { useAdminSettings } from '../composables/useAdminSettings.js'

const resetting = ref(false)

const store = useAdminSettingsStore()
store.loadInitialState('codeLength', 'codeValidMinutes', 'sendRateLimitAttempts', 'sendRateLimitPeriodSeconds', 'eMailTemplate')

const numericFields = [
  { key: 'codeLength',                 label: t('twofactor_email', 'Code Length') },
  { key: 'codeValidMinutes',           label: t('twofactor_email', 'Code Validity (Minutes)') },
  { key: 'sendRateLimitAttempts',      label: t('twofactor_email', 'Send Rate Limit Attempts') },
  { key: 'sendRateLimitPeriodSeconds', label: t('twofactor_email', 'Send Rate Limit Period (Seconds)') },
]

const textAreaFields = [
  { key: 'eMailTemplate' },
]

const allFields = [...numericFields, ...textAreaFields]

const { inputValues, loading, successRefs } = useAdminSettings(
    store,
    allFields.map(f => f.key)
)

/**
 * Blocks '-' (minus) and 'e' (scientific notation) in number inputs.
 * The min="1" attribute handles values after entry; this blocks them
 * during typing so invalid characters never appear in the field.
 *
 * @param {KeyboardEvent} event - The keyboard event from the input field
 */
function blockInvalidNumericInput(event) {
  if (event.key === '-' || event.key === 'e') {
    event.preventDefault()
  }
}

async function onReset() {
	resetting.value = true
	try {
		const result = await store.reset()
		if (typeof result?.error !== 'string') {
			for (const key of allFields.map(f => f.key)) {
				inputValues[key] = store[key]
			}
		}
	} catch (e) {
		console.error('reset failed:', e)
	} finally {
		resetting.value = false
	}
}
</script>

<style scoped>
.numeric-fields-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px 16px;
}

/* Stack to single column on narrow screens */
@media (max-width: 640px) {
  .numeric-fields-grid {
    grid-template-columns: 1fr;
  }
}

.email-template-field {
  margin-top: 8px;
}

.email-template-field__label {
  display: block;
  margin-bottom: 4px;
  font-weight: 500;
  color: var(--color-text-maxcontrast);
  font-size: 14px;
}

/* Force monospace / code-editor appearance on the inner textarea element */
.email-template-field__textarea :deep(textarea) {
  font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
  font-size: 13px;
  line-height: 1.6;
  min-height: 220px;
  resize: vertical;
  tab-size: 2;
  white-space: pre-wrap;
  background-color: var(--color-background-dark);
  border-radius: var(--border-radius);
}

.reset-section {
  margin-top: 16px;
}
</style>

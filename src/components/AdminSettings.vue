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
							 :error="successRefs[field.key] === false"
							 :label="field.label"
							 :loading="loading"
							 :success="successRefs[field.key] === true"
							 min="1"
							 type="number"
							 @keydown="blockInvalidNumericInput" />
			</div>

			<!-- Email template parts: empty fields fall back to the localized
				 default shown as placeholder. Available placeholders in all
				 fields: {code}, {user}, {cloud}, {validity}. -->
			<div class="email-text-fields">
				<NcTextField v-for="field in textFields"
							 :key="field.key"
							 v-model="inputValues[field.key]"
							 :error="successRefs[field.key] === false"
							 :helper-text="field.helperText"
							 :label="field.label"
							 :loading="loading"
							 :placeholder="defaults[field.key]"
							 :success="successRefs[field.key] === true" />
			</div>

			<!-- Email body: monospace textarea with code-editor appearance -->
			<div class="email-template-field">
				<NcTextArea v-for="field in textAreaFields"
							:key="field.key"
							v-model="inputValues[field.key]"
							:error="successRefs[field.key] === false"
							:helper-text="t('twofactor_email', 'The email body text to be sent to the user. Leave empty to use the localized default. Available placeholders: {code}, {user}, {cloud}, {validity}. The code must appear in the heading or in the body.')"
							:hide-label="true"
							:label="t('twofactor_email', 'Email Body (plain text)')"
							:loading="loading"
							:placeholder="defaults[field.key]"
							:success="successRefs[field.key] === true"
							class="email-template-field__textarea" />
			</div>

			<div class="reset-section">
				<NcButton :disabled="resetting"
						  type="tertiary-no-background"
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
import { loadState } from '@nextcloud/initial-state'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { mdiUndo } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import { useAdminSettingsStore } from '../Store.js'
import { useAdminSettings } from '../composables/useAdminSettings.js'
import Logger from '../Logger.js'

const resetting = ref(false)

const store = useAdminSettingsStore()
store.loadInitialState('codeLength', 'codeValidMinutes', 'eMailSubject', 'eMailHeading', 'eMailTemplate', 'eMailFooter')

// Localized default texts, shown as placeholders in empty fields
const defaults = loadState('twofactor_email', 'eMailDefaults', {})

const numericFields = [
	{ key: 'codeLength', label: t('twofactor_email', 'Code Length (Characters)') },
	{ key: 'codeValidMinutes', label: t('twofactor_email', 'Code Validity (Minutes)') },
]

const textFields = [
	{
		key: 'eMailSubject',
		label: t('twofactor_email', 'Email Subject'),
		helperText: t('twofactor_email', 'Leave empty to use the localized default. Note: a {code} in the subject may show up in notification previews on lock screens.'),
	},
	{
		key: 'eMailHeading',
		label: t('twofactor_email', 'Email Heading'),
		helperText: t('twofactor_email', 'Leave empty to use the localized default.'),
	},
	{
		key: 'eMailFooter',
		label: t('twofactor_email', 'Email Footer'),
		helperText: t('twofactor_email', 'Leave empty to use the standard footer of this Nextcloud instance.'),
	},
]

const textAreaFields = [
	{ key: 'eMailTemplate' },
]

const allFields = [...numericFields, ...textFields, ...textAreaFields]

const { inputValues, loading, successRefs } = useAdminSettings(
	store,
	allFields.map(f => f.key),
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
		Logger.error('reset failed:', e)
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
	margin-bottom: 24px;
}

/* Stack to single column on narrow screens */
@media (max-width: 640px) {
	.numeric-fields-grid {
		grid-template-columns: 1fr;
	}
}

.email-text-fields {
	display: grid;
	gap: 8px;
	margin-bottom: 16px;
}

.email-template-field {
	margin-top: 8px;
}

/* Force monospace / code-editor appearance on the inner textarea element */
.email-template-field__textarea :deep(textarea) {
	font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
	font-size: 13px;
	line-height: 1.6;
	min-height: 220px;
	margin-top: 24px;
	margin-bottom: 8px;
	resize: vertical;
	tab-size: 2;
	white-space: pre-wrap;
	/* noinspection CssUnresolvedCustomProperty */
	background-color: var(--color-background-dark, darkgray);
	/* noinspection CssUnresolvedCustomProperty */
	border-radius: var(--border-radius, 4px);
}

.reset-section {
	margin-top: 16px;
}
</style>

<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="twofactor_email-admin_settings">
		<NcSettingsSection :name="t('twofactor_email', 'Two-Factor email provider')"
						   :description="t('twofactor_email', 'These system wide settings are saved automatically shortly after the last keypress.')">

			<!-- Group: authentication code -->
			<fieldset class="settings-group">
				<h3 class="settings-group__heading">
					{{ t('twofactor_email', 'Authentication code') }}
				</h3>
				<p class="settings-group__description">
					{{ t('twofactor_email', 'Length and validity of the one-time codes sent via email.') }}
				</p>

				<!-- Numeric fields: min="1" prevents zero and negative values at the browser level;
					 @keydown guard blocks direct keyboard entry of '-' and 'e' (scientific notation) -->
				<div v-for="field in numericFields"
					 :key="field.key"
					 class="labeled-field">
					<label :for="`twofactor_email-${field.key}`" class="labeled-field__label">
						{{ field.label }}
					</label>
					<NcTextField :id="`twofactor_email-${field.key}`"
								 v-model="inputValues[field.key]"
								 :error="successRefs[field.key] === false"
								 :label-outside="true"
								 :loading="loading"
								 :success="successRefs[field.key] === true"
								 class="labeled-field__input"
								 min="1"
								 type="number"
								 @keydown="blockInvalidNumericInput" />
				</div>
			</fieldset>

			<!-- Group: email template -->
			<fieldset class="settings-group">
				<h3 class="settings-group__heading">
					{{ t('twofactor_email', 'Email template') }}
				</h3>
				<p class="settings-group__description">
					{{ t('twofactor_email', 'This template defines the email that delivers the one-time code to users. It is partially dynamic: placeholders are filled individually each time an email is sent.') }}
				</p>
				<ul class="settings-group__hints">
					<li>{{ t('twofactor_email', 'Placeholders: {code} (one-time code), {user} (display name), {cloud} (instance name), {validity} (validity in minutes). A customized body must contain {code}; in the body all placeholders are highlighted.') }}</li>
					<li>{{ t('twofactor_email', 'Defaults: empty fields use the localized default text, shown as a hint inside the field.') }}</li>
					<li>{{ t('twofactor_email', 'Formatting: a blank line starts a new paragraph, a single line break becomes a line break.') }}</li>
					<li>{{ t('twofactor_email', 'Links: [URL="https://example.org"]Text[/URL] or [URL]https://example.org[/URL] — in the footer shown as "Text (URL)".') }}</li>
					<li>{{ t('twofactor_email', 'Images: [IMG="https://example.org/image.png"]Description[/IMG] (https only); many clients load remote images only after confirmation.') }}</li>
					<li>{{ t('twofactor_email', 'Logo: an empty body shows the standard logo header; in a customized body place {logo} anywhere or omit it. The logo is rendered small (at most 250 pixels and 20% of the email width).') }}</li>
					<li>{{ t('twofactor_email', 'Subject: a {code} here may show up in notification previews on lock screens.') }}</li>
				</ul>

				<div v-for="field in textFields"
					 :key="field.key"
					 class="labeled-field">
					<label :for="`twofactor_email-${field.key}`" class="labeled-field__label">
						{{ field.label }}
					</label>
					<NcTextField :id="`twofactor_email-${field.key}`"
								 v-model="inputValues[field.key]"
								 :error="successRefs[field.key] === false"
								 :label-outside="true"
								 :loading="loading"
								 :placeholder="defaults[field.key]"
								 :success="successRefs[field.key] === true"
								 class="labeled-field__input" />
				</div>

				<!-- Email body: label above, monospace textarea with code-editor appearance -->
				<div v-for="field in textAreaFields"
					 :key="field.key"
					 class="labeled-field labeled-field--stacked">
					<label :for="`twofactor_email-${field.key}`" class="labeled-field__label">
						{{ field.label }}
					</label>
					<NcTextArea :id="`twofactor_email-${field.key}`"
								v-model="inputValues[field.key]"
								:error="successRefs[field.key] === false"
								:label-outside="true"
								:loading="loading"
								:placeholder="defaults[field.key]"
								:success="successRefs[field.key] === true"
								class="labeled-field__input email-template-field__textarea" />
				</div>
			</fieldset>

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
store.loadInitialState('codeLength', 'codeValidMinutes', 'eMailSubject', 'eMailTemplate', 'eMailFooter')

// Localized default texts, shown as placeholders in empty fields
const defaults = loadState('twofactor_email', 'eMailDefaults', {})

const numericFields = [
	{ key: 'codeLength', label: t('twofactor_email', 'Code Length (Characters)') },
	{ key: 'codeValidMinutes', label: t('twofactor_email', 'Code Validity (Minutes)') },
]

const textFields = [
	{ key: 'eMailSubject', label: t('twofactor_email', 'Email Subject') },
	{ key: 'eMailFooter', label: t('twofactor_email', 'Email Footer') },
]

const textAreaFields = [
	{ key: 'eMailTemplate', label: t('twofactor_email', 'Email Body (plain text)') },
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
/* Visual grouping with heading and description (fieldset reset) */
.settings-group {
	border: 0;
	margin: 0 0 32px;
	padding: 0;
	min-width: 0;
}

.settings-group__heading {
	font-size: 16px;
	font-weight: bold;
	margin-bottom: 4px;
}

.settings-group__description {
	/* noinspection CssUnresolvedCustomProperty */
	color: var(--color-text-maxcontrast, gray);
	margin-bottom: 16px;
	max-width: 64em;
}

.settings-group__hints {
	/* noinspection CssUnresolvedCustomProperty */
	color: var(--color-text-maxcontrast, gray);
	list-style: disc;
	margin: -8px 0 16px;
	max-width: 64em;
	padding-inline-start: 24px;
}

.settings-group__hints li {
	margin-bottom: 4px;
}

/* One row per field: real label on the left, input indented to a common edge */
.labeled-field {
	display: grid;
	grid-template-columns: 220px 1fr;
	gap: 4px 16px;
	align-items: center;
	margin-bottom: 8px;
	max-width: 64em;
}

/* Multi-line fields get their label above instead */
.labeled-field--stacked {
	grid-template-columns: 1fr;
	align-items: start;
}

/* Stack all labels above their field on narrow screens */
@media (max-width: 640px) {
	.labeled-field {
		grid-template-columns: 1fr;
	}
}

/* Force monospace / code-editor appearance on the inner textarea element */
.email-template-field__textarea :deep(textarea) {
	font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
	font-size: 13px;
	line-height: 1.6;
	min-height: 220px;
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

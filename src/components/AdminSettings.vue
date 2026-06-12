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
				<h3>{{ t('twofactor_email', 'Authentication code') }}</h3>
				<p>{{ t('twofactor_email', 'Length and validity of the one-time codes sent via email.') }}</p>

				<div class="numeric-fields-grid">
					<LabeledField id="twofactor_email-codeLength"
								  v-model="inputValues.codeLength"
								  :label="t('twofactor_email', 'Length (characters)')"
								  :loading="loading"
								  :result="successRefs.codeLength"
								  type="number" />
					<LabeledField id="twofactor_email-codeValidMinutes"
								  v-model="inputValues.codeValidMinutes"
								  :label="t('twofactor_email', 'Validity (minutes)')"
								  :loading="loading"
								  :result="successRefs.codeValidMinutes"
								  type="number" />
				</div>
			</fieldset>

			<!-- Group: email template -->
			<fieldset class="settings-group">
				<h3>{{ t('twofactor_email', 'Email template') }}</h3>
				<p>{{ t('twofactor_email', 'This template defines the email that delivers the one-time code to users. It is partially dynamic: placeholders are filled individually each time an email is sent.') }}</p>

				<LabeledField id="twofactor_email-eMailSubject"
							  v-model="inputValues.eMailSubject"
							  :helper-text="t('twofactor_email', 'A {code} here may show up in notification previews on lock screens.')"
							  :label="t('twofactor_email', 'Subject')"
							  :loading="loading"
							  :placeholder="defaults.eMailSubject"
							  :result="successRefs.eMailSubject" />
				<LabeledField id="twofactor_email-eMailTemplate"
							  v-model="inputValues.eMailTemplate"
							  :helper-text="bodyHelperText"
							  :label="t('twofactor_email', 'Body')"
							  :loading="loading"
							  :placeholder="defaults.eMailTemplate"
							  :result="successRefs.eMailTemplate"
							  class="body-field"
							  type="textarea" />
			</fieldset>

			<NcButton :disabled="resetting"
					  type="tertiary-no-background"
					  @click="onReset">
				<template #icon>
					<NcIconSvgWrapper :path="mdiUndo" :size="20" />
				</template>
				{{ t('twofactor_email', 'Reset all Two-Factor email app-wide admin settings to their defaults') }}
			</NcButton>

		</NcSettingsSection>
	</div>
</template>

<script setup>
import { ref } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import { mdiUndo } from '@mdi/js'
import { t } from '@nextcloud/l10n'
import LabeledField from './LabeledField.vue'
import { useAdminSettingsStore } from '../Store.js'
import { useAdminSettings } from '../composables/useAdminSettings.js'
import Logger from '../Logger.js'

const resetting = ref(false)

const fieldKeys = ['codeLength', 'codeValidMinutes', 'eMailSubject', 'eMailTemplate']

const store = useAdminSettingsStore()
store.loadInitialState(...fieldKeys)

// Localized default texts, shown as placeholders in empty fields
const defaults = loadState('twofactor_email', 'eMailDefaults', {})

const { inputValues, loading, successRefs } = useAdminSettings(store, fieldKeys)

// Placeholder and formatting reference, shown below the body field
// (rendered line by line via white-space: pre-line)
const bodyHelperText = [
	t('twofactor_email', 'Placeholders: {code} (one-time code), {user} (display name), {cloud} (instance name), {validity} (validity in minutes). A customized body must contain {code}; in the body all placeholders are highlighted.'),
	t('twofactor_email', 'Defaults: empty fields use the localized default text, shown as a hint inside the field.'),
	t('twofactor_email', 'Formatting: a blank line starts a new paragraph, a single line break becomes a line break.'),
	t('twofactor_email', 'Links: [URL="https://example.org"]Text[/URL] or [URL]https://example.org[/URL].'),
	t('twofactor_email', 'Images: [IMG="https://example.org/image.png"]Description[/IMG] (https only); many clients load remote images only after confirmation.'),
	t('twofactor_email', 'Logo: place {logo} anywhere or omit it. The logo is rendered small (at most 250 pixels and 20% of the email width).'),
].join('\n')

async function onReset() {
	resetting.value = true
	try {
		const result = await store.reset()
		if (typeof result?.error !== 'string') {
			for (const key of fieldKeys) {
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
/* Visual grouping; headings and lists keep their browser defaults.
   Secondary text (descriptions, hints) uses the NC muted text color. */
.settings-group {
	border: 0;
	margin: 0 0 32px;
	padding: 0;
	max-width: 64em;
}

.settings-group p {
	/* noinspection CssUnresolvedCustomProperty */
	color: var(--color-text-maxcontrast, gray);
}

/* The two short numeric fields share one row (stacked on narrow screens) */
.numeric-fields-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 0 16px;
}

@media (max-width: 640px) {
	.numeric-fields-grid {
		grid-template-columns: 1fr;
	}
}

/* Monospace editor appearance for the body template */
.body-field :deep(textarea) {
	font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
	min-height: 220px;
	resize: vertical;
}

/* The body helper text is a multi-line reference — keep its line breaks */
.body-field :deep(.textarea__helper-text-message) {
	white-space: pre-line;
	align-items: start;
}
</style>

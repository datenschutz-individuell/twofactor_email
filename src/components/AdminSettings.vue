<!--
  - SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcSettingsSection :name="t('twofactor_email', 'Two-Factor Email')"
		:description="t('twofactor_email', 'Configure two-factor email authentication settings.')">

		<!-- CODE SETTINGS -->
		<fieldset>
			<legend>{{ t('twofactor_email', 'Code Settings') }}</legend>

			<div class="setting-row">
				<label for="code-length">{{ t('twofactor_email', 'Code length') }}</label>
				<NcSelect v-model="settings.codeLength"
					:options="codeLengthOptions"
					:reduce="opt => opt.value"
					input-id="code-length"
					@update:model-value="save" />
				<span class="hint">{{ t('twofactor_email', 'characters') }}</span>
			</div>

			<div class="setting-row">
				<label for="code-validity">{{ t('twofactor_email', 'Code validity') }}</label>
				<NcSelect v-model="settings.codeValidMinutes"
					:options="validityOptions"
					:reduce="opt => opt.value"
					input-id="code-validity"
					@update:model-value="save" />
				<span class="hint">{{ t('twofactor_email', 'minutes') }}</span>
			</div>

			<div class="setting-row">
				<label for="max-attempts">{{ t('twofactor_email', 'Max failed attempts') }}</label>
				<NcSelect v-model="settings.maxVerificationAttempts"
					:options="attemptsOptions"
					:reduce="opt => opt.value"
					input-id="max-attempts"
					@update:model-value="save" />
				<span class="hint">{{ t('twofactor_email', 'before code invalidation') }}</span>
			</div>
		</fieldset>

		<!-- CODE FORMAT -->
		<fieldset>
			<legend>{{ t('twofactor_email', 'Code Format') }}</legend>

			<NcCheckboxRadioSwitch v-model="settings.useAlphanumericCodes"
				type="switch"
				@update:model-value="save">
				{{ t('twofactor_email', 'Use alphanumeric codes (stronger)') }}
			</NcCheckboxRadioSwitch>
			<p class="hint">
				{{ codeEntropyHint }}
			</p>
		</fieldset>

		<!-- RATE LIMITING -->
		<fieldset>
			<legend>{{ t('twofactor_email', 'Rate Limiting') }}</legend>

			<div class="setting-row">
				<label for="rate-limit-attempts">{{ t('twofactor_email', 'Max emails') }}</label>
				<NcSelect v-model="settings.rateLimitAttempts"
					:options="rateLimitAttemptsOptions"
					:reduce="opt => opt.value"
					input-id="rate-limit-attempts"
					@update:model-value="save" />
				<span class="hint">{{ t('twofactor_email', 'per period') }}</span>
			</div>

			<div class="setting-row">
				<label for="rate-limit-period">{{ t('twofactor_email', 'Period duration') }}</label>
				<NcSelect v-model="settings.rateLimitPeriodMinutes"
					:options="rateLimitPeriodOptions"
					:reduce="opt => opt.value"
					input-id="rate-limit-period"
					@update:model-value="save" />
				<span class="hint">{{ t('twofactor_email', 'minutes') }}</span>
			</div>
		</fieldset>

		<!-- EMAIL SETTINGS -->
		<fieldset>
			<legend>{{ t('twofactor_email', 'Email') }}</legend>

			<NcCheckboxRadioSwitch v-model="settings.includeEmailHeader"
				type="switch"
				@update:model-value="save">
				{{ t('twofactor_email', 'Include logo in email header') }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<!-- DOMAIN RESTRICTIONS -->
		<fieldset>
			<legend>{{ t('twofactor_email', 'Domain Restrictions') }}</legend>

			<div class="setting-row">
				<label for="allowed-domains">{{ t('twofactor_email', 'Allowed email domains') }}</label>
				<NcTextField id="allowed-domains" v-model="allowedDomainsText"
					:placeholder="t('twofactor_email', 'company.com, corp.example.org')"
					@update:model-value="saveDomainsDebounced" />
			</div>
			<p class="hint">
				{{ t('twofactor_email', 'Comma-separated list. Leave empty to allow all domains.') }}
			</p>

			<NcCheckboxRadioSwitch v-model="settings.preferLdapEmail"
				type="switch"
				@update:model-value="save">
				{{ t('twofactor_email', 'Prefer LDAP email (non-user-writable)') }}
			</NcCheckboxRadioSwitch>
			<p class="hint">
				{{ t('twofactor_email', 'Uses email from LDAP backend if available. Provides better security as users cannot change their 2FA email.') }}
			</p>
		</fieldset>

		<!-- STATUS -->
		<div v-if="saving" class="status saving">
			{{ t('twofactor_email', 'Saving...') }}
		</div>
		<div v-else-if="saved" class="status saved">
			{{ t('twofactor_email', 'Settings saved') }}
		</div>
		<div v-else-if="error" class="status error">
			{{ t('twofactor_email', 'Error saving settings') }}
		</div>
	</NcSettingsSection>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'

import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'

// Load initial state
const initialSettings = loadState('twofactor_email', 'adminSettings', {})

const settings = reactive({
	codeLength: initialSettings.codeLength ?? 6,
	codeValidMinutes: initialSettings.codeValidMinutes ?? 10,
	maxVerificationAttempts: initialSettings.maxVerificationAttempts ?? 3,
	useAlphanumericCodes: initialSettings.useAlphanumericCodes ?? false,
	rateLimitAttempts: initialSettings.rateLimitAttempts ?? 10,
	rateLimitPeriodMinutes: initialSettings.rateLimitPeriodMinutes ?? 10,
	skipSendIfCodeExists: initialSettings.skipSendIfCodeExists ?? false,
	includeEmailHeader: initialSettings.includeEmailHeader ?? true,
	allowedDomains: initialSettings.allowedDomains ?? [],
	preferLdapEmail: initialSettings.preferLdapEmail ?? false,
})

const allowedDomainsText = ref(settings.allowedDomains.join(', '))

const saving = ref(false)
const saved = ref(false)
const error = ref(false)

// Options for dropdowns
const codeLengthOptions = [
	{ value: 4, label: '4' },
	{ value: 5, label: '5' },
	{ value: 6, label: '6' },
	{ value: 7, label: '7' },
	{ value: 8, label: '8' },
	{ value: 10, label: '10' },
	{ value: 12, label: '12' },
]

const validityOptions = [
	{ value: 1, label: '1' },
	{ value: 2, label: '2' },
	{ value: 5, label: '5' },
	{ value: 10, label: '10' },
	{ value: 15, label: '15' },
	{ value: 20, label: '20' },
	{ value: 30, label: '30' },
]

const attemptsOptions = [
	{ value: 1, label: '1' },
	{ value: 2, label: '2' },
	{ value: 3, label: '3' },
	{ value: 5, label: '5' },
	{ value: 10, label: '10' },
]

const rateLimitAttemptsOptions = [
	{ value: 3, label: '3' },
	{ value: 5, label: '5' },
	{ value: 10, label: '10' },
	{ value: 15, label: '15' },
	{ value: 20, label: '20' },
	{ value: 50, label: '50' },
]

const rateLimitPeriodOptions = [
	{ value: 1, label: '1' },
	{ value: 5, label: '5' },
	{ value: 10, label: '10' },
	{ value: 15, label: '15' },
	{ value: 30, label: '30' },
	{ value: 60, label: '60' },
]

// Computed entropy hint
const codeEntropyHint = computed(() => {
	const length = settings.codeLength
	const alphanumeric = settings.useAlphanumericCodes
	const combinations = alphanumeric
		? Math.pow(36, length)
		: Math.pow(10, length)
	const formatted = combinations.toLocaleString()
	return t('twofactor_email', '{combinations} possible combinations', { combinations: formatted })
})

// Debounced save for text fields
let saveTimeout = null
function saveDomainsDebounced() {
	if (saveTimeout) {
		clearTimeout(saveTimeout)
	}
	saveTimeout = setTimeout(() => {
		settings.allowedDomains = allowedDomainsText.value
			.split(',')
			.map(d => d.trim())
			.filter(d => d !== '')
		save()
	}, 500)
}

// Save settings to server
async function save() {
	saving.value = true
	saved.value = false
	error.value = false

	try {
		const url = generateUrl('/apps/twofactor_email/admin_settings')
		const response = await axios.post(url, {
			codeLength: settings.codeLength,
			codeValidMinutes: settings.codeValidMinutes,
			maxVerificationAttempts: settings.maxVerificationAttempts,
			useAlphanumericCodes: settings.useAlphanumericCodes,
			rateLimitAttempts: settings.rateLimitAttempts,
			rateLimitPeriodMinutes: settings.rateLimitPeriodMinutes,
			skipSendIfCodeExists: settings.skipSendIfCodeExists,
			includeEmailHeader: settings.includeEmailHeader,
			allowedDomains: settings.allowedDomains,
			preferLdapEmail: settings.preferLdapEmail,
		})

		if (response.data.success) {
			saved.value = true
			setTimeout(() => { saved.value = false }, 2000)
		} else {
			error.value = true
		}
	} catch (e) {
		console.error('Failed to save settings:', e)
		error.value = true
	} finally {
		saving.value = false
	}
}
</script>

<style scoped>
fieldset {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 16px;
	margin-bottom: 16px;
}

legend {
	font-weight: bold;
	padding: 0 8px;
}

.setting-row {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 8px;
}

.setting-row label {
	min-width: 150px;
}

.setting-row :deep(.v-select) {
	min-width: 80px;
}

.hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	margin-left: 8px;
}

p.hint {
	margin: 4px 0 12px 0;
}

.status {
	margin-top: 16px;
	padding: 8px 12px;
	border-radius: var(--border-radius);
}

.status.saving {
	background: var(--color-background-hover);
}

.status.saved {
	background: var(--color-success);
	color: white;
}

.status.error {
	background: var(--color-error);
	color: white;
}
</style>

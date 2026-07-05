<!--
  - SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- Sync strings with LoginSetup.vue -->
<template>
	<div id="twofactor_email-personal_settings">
		<div v-if="store.hasEmail">
			<p>
				<NcCheckboxRadioSwitch
					v-model="store.enabled"
					:loading="loading"
					type="switch"
					@update:modelValue="onUpdate">
					{{ t('twofactor_email', 'Use two-factor authentication via email') }}
				</NcCheckboxRadioSwitch>
			</p>
			<p v-if="store.enabled">
				{{ t('twofactor_email', 'Codes will be sent to your primary email address:') }} <b>{{ store.email }}</b>
			</p>
		</div>
		<div v-else>
			<span class="notice">
				{{ t('twofactor_email', 'You cannot enable two-factor authentication via email. You need to set a primary email address (in your personal settings) first.')
				}}
			</span>
		</div>
		<span v-if="store.error === 'password-confirmation-failed'" class="error">
			{{ t('twofactor_email', 'Password confirmation failed. Please try again.') }}
		</span>
		<span v-else-if="store.error === 'no-email'" class="error">
			{{ t('twofactor_email', 'Apparently your previously configured email address just vanished.') }}
		</span>
		<span v-else-if="store.error === 'save-failed'" class="error">
			{{ t('twofactor_email', 'Could not enable/disable two-factor authentication via email.') }}
		</span>
		<span v-else-if="store.error" class="error">
			{{ t('twofactor_email', 'Unhandled error!') }}
		</span>
	</div>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { ref } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import Logger from '../Logger.js'
import { usePersonalSettingsStore } from '../Store.js'

import '@nextcloud/password-confirmation/style.css'

const store = usePersonalSettingsStore()
store.loadInitialState('enabled', 'hasEmail', 'email')

const loading = ref(false)

async function onUpdate() {
	if (loading.value) {
		Logger.debug('still loading -> ignoring event')
		return
	}
	loading.value = true

	// Save the current "enabled" value to be used in the frontend in case of an error in the backend.
	// Since the toggle already happened (only then onUpdate is called), that's the inverted value.
	const previousState = !store.enabled

	// Reset possible previous errors upon consecutive retries
	store.$patch({ error: null })

	try {
		await confirmPassword()
	} catch (passwordError) {
		// password required but not correct or not given, e.g., aborted
		store.enabled = previousState
		store.$patch({ error: 'password-confirmation-failed' })
		Logger.error('Password confirmation failed', passwordError)
		loading.value = false
		return
	}

	try {
		await store.save()
		// Reset the UI switch if StateController sends HTTP error code
		if (store.error) {
			store.enabled = previousState
		}
	} catch (saveError) {
		// backend error while trying to persistState
		store.enabled = previousState
		store.$patch({ error: 'save-failed' })
		Logger.error('Could not persistState settings', saveError)
	} finally {
		loading.value = false
	}
}
</script>

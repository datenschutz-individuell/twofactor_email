<!--
  - SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- Sync strings with PersonalSettings.vue -->
<template>
	<div id="twofactor_email-login_setup">
		<span v-if="store.error === 'no-email'" class="error">
			{{ t('twofactor_email', 'No email address available, please contact your administrator.')
			}}
		</span>
		<span v-else-if="store.error === 'save-failed'" class="error">
			{{ t('twofactor_email', 'Could not enable/disable two-factor authentication via email.') }}
		</span>
		<span v-else-if="store.error" class="error">
			{{ t('twofactor_email', 'Unhandled error!') }}
		</span>
		<div v-else-if="loading" class="loading" style="min-height: 50px" />
		<div v-else>
			<p>{{ t('twofactor_email', 'Two-factor authentication via email was enabled.') }}</p>
			<p>
				{{ t('twofactor_email', 'Codes will be sent to your primary email address:') }} <b>{{ store.maskedEmail
				}}</b>
			</p>
			<form method="POST">
				<button>{{ t('twofactor_email', 'Proceed') }}</button>
			</form>
		</div>
	</div>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import { onMounted, ref } from 'vue'
import Logger from '../Logger.js'
import { usePersonalSettingsStore } from '../Store.js'

const store = usePersonalSettingsStore()
store.loadInitialState('maskedEmail')

const loading = ref(true)

onMounted(async () => {
	try {
		await store.enable()

		// Show errors by disabling "loading"
		if (store.error) {
			loading.value = false
		}
	} catch (error) {
		Logger.error('failed to enable two-factor email', error)
		store.$patch({ error: 'save-failed' })
	} finally {
		loading.value = false
	}
})

</script>

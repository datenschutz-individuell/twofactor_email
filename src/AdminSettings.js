/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import AdminSettings from './components/AdminSettings.vue'

const pinia = createPinia()
const View = createApp(AdminSettings)
	.use(pinia)
View.mount('#twofactor_email-admin-settings')

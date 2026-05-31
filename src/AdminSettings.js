/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'

import { pinia } from './Store.js'
import AdminSettings from './components/AdminSettings.vue'

const View = createApp(AdminSettings)
    .use(pinia)
View.mount('#twofactor_email-admin_settings')

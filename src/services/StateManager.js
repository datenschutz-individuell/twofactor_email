/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Logger from '../Logger.js'

/**
 * @param {boolean} enabled Enable or disable?
 * @return {Promise}
 */
export function persist(enabled) {
	const url = generateUrl('/apps/twofactor_email/personal/save')
	const data = {
		state: enabled,
	}

	Logger.debug('sending two-factor email state change request', data)
	return Axios.post(url, data)
		.then(resp => {
			if (resp.status !== 200) {
				return { error: 'save-failed' }
			} else {
				return resp.data
			}
		}).catch(_ => {
			return { error: 'save-failed' }
		})
}

export function persistAdminSettings(settings) {
	const url = generateUrl('/apps/twofactor_email/admin/save')

	Logger.debug('sending two-factor email admin settings', settings)
	return Axios.post(url, settings)
		.then(resp => {
			if (resp.status !== 200) {
				return { error: 'save-failed' }
			} else {
				return resp.data
			}
		}).catch(_ => {
			return { error: 'save-failed' }
		})
}

export function resetAdminSettings() {
	const url = generateUrl('/apps/twofactor_email/admin/reset')

	Logger.debug('resetting two-factor email admin settings to defaults')
	return Axios.post(url)
		.then(resp => {
			if (resp.status !== 200) {
				return { error: 'reset-failed' }
			} else {
				return resp.data
			}
		}).catch(_ => {
			return { error: 'reset-failed' }
		})
}

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import Logger from '../Logger.js'

/**
 * @typedef {{
 *   enabled: boolean,
 *   error: string|boolean
 * }} PersistStateResult
 */

/**
 * Makes the backend save the twofactor "email" enabled/disabled state.
 *
 * @param {boolean} enabled Enable or disable?
 * @return {Promise<PersistStateResult>}
 */
/**
 * Makes the backend save the twofactor "email" enabled/disabled state.
 *
 * @param {boolean} enabled Enable or disable?
 * @return {Promise<PersistStateResult>}
 */
export function persistState(enabled) {
	const url = generateUrl('/apps/twofactor_email/state/save')
	const data = {
		state: enabled,
	}

	Logger.debug('sending two-factor email state change request', data)
	return Axios.post(url, data)
		.then(resp => {
			// here HTTP 2xx only since 4xx error codes directly go to catch
			return resp.data
		}).catch(error => {
			Logger.error('failed to save two-factor email state', error)

			if (error.response && error.response.data && error.response.data.error) {
				return {
					enabled: false,
					error: error.response.data.error,
				}
			}

			// fallback for network or other unexpected errors
			return {
				enabled: false,
				error: 'save-failed',
			}
		})
}


/**
 * @typedef {{
 *   codeLength: number,
 *   codeValidMinutes: number,
 *   eMailTemplate: string,
 *   error: boolean
 * }} PersistAdminSettingsResult
 */

/**
 * Makes the backend save the admin settings.
 *
 * @param {object} settings The admin settings to save
 * @return {Promise<PersistAdminSettingsResult>}
 */
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

/**
 * Resets the admin settings to their default values.
 *
 * @return {Promise<PersistAdminSettingsResult>}
 */
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

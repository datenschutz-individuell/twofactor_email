/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './LoginChallenge.css'

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import Logger from './Logger.js'

// Wire up the "Send a new code" button on the 2FA challenge page. The page is
// server-rendered (templates/LoginChallenge.php), so this is plain DOM, not Vue.
document.addEventListener('DOMContentLoaded', () => {
	const button = document.querySelector('.twofactor_email-resend')
	const status = document.querySelector('.twofactor_email-resend-status')
	if (!button || !status) {
		return
	}

	const showStatus = (message) => {
		status.textContent = message
		status.hidden = false
	}

	/**
	 * Disable the button for the given number of seconds (resend cooldown).
	 *
	 * @param {number} seconds time until the button becomes usable again
	 */
	const disableFor = (seconds) => {
		button.disabled = true
		window.setTimeout(() => { button.disabled = false }, seconds * 1000)
	}

	button.addEventListener('click', async () => {
		button.disabled = true
		try {
			await Axios.post(generateUrl('/apps/twofactor_email/challenge/resend'))
			showStatus(t('twofactor_email', 'A new code was sent. Only the new code is valid now.'))
			const input = document.querySelector('.twofactor_email-challenge-form input[name="challenge"]')
			if (input) {
				input.value = ''
				input.focus()
			}
			button.disabled = false
		} catch (error) {
			const data = error.response && error.response.data
			if (error.response && error.response.status === 429) {
				const retryAfter = (data && data.retryAfter) || 60
				showStatus(t('twofactor_email', 'Please wait {seconds} seconds before requesting a new code.', { seconds: retryAfter }))
				disableFor(retryAfter)
			} else if (data && data.error === 'no-email') {
				showStatus(t('twofactor_email', 'No email address is configured for your account.'))
			} else {
				Logger.error('failed to resend two-factor email code', error)
				showStatus(t('twofactor_email', 'The code could not be sent. Please try again later.'))
				button.disabled = false
			}
		}
	})
})

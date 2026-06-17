/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './LoginChallenge.css'

import Axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import Logger from './Logger.js'

// Resend control on the 2FA challenge page (server-rendered template, so plain
// DOM, not Vue). It shows a clickable "Send a new code" link only while a resend
// is actually possible; during the cooldown it shows a live countdown instead,
// so the user never clicks and fails.
document.addEventListener('DOMContentLoaded', () => {
	const line = document.querySelector('.twofactor_email-resend-line')
	const link = document.querySelector('.twofactor_email-resend')
	const status = document.querySelector('.twofactor_email-resend-status')
	if (!line || !link || !status) {
		return
	}

	const cooldown = Number(line.dataset.cooldown) || 0
	let timer = null

	const clearTimer = () => {
		if (timer !== null) {
			window.clearInterval(timer)
			timer = null
		}
	}

	// Offer the clickable resend link and clear any status text.
	const offerResend = () => {
		clearTimer()
		status.textContent = ''
		link.hidden = false
	}

	// Hide the link and count down until a resend is allowed again. An optional
	// first-tick message lets us confirm a just-sent code before the plain
	// countdown takes over.
	const startCountdown = (seconds, firstMessage) => {
		clearTimer()
		link.hidden = true
		let remaining = Math.max(0, Math.floor(seconds))
		let message = firstMessage
		const render = () => {
			if (remaining <= 0) {
				offerResend()
				return
			}
			status.textContent = message
				|| t('twofactor_email', 'You can request a new code in {seconds} s', { seconds: remaining })
			message = null
			remaining -= 1
		}
		render()
		timer = window.setInterval(render, 1000)
	}

	link.addEventListener('click', async (event) => {
		event.preventDefault()
		clearTimer()
		link.hidden = true
		try {
			await Axios.post(generateUrl('/apps/twofactor_email/challenge/resend'))
			const input = document.querySelector('.twofactor_email-challenge-form input[name="challenge"]')
			if (input) {
				input.value = ''
				input.focus()
			}
			startCountdown(cooldown, t('twofactor_email', 'A new code was sent. You can request another in {seconds} s', { seconds: cooldown }))
		} catch (error) {
			const data = error.response && error.response.data
			if (error.response && error.response.status === 429) {
				// Cooldown not elapsed; retryAfter comes from our controller.
				startCountdown((data && data.retryAfter) || cooldown)
			} else if (data && data.error === 'no-email') {
				status.textContent = t('twofactor_email', 'No email address is configured for your account.')
			} else {
				Logger.error('failed to resend two-factor email code', error)
				status.textContent = t('twofactor_email', 'The code could not be sent. Please try again later.')
				link.hidden = false
			}
		}
	})

	// Initial state from the server-rendered cooldown.
	const availableIn = Number(line.dataset.availableIn) || 0
	if (availableIn > 0) {
		startCountdown(availableIn)
	} else {
		offerResend()
	}
})

/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { n, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import Logger from './Logger.js'

import './LoginChallenge.css'

// Resend control on the 2FA challenge page (server-rendered template, so plain
// DOM, not Vue). It shows a clickable "Send a new code" link only while a resend
// is actually possible; during the cooldown it shows a countdown instead, so the
// user never clicks and fails. The remaining time is tracked in seconds (for an
// accurate "link is back" moment) but displayed coarsely in minutes.
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

	const remainingText = (seconds) => {
		if (seconds > 60) {
			const minutes = Math.ceil(seconds / 60)
			return n('twofactor_email', 'You can request a new code in %n minute.', 'You can request a new code in %n minutes.', minutes)
		}
		// Disabling sanitize keeps the literal "<". Sanitize would turn it into "&lt;".
		// Safe only because the result is assigned via textContent below, which never
		// parses HTML. Do not reuse this string with innerHTML.
		return t('twofactor_email', 'You can request a new code in <1 minute.', {}, { sanitize: false })
	}

	// Nextcloud keeps transient confirmations on screen for seven seconds
	// (TOAST_DEFAULT_TIMEOUT in @nextcloud/dialogs). The countdown must not push the
	// confirmation away before that: it used to replace it on the very next tick, so
	// "a new code was sent" was readable for exactly one second.
	const CONFIRMATION_DWELL_SECONDS = 7

	const setStatus = (text) => {
		status.textContent = text
	}

	// Only for the countdown: it re-renders every second while its text changes once a
	// minute, so writing the same string again would touch the aria-live region for
	// nothing. One-shot messages must NOT go through this — a failure repeated after a
	// second click has identical text, and skipping the write would leave the user
	// without any sign that the click did something.
	const setCountdownStatus = (text) => {
		if (status.textContent !== text) {
			status.textContent = text
		}
	}

	const offerResend = () => {
		clearTimer()
		setStatus('')
		link.hidden = false
	}

	// Hide the link and count down (second-accurate) until a resend is allowed
	// again. An optional first message confirms a just-sent code.
	const startCountdown = (seconds, firstMessage) => {
		clearTimer()
		link.hidden = true
		// Both counters tick with the interval, not with the clock. A tab that is hidden
		// long enough for the browser to throttle timers, or a suspended machine, therefore
		// stretches the dwell and the countdown past their nominal seconds — the link then
		// stays hidden after the server-side cooldown has passed, and reloading is the way
		// out. Deriving both from a Date.now() deadline would fix that; it is a separate
		// change from this one.
		let remaining = Math.max(0, Math.floor(seconds))
		let dwell = firstMessage ? CONFIRMATION_DWELL_SECONDS : 0
		const render = () => {
			if (remaining <= 0) {
				offerResend()
				return
			}
			if (dwell > 0) {
				setCountdownStatus(firstMessage)
				dwell -= 1
			} else {
				setCountdownStatus(remainingText(remaining))
			}
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
			startCountdown(cooldown, t('twofactor_email', 'A new code was sent. Only the new code is valid now.'))
		} catch (error) {
			/** @type {{ error?: string, retryAfter?: number } | undefined} */
			const data = error.response && error.response.data
			if (error.response && error.response.status === 429) {
				// The cooldown has not elapsed. retryAfter (seconds) comes from our controller.
				startCountdown((data && data.retryAfter) || cooldown)
			} else if (data && data.error === 'no-email') {
				setStatus(t('twofactor_email', 'No email address available, please contact your administrator.'))
			} else {
				Logger.error('failed to resend two-factor email code', error)
				setStatus(t('twofactor_email', 'The code could not be sent. Please try again later.'))
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

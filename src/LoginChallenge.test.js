// @vitest-environment happy-dom
/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import './LoginChallenge.js'

vi.mock('./LoginChallenge.css', () => ({}))
vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('@nextcloud/l10n', () => ({
	t: (app, text) => text,
	n: (app, singular, plural, count) => plural.replace('%n', String(count)),
}))
vi.mock('./Logger.js', () => ({ default: { error: vi.fn() } }))

function render({ cooldown = 60, availableIn = 0 } = {}) {
	document.body.innerHTML = `
		<div class="twofactor_email-resend-line" data-cooldown="${cooldown}" data-available-in="${availableIn}">
			<a class="twofactor_email-resend" href="#">Send a new code</a>
			<span class="twofactor_email-resend-status"></span>
		</div>
		<form class="twofactor_email-challenge-form">
			<input name="challenge" value="123456">
		</form>
	`
	document.dispatchEvent(new Event('DOMContentLoaded'))
	return {
		link: document.querySelector('.twofactor_email-resend'),
		status: document.querySelector('.twofactor_email-resend-status'),
		input: document.querySelector('input[name="challenge"]'),
	}
}

beforeEach(() => {
	vi.useFakeTimers()
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('LoginChallenge resend', () => {
	it('offers the resend link when no cooldown is pending', () => {
		const { link } = render({ availableIn: 0 })

		expect(link.hidden).toBe(false)
	})

	it('hides the link and shows a countdown when a cooldown is pending', () => {
		const { link, status } = render({ availableIn: 30 })

		expect(link.hidden).toBe(true)
		expect(status.textContent).toContain('1 minute')
	})

	it('sends a new code, clears the input and starts the cooldown on click', async () => {
		Axios.post.mockResolvedValue({})
		const { link, status, input } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(Axios.post).toHaveBeenCalledWith('/apps/twofactor_email/challenge/resend')
		expect(input.value).toBe('')
		expect(link.hidden).toBe(true)
		expect(status.textContent).toContain('new code was sent')
	})

	it('shows a countdown when the server reports the cooldown (429)', async () => {
		Axios.post.mockRejectedValue({ response: { status: 429, data: { retryAfter: 30 } } })
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(link.hidden).toBe(true)
		expect(status.textContent).toContain('1 minute')
	})

	it('reports a missing email address', async () => {
		Axios.post.mockRejectedValue({ response: { status: 400, data: { error: 'no-email' } } })
		const { status } = render({ availableIn: 0 })

		document.querySelector('.twofactor_email-resend').click()
		await vi.advanceTimersByTimeAsync(0)

		expect(status.textContent).toContain('contact your administrator')
	})

	it('reports a generic failure and offers the link again', async () => {
		Axios.post.mockRejectedValue(new Error('boom'))
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(status.textContent).toContain('could not be sent')
		expect(link.hidden).toBe(false)
	})
})

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
		<p class="twofactor_email-code-age-hint">No new code was sent, because an earlier one is still valid.</p>
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

	it('removes the line about the earlier code once a new one was sent', async () => {
		Axios.post.mockResolvedValue({})
		const { link } = render({ availableIn: 0 })
		expect(document.querySelector('.twofactor_email-code-age-hint')).not.toBeNull()

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(document.querySelector('.twofactor_email-code-age-hint')).toBeNull()
	})

	it('keeps the line about the earlier code when the send failed', async () => {
		Axios.post.mockRejectedValue({ response: { status: 500, data: {} } })
		const { link } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(document.querySelector('.twofactor_email-code-age-hint')).not.toBeNull()
	})

	it('keeps the confirmation readable before the countdown takes over', async () => {
		Axios.post.mockResolvedValue({})
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)
		expect(status.textContent).toContain('new code was sent')

		// Six seconds in it must still be there — one second was the old behaviour.
		await vi.advanceTimersByTimeAsync(6000)
		expect(status.textContent).toContain('new code was sent')

		// After the dwell of seven seconds the countdown takes the line over.
		await vi.advanceTimersByTimeAsync(2000)
		expect(status.textContent).toContain('1 minute')
	})

	it('leaves the live region untouched while the countdown text is unchanged', async () => {
		const { status } = render({ availableIn: 120 })
		const changes = []
		const observer = new MutationObserver(() => changes.push(status.textContent))
		observer.observe(status, { characterData: true, childList: true, subtree: true })

		await vi.advanceTimersByTimeAsync(5000)
		observer.disconnect()

		expect(changes).toEqual([])
	})

	it('shows a countdown when the server reports the cooldown (429)', async () => {
		Axios.post.mockRejectedValue({ response: { status: 429, data: { retryAfter: 30 } } })
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(link.hidden).toBe(true)
		expect(status.textContent).toContain('1 minute')
	})

	it('reports a missing email address and keeps the link hidden', async () => {
		Axios.post.mockRejectedValue({ response: { status: 400, data: { error: 'no-email' } } })
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)

		expect(status.textContent).toContain('contact your administrator')
		// Deliberate, and the one error path that does not offer the link again: until
		// an administrator sets an address, another attempt fails the same way.
		expect(link.hidden).toBe(true)
	})

	it('shows a repeated failure again, so a second click is visibly answered', async () => {
		Axios.post.mockRejectedValue(new Error('boom'))
		const { link, status } = render({ availableIn: 0 })

		link.click()
		await vi.advanceTimersByTimeAsync(0)
		expect(status.textContent).toContain('could not be sent')

		// The second click produces the same text. It must still be written, otherwise
		// nothing changes in the DOM: no visual refresh and no re-announcement in the
		// live region, so the user cannot tell the click did anything.
		const changes = []
		const observer = new MutationObserver(() => changes.push(status.textContent))
		observer.observe(status, { characterData: true, childList: true, subtree: true })
		link.click()
		await vi.advanceTimersByTimeAsync(0)
		observer.disconnect()

		expect(changes.length).toBeGreaterThan(0)
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

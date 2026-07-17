/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { persistAdminSettings, persistState, resetAdminSettings } from './StateManager.js'

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))
vi.mock('@nextcloud/axios', () => ({ default: { post: vi.fn() } }))
vi.mock('../Logger.js', () => ({ default: { debug: vi.fn(), error: vi.fn() } }))

beforeEach(() => {
	vi.clearAllMocks()
})

describe('persistAdminSettings', () => {
	it('returns the saved settings on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(persistAdminSettings({})).resolves.toEqual({ codeLength: 6 })
	})

	it('maps a 400 field-to-code map to { errors }', async () => {
		Axios.post.mockRejectedValue({ response: { data: { errors: { codeLength: 'code-length-out-of-range' } } } })

		await expect(persistAdminSettings({})).resolves.toEqual({ errors: { codeLength: 'code-length-out-of-range' } })
	})

	it('ignores an array errors payload and reports save-failed', async () => {
		Axios.post.mockRejectedValue({ response: { data: { errors: ['code-length-out-of-range'] } } })

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})

	it('reports save-failed on a network error', async () => {
		Axios.post.mockRejectedValue(new Error('network down'))

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})
})

describe('persistState', () => {
	it('returns the backend state on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { enabled: true } })

		await expect(persistState(true)).resolves.toEqual({ enabled: true })
	})

	it('surfaces a specific backend error', async () => {
		Axios.post.mockRejectedValue({ response: { data: { error: 'no-email' } } })

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: 'no-email' })
	})

	it('reports save-failed on a network error', async () => {
		Axios.post.mockRejectedValue(new Error('network down'))

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: 'save-failed' })
	})
})

describe('resetAdminSettings', () => {
	it('returns the defaults on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(resetAdminSettings()).resolves.toEqual({ codeLength: 6 })
	})

	it('reports reset-failed on error', async () => {
		Axios.post.mockRejectedValue(new Error('boom'))

		await expect(resetAdminSettings()).resolves.toEqual({ error: 'reset-failed' })
	})
})

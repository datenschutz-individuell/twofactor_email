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

const garbageBodies = [
	{ case: 'null', body: null },
	{ case: 'a string', body: 'unexpected' },
	{ case: 'an unexpected object', body: { totallyWrong: true } },
]

beforeEach(() => {
	vi.clearAllMocks()
})

describe('persistAdminSettings', () => {
	it('returns the saved settings on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(persistAdminSettings({})).resolves.toEqual({ codeLength: 6 })
	})

	it.each(garbageBodies)('returns a 200 body verbatim ($case)', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(persistAdminSettings({})).resolves.toEqual(body)
	})

	it('maps a 400 field-to-code map to { errors }', async () => {
		Axios.post.mockRejectedValue({ response: { data: { errors: { codeLength: 'code-length-out-of-range' } } } })

		await expect(persistAdminSettings({})).resolves.toEqual({ errors: { codeLength: 'code-length-out-of-range' } })
	})

	it.each([
		{ case: 'an array errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: { errors: ['code-length-out-of-range'] } } }) },
		{ case: 'a string errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: { errors: 'code-length-out-of-range' } } }) },
		{ case: 'a numeric errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: { errors: 42 } } }) },
		{ case: 'a boolean errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: { errors: true } } }) },
		{ case: 'a null errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: { errors: null } } }) },
		{ case: 'a missing errors payload', arm: () => Axios.post.mockRejectedValue({ response: { data: {} } }) },
		{ case: 'a non-200 response', arm: () => Axios.post.mockResolvedValue({ status: 500, data: {} }) },
		{ case: 'a network error without a response', arm: () => Axios.post.mockRejectedValue(new Error('network down')) },
	])('reports save-failed for $case', async ({ arm }) => {
		arm()

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})
})

describe('persistState', () => {
	it('returns the backend state on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { enabled: true } })

		await expect(persistState(true)).resolves.toEqual({ enabled: true })
	})

	it.each(garbageBodies)('returns a 200 body verbatim ($case)', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(persistState(true)).resolves.toEqual(body)
	})

	it.each([
		{ case: 'a known code', error: 'no-email' },
		{ case: 'a garbage value', error: { weird: 1 } },
	])('surfaces the backend error verbatim ($case)', async ({ error }) => {
		Axios.post.mockRejectedValue({ response: { data: { error } } })

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error })
	})

	it.each([
		{ case: 'a response without a data body', rejection: { response: {} } },
		{ case: 'a data body without an error', rejection: { response: { data: {} } } },
		{ case: 'an empty error string', rejection: { response: { data: { error: '' } } } },
		{ case: 'a network error without a response', rejection: new Error('network down') },
	])('reports save-failed for $case', async ({ rejection }) => {
		Axios.post.mockRejectedValue(rejection)

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: 'save-failed' })
	})
})

describe('resetAdminSettings', () => {
	it('returns the defaults on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(resetAdminSettings()).resolves.toEqual({ codeLength: 6 })
	})

	it.each(garbageBodies)('returns a 200 body verbatim ($case)', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(resetAdminSettings()).resolves.toEqual(body)
	})

	it.each([
		{ case: 'a rejected request', arm: () => Axios.post.mockRejectedValue(new Error('boom')) },
		{ case: 'a garbage 4xx payload', arm: () => Axios.post.mockRejectedValue({ response: { status: 418, data: 'nonsense' } }) },
		{ case: 'a non-200 response', arm: () => Axios.post.mockResolvedValue({ status: 500, data: {} }) },
	])('reports reset-failed for $case', async ({ arm }) => {
		arm()

		await expect(resetAdminSettings()).resolves.toEqual({ error: 'reset-failed' })
	})
})

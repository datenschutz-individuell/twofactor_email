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

// 2xx responses whose body is not a usable result object; the store would
// crash or misbehave on these, so they must collapse to the failure shape.
const malformedBodies = [
	{ case: 'a null body', body: null },
	{ case: 'an empty body', body: '' },
	{ case: 'an array body', body: [] },
	{ case: 'a numeric body', body: 42 },
	{ case: 'a boolean body', body: true },
]

beforeEach(() => {
	vi.clearAllMocks()
})

describe('persistAdminSettings', () => {
	it('returns the saved settings on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(persistAdminSettings({})).resolves.toEqual({ codeLength: 6 })
	})

	it('passes an unexpected object body through unchanged', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { unexpected: true } })

		await expect(persistAdminSettings({})).resolves.toEqual({ unexpected: true })
	})

	it('maps a 400 field-to-code map to { errors }', async () => {
		Axios.post.mockRejectedValue({ response: { data: { errors: { codeLength: 'code-length-out-of-range' } } } })

		await expect(persistAdminSettings({})).resolves.toEqual({ errors: { codeLength: 'code-length-out-of-range' } })
	})

	it.each([
		{ case: 'an array errors payload', rejection: { response: { data: { errors: ['code-length-out-of-range'] } } } },
		{ case: 'a string errors payload', rejection: { response: { data: { errors: 'code-length-out-of-range' } } } },
		{ case: 'a numeric errors payload', rejection: { response: { data: { errors: 42 } } } },
		{ case: 'a boolean errors payload', rejection: { response: { data: { errors: true } } } },
		{ case: 'a null errors payload', rejection: { response: { data: { errors: null } } } },
		{ case: 'a missing errors payload', rejection: { response: { data: {} } } },
		{ case: 'a network error without a response', rejection: new Error('network down') },
	])('reports save-failed for $case', async ({ rejection }) => {
		Axios.post.mockRejectedValue(rejection)

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})

	it.each(malformedBodies)('reports save-failed for $case on a 200', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})

	it('reports save-failed on a non-200 response even with a valid body', async () => {
		Axios.post.mockResolvedValue({ status: 201, data: { codeLength: 6 } })

		await expect(persistAdminSettings({})).resolves.toEqual({ error: 'save-failed' })
	})
})

describe('persistState', () => {
	it('returns the backend state on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { enabled: true } })

		await expect(persistState(true)).resolves.toEqual({ enabled: true })
	})

	it('passes an unexpected object body through unchanged', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { unexpected: true } })

		await expect(persistState(true)).resolves.toEqual({ unexpected: true })
	})

	it('surfaces a specific backend error', async () => {
		Axios.post.mockRejectedValue({ response: { data: { error: 'no-email' } } })

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: 'no-email' })
	})

	it('surfaces a garbage backend error verbatim', async () => {
		Axios.post.mockRejectedValue({ response: { data: { error: { weird: 1 } } } })

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: { weird: 1 } })
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

	it.each(malformedBodies)('reports save-failed for $case on a 200', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(persistState(true)).resolves.toEqual({ enabled: false, error: 'save-failed' })
	})
})

describe('resetAdminSettings', () => {
	it('returns the defaults on success', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { codeLength: 6 } })

		await expect(resetAdminSettings()).resolves.toEqual({ codeLength: 6 })
	})

	it('passes an unexpected object body through unchanged', async () => {
		Axios.post.mockResolvedValue({ status: 200, data: { unexpected: true } })

		await expect(resetAdminSettings()).resolves.toEqual({ unexpected: true })
	})

	it.each([
		{ case: 'a rejected request', rejection: new Error('boom') },
		{ case: 'a garbage 4xx payload', rejection: { response: { status: 418, data: 'nonsense' } } },
	])('reports reset-failed for $case', async ({ rejection }) => {
		Axios.post.mockRejectedValue(rejection)

		await expect(resetAdminSettings()).resolves.toEqual({ error: 'reset-failed' })
	})

	it.each(malformedBodies)('reports reset-failed for $case on a 200', async ({ body }) => {
		Axios.post.mockResolvedValue({ status: 200, data: body })

		await expect(resetAdminSettings()).resolves.toEqual({ error: 'reset-failed' })
	})

	it('reports reset-failed on a non-200 response even with a valid body', async () => {
		Axios.post.mockResolvedValue({ status: 201, data: { codeLength: 6 } })

		await expect(resetAdminSettings()).resolves.toEqual({ error: 'reset-failed' })
	})
})

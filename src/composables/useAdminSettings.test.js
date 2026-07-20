/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, reactive } from 'vue'
import { useAdminSettings } from './useAdminSettings.js'

vi.mock('@nextcloud/initial-state', () => ({ loadState: () => ({}) }))
vi.mock('@nextcloud/l10n', () => ({ t: (app, text) => text }))
vi.mock('../Logger.js', () => ({ default: { error: vi.fn(), warn: vi.fn() } }))

const FIELDS = ['codeLength', 'codeValidMinutes', 'codeResendMinutes', 'eMailSubject', 'eMailTemplate']

function makeStore() {
	const store = reactive({
		codeLength: 6,
		codeValidMinutes: 10,
		codeResendMinutes: 30,
		eMailSubject: 'Subject',
		eMailTemplate: 'Body {code}',
	})
	store.$patch = (patch) => Object.assign(store, patch)
	store.save = vi.fn()
	return store
}

/**
 * Starts the composable, waits for its deferred watchers to register, edits one
 * field and drives the debounced save to completion.
 */
async function editAndSave(store, key, value) {
	const composable = useAdminSettings(store, FIELDS)
	await nextTick()
	await nextTick()
	composable.inputValues[key] = value
	await nextTick()
	await vi.advanceTimersByTimeAsync(1500)
	return composable
}

beforeEach(() => {
	vi.useFakeTimers()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('useAdminSettings', () => {
	it('flags only the field the backend rejected', async () => {
		const store = makeStore()
		store.save.mockResolvedValue({ errors: { codeLength: 'code-length-out-of-range' } })

		const { successRefs, errorMessages } = await editAndSave(store, 'codeLength', 2)

		expect(successRefs.codeLength).toBe(false)
		expect(errorMessages.codeLength).toContain('code length')
		expect(successRefs.codeValidMinutes).toBeNull()
		expect(errorMessages.codeValidMinutes).toBe('')
	})

	it('marks every field as saved on success, then clears it', async () => {
		const store = makeStore()
		store.save.mockResolvedValue({ codeLength: 8 })

		const { successRefs } = await editAndSave(store, 'codeLength', 8)

		expect(successRefs.codeLength).toBe(true)
		expect(successRefs.eMailSubject).toBe(true)

		await vi.advanceTimersByTimeAsync(1200)
		expect(successRefs.codeLength).toBeNull()
	})

	it('flags all fields without a message on an unexpected failure', async () => {
		const store = makeStore()
		store.save.mockResolvedValue({ error: 'save-failed' })

		const { successRefs, errorMessages } = await editAndSave(store, 'codeLength', 8)

		expect(successRefs.codeLength).toBe(false)
		expect(successRefs.eMailSubject).toBe(false)
		expect(errorMessages.codeLength).toBe('')
	})

	it('coalesces rapid edits into a single save', async () => {
		const store = makeStore()
		store.save.mockResolvedValue({ codeLength: 9 })
		const { inputValues } = useAdminSettings(store, FIELDS)
		await nextTick()
		await nextTick()

		inputValues.codeLength = 7
		await nextTick()
		await vi.advanceTimersByTimeAsync(500)
		inputValues.codeLength = 9
		await nextTick()
		await vi.advanceTimersByTimeAsync(1500)

		expect(store.save).toHaveBeenCalledTimes(1)
	})
})

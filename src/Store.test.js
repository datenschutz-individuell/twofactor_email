/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { persistAdminSettings, persistState, resetAdminSettings } from './services/StateManager.js'
import { useAdminSettingsStore, usePersonalSettingsStore } from './Store.js'

vi.mock('@nextcloud/initial-state', () => ({ loadState: vi.fn() }))
vi.mock('./services/StateManager.js', () => ({
	persistState: vi.fn(),
	persistAdminSettings: vi.fn(),
	resetAdminSettings: vi.fn(),
}))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('usePersonalSettingsStore.save', () => {
	it('keeps the new state on success', async () => {
		persistState.mockResolvedValue({ enabled: true })
		const store = usePersonalSettingsStore()
		store.enabled = true

		await store.save()

		expect(store.enabled).toBe(true)
		expect(store.error).toBeFalsy()
	})

	it('reverts the switch and keeps the error on failure', async () => {
		persistState.mockResolvedValue({ enabled: false, error: 'no-email' })
		const store = usePersonalSettingsStore()
		store.enabled = true

		await store.save()

		expect(store.enabled).toBe(false)
		expect(store.error).toBe('no-email')
	})
})

describe('useAdminSettingsStore.save', () => {
	it('maps the saved settings into the state', async () => {
		const saved = { codeLength: 8, codeValidMinutes: 10, codeResendMinutes: 30, eMailSubject: 'S', eMailTemplate: 'T' }
		persistAdminSettings.mockResolvedValue(saved)
		const store = useAdminSettingsStore()

		const result = await store.save()

		expect(store.codeLength).toBe(8)
		expect(result).toEqual(saved)
	})

	it('keeps the current values and returns the errors on a validation error', async () => {
		persistAdminSettings.mockResolvedValue({ errors: { codeLength: 'code-length-out-of-range' } })
		const store = useAdminSettingsStore()
		store.codeLength = 6

		const result = await store.save()

		expect(store.codeLength).toBe(6)
		expect(result.errors).toEqual({ codeLength: 'code-length-out-of-range' })
	})
})

describe('useAdminSettingsStore.reset', () => {
	it('applies the defaults on success', async () => {
		resetAdminSettings.mockResolvedValue({ codeLength: 6, codeValidMinutes: 10, codeResendMinutes: 30, eMailSubject: '', eMailTemplate: '' })
		const store = useAdminSettingsStore()

		await store.reset()

		expect(store.codeLength).toBe(6)
	})

	it('does not touch the state on error', async () => {
		resetAdminSettings.mockResolvedValue({ error: 'reset-failed' })
		const store = useAdminSettingsStore()
		store.codeLength = 8

		const result = await store.reset()

		expect(store.codeLength).toBe(8)
		expect(result.error).toBe('reset-failed')
	})
})

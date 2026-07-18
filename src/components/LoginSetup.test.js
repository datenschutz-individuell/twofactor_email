// @vitest-environment happy-dom
/*
 * SPDX-FileCopyrightText: 2025 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'

vi.mock('@nextcloud/l10n', () => ({ t: (app, text) => text }))
vi.mock('../Logger.js', () => ({ default: { error: vi.fn(), debug: vi.fn(), warn: vi.fn() } }))

let store
vi.mock('../Store.js', () => ({ usePersonalSettingsStore: () => store }))

const Logger = (await import('../Logger.js')).default
const { default: LoginSetup } = await import('./LoginSetup.vue')

beforeEach(() => {
	store = reactive({
		maskedEmail: 'u***@example.com',
		error: false,
		loadInitialState: vi.fn(),
		enable: vi.fn().mockResolvedValue(),
		$patch(patch) { Object.assign(this, patch) },
	})
})

afterEach(() => vi.clearAllMocks())

describe('LoginSetup', () => {
	it('enables 2FA on mount and shows the success view with the masked email', async () => {
		const wrapper = mount(LoginSetup)
		await flushPromises()

		expect(store.enable).toHaveBeenCalledOnce()
		expect(wrapper.text()).toContain('Two-factor authentication via email was enabled.')
		expect(wrapper.text()).toContain('u***@example.com')
		expect(wrapper.find('button').text()).toBe('Proceed')
	})

	it('shows a spinner while enabling is still in flight', async () => {
		let release
		store.enable.mockReturnValue(new Promise((resolve) => {
			release = resolve
		}))
		const wrapper = mount(LoginSetup)
		await flushPromises()

		expect(wrapper.find('.loading').exists()).toBe(true)
		release()
		await flushPromises()
		expect(wrapper.find('.loading').exists()).toBe(false)
	})

	it.each([
		['no-email', 'No email address available, please contact your administrator.'],
		['save-failed', 'Could not enable/disable two-factor authentication via email.'],
		[true, 'Unhandled error!'],
	])('renders the matching message for error %s', async (error, message) => {
		store.error = error
		const wrapper = mount(LoginSetup)
		await flushPromises()

		expect(wrapper.find('.error').text()).toBe(message)
	})

	it('flags save-failed when enabling throws', async () => {
		store.enable.mockRejectedValue(new Error('boom'))
		const wrapper = mount(LoginSetup)
		await flushPromises()

		expect(store.error).toBe('save-failed')
		expect(Logger.error).toHaveBeenCalled()
		expect(wrapper.find('.error').text()).toBe('Could not enable/disable two-factor authentication via email.')
	})
})

// @vitest-environment happy-dom
/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'

// t() returns the raw source string so we can assert on the English text.
vi.mock('@nextcloud/l10n', () => ({ t: (app, text) => text }))
vi.mock('@nextcloud/password-confirmation', () => ({ confirmPassword: vi.fn() }))
vi.mock('@nextcloud/password-confirmation/style.css', () => ({}))
vi.mock('../Logger.js', () => ({ default: { error: vi.fn(), debug: vi.fn(), warn: vi.fn() } }))

// The switch is stubbed to a single-root element that re-emits update:modelValue,
// which is the only interaction the component reacts to.
vi.mock('@nextcloud/vue/components/NcCheckboxRadioSwitch', () => ({
	default: { name: 'NcSwitch', props: ['modelValue', 'loading', 'type'], emits: ['update:modelValue'], template: '<button @click="$emit(\'update:modelValue\', !modelValue)"><slot /></button>' },
}))

// A reactive stand-in for the pinia store, injected via the module mock below.
let store
vi.mock('../Store.js', () => ({ usePersonalSettingsStore: () => store }))

const { confirmPassword } = await import('@nextcloud/password-confirmation')
const Logger = (await import('../Logger.js')).default
const { default: PersonalSettings } = await import('./PersonalSettings.vue')

beforeEach(() => {
	store = reactive({
		enabled: false,
		hasEmail: true,
		email: 'user@example.com',
		error: false,
		loadInitialState: vi.fn(),
		save: vi.fn(),
		$patch(patch) { Object.assign(this, patch) },
	})
	confirmPassword.mockReset().mockResolvedValue()
})

afterEach(() => vi.clearAllMocks())

const ncSwitch = (wrapper) => wrapper.findComponent({ name: 'NcSwitch' })

// Simulates a user flipping the switch: the v-model write happens first (as in
// the real component), then the @update:modelValue handler (onUpdate) runs.
async function toggle(wrapper, value = true) {
	ncSwitch(wrapper).vm.$emit('update:modelValue', value)
	await flushPromises()
}

describe('PersonalSettings', () => {
	it('shows the switch when an email exists, the notice otherwise', () => {
		expect(ncSwitch(mount(PersonalSettings)).exists()).toBe(true)

		store.hasEmail = false
		const wrapper = mount(PersonalSettings)
		expect(ncSwitch(wrapper).exists()).toBe(false)
		expect(wrapper.find('.notice').exists()).toBe(true)
	})

	it('saves and keeps the new state when confirmation and save succeed', async () => {
		const wrapper = mount(PersonalSettings)
		await toggle(wrapper, true)

		expect(confirmPassword).toHaveBeenCalledOnce()
		expect(store.save).toHaveBeenCalledOnce()
		expect(store.enabled).toBe(true)
		// onUpdate clears any previous error at the start; a clean save leaves it cleared
		expect(store.error).toBeNull()
	})

	it('reverts the toggle and flags a password error when confirmation fails', async () => {
		confirmPassword.mockRejectedValue(new Error('aborted'))
		const wrapper = mount(PersonalSettings)
		await toggle(wrapper, true)

		expect(store.save).not.toHaveBeenCalled()
		expect(store.enabled).toBe(false)
		expect(store.error).toBe('password-confirmation-failed')
		expect(Logger.error).toHaveBeenCalled()
	})

	it('reverts the toggle when the backend reports an error', async () => {
		store.save.mockImplementation(() => {
			store.error = 'backend-said-no'
		})
		const wrapper = mount(PersonalSettings)
		await toggle(wrapper, true)

		expect(store.enabled).toBe(false)
	})

	it('reverts the toggle and flags save-failed when save throws', async () => {
		store.save.mockRejectedValue(new Error('network'))
		const wrapper = mount(PersonalSettings)
		await toggle(wrapper, true)

		expect(store.enabled).toBe(false)
		expect(store.error).toBe('save-failed')
		expect(Logger.error).toHaveBeenCalled()
	})

	it('ignores a second toggle while the first is still in flight', async () => {
		let release
		confirmPassword.mockReturnValue(new Promise((resolve) => {
			release = resolve
		}))
		const wrapper = mount(PersonalSettings)

		ncSwitch(wrapper).vm.$emit('update:modelValue', true) // starts, awaits confirmation
		ncSwitch(wrapper).vm.$emit('update:modelValue', false) // should be ignored (loading)
		release()
		await flushPromises()

		expect(Logger.debug).toHaveBeenCalled()
		expect(store.save).toHaveBeenCalledOnce()
	})
})

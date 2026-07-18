// @vitest-environment happy-dom
/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive, ref } from 'vue'

vi.mock('@mdi/js', () => ({ mdiUndo: 'M0 0' }))
vi.mock('@nextcloud/initial-state', () => ({ loadState: () => '' }))
vi.mock('@nextcloud/l10n', () => ({ t: (app, text) => text }))
vi.mock('../Logger.js', () => ({ default: { error: vi.fn(), debug: vi.fn(), warn: vi.fn() } }))

// Stub the Nc chrome and the child field: none of them carry logic this test
// cares about, and stubbing keeps @nextcloud/vue out of the render.
vi.mock('@nextcloud/vue/components/NcSettingsSection', () => ({ default: { name: 'NcSettingsSection', template: '<div><slot /></div>' } }))
vi.mock('@nextcloud/vue/components/NcButton', () => ({ default: { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' } }))
vi.mock('@nextcloud/vue/components/NcIconSvgWrapper', () => ({ default: { name: 'NcIconSvgWrapper', template: '<span />' } }))
vi.mock('./LabeledField.vue', () => ({ default: { name: 'LabeledField', props: ['id', 'modelValue'], template: '<div class="labeled-field-stub" />' } }))

const fieldKeys = ['codeLength', 'codeValidMinutes', 'codeResendMinutes', 'eMailSubject', 'eMailTemplate']

// The composable is exercised in its own test; here we hand the component a
// controllable inputValues object so we can watch onReset write into it.
let inputValues
vi.mock('../composables/useAdminSettings.js', () => ({
	useAdminSettings: () => ({ inputValues, loading: ref(false), successRefs: reactive({}), errorMessages: reactive({}) }),
}))

let store
vi.mock('../Store.js', () => ({ useAdminSettingsStore: () => store }))

const Logger = (await import('../Logger.js')).default
const { default: AdminSettings } = await import('./AdminSettings.vue')

beforeEach(() => {
	// The store holds the freshly reset (default) values; inputValues starts stale.
	store = reactive({
		codeLength: 6,
		codeValidMinutes: 10,
		codeResendMinutes: 1,
		eMailSubject: 'S',
		eMailTemplate: 'T',
		error: false,
		loadInitialState: vi.fn(),
		reset: vi.fn().mockResolvedValue({}),
	})
	inputValues = reactive({ codeLength: 99, codeValidMinutes: 99, codeResendMinutes: 99, eMailSubject: 'x', eMailTemplate: 'x' })
})

afterEach(() => vi.clearAllMocks())

async function clickReset(wrapper) {
	await wrapper.find('button').trigger('click')
	await flushPromises()
}

describe('AdminSettings', () => {
	it('renders one field per setting', () => {
		expect(mount(AdminSettings).findAllComponents({ name: 'LabeledField' })).toHaveLength(fieldKeys.length)
	})

	it('copies the reset defaults back into the inputs on a successful reset', async () => {
		const wrapper = mount(AdminSettings)
		await clickReset(wrapper)

		expect(store.reset).toHaveBeenCalledOnce()
		for (const key of fieldKeys) {
			expect(inputValues[key]).toBe(store[key])
		}
	})

	it('leaves the inputs untouched when reset returns a validation error', async () => {
		store.reset.mockResolvedValue({ error: 'Length must be a number' })
		const before = { ...inputValues }
		const wrapper = mount(AdminSettings)
		await clickReset(wrapper)

		expect(inputValues).toEqual(before)
	})

	it('logs and recovers when reset throws', async () => {
		store.reset.mockRejectedValue(new Error('network'))
		const wrapper = mount(AdminSettings)
		await clickReset(wrapper)

		expect(Logger.error).toHaveBeenCalled()
		// The button is re-enabled (resetting flag cleared in finally)
		expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
	})
})

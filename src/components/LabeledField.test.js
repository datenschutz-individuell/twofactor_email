// @vitest-environment happy-dom
/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

// Minimal stand-ins for the Nc inputs. Each renders a single root element so
// the parent's fall-through listeners (notably @keydown) land on a real DOM
// node, and each declares the props we assert on so we can read what the
// component passed down.
const fieldProps = ['id', 'modelValue', 'error', 'helperText', 'labelOutside', 'loading', 'placeholder', 'success', 'min', 'type']
vi.mock('@nextcloud/vue/components/NcTextField', () => ({
	default: { name: 'NcTextField', props: fieldProps, emits: ['update:modelValue'], template: '<input :id="id" />' },
}))
vi.mock('@nextcloud/vue/components/NcTextArea', () => ({
	default: { name: 'NcTextArea', props: fieldProps, emits: ['update:modelValue'], template: '<textarea :id="id" />' },
}))

const { default: LabeledField } = await import('./LabeledField.vue')

/**
 * Mounts LabeledField with sensible defaults, overridable per test.
 *
 * @param {object} props - Props to override the defaults with
 * @return {import('@vue/test-utils').VueWrapper} The mounted wrapper
 */
function mountField(props = {}) {
	return mount(LabeledField, { props: { id: 'field-id', label: 'The label', ...props } })
}

const field = (wrapper) => wrapper.findComponent({ name: 'NcTextField' })
const area = (wrapper) => wrapper.findComponent({ name: 'NcTextArea' })

describe('LabeledField', () => {
	it('renders the label bound to the input id', () => {
		const wrapper = mountField()
		const label = wrapper.find('label')
		expect(label.attributes('for')).toBe('field-id')
		expect(label.text()).toBe('The label')
	})

	it('renders a text field by default and a textarea when asked', () => {
		expect(field(mountField()).exists()).toBe(true)
		expect(area(mountField()).exists()).toBe(false)

		const textarea = mountField({ type: 'textarea' })
		expect(field(textarea).exists()).toBe(false)
		expect(area(textarea).exists()).toBe(true)
	})

	it('shows the error message instead of the helper text only while flagged', () => {
		const base = { helperText: 'the help', errorMessage: 'the error' }
		// result === false (flagged) with an error message -> show the error
		expect(field(mountField({ ...base, result: false })).props('helperText')).toBe('the error')
		// no error message -> keep the helper text even while flagged
		expect(field(mountField({ ...base, errorMessage: '', result: false })).props('helperText')).toBe('the help')
		// success and idle both keep the helper text
		expect(field(mountField({ ...base, result: true })).props('helperText')).toBe('the help')
		expect(field(mountField({ ...base, result: null })).props('helperText')).toBe('the help')
	})

	it('maps the tri-state result onto the error/success props', () => {
		const flagged = field(mountField({ result: false }))
		expect(flagged.props('error')).toBe(true)
		expect(flagged.props('success')).toBe(false)

		const ok = field(mountField({ result: true }))
		expect(ok.props('error')).toBe(false)
		expect(ok.props('success')).toBe(true)

		const idle = field(mountField({ result: null }))
		expect(idle.props('error')).toBe(false)
		expect(idle.props('success')).toBe(false)
	})

	it('sets a minimum of 1 for number fields only', () => {
		expect(field(mountField({ type: 'number' })).props('min')).toBe('1')
		expect(field(mountField({ type: 'text' })).props('min')).toBeUndefined()
	})

	it('blocks "-" and "e" while typing in a number field, nothing else', () => {
		const dispatch = (wrapper, key) => {
			const event = new KeyboardEvent('keydown', { key, cancelable: true })
			wrapper.find('input').element.dispatchEvent(event)
			return event.defaultPrevented
		}

		const number = mountField({ type: 'number' })
		expect(dispatch(number, '-')).toBe(true)
		expect(dispatch(number, 'e')).toBe(true)
		expect(dispatch(number, '5')).toBe(false)

		// A non-number field never blocks anything
		expect(dispatch(mountField({ type: 'text' }), '-')).toBe(false)
	})
})

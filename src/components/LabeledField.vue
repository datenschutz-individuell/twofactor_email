<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- One admin settings field: a real label plus the matching input.
     The label sits left of the input (above on narrow screens), or above
     when `stacked` is set. -->
<template>
	<div :class="{ 'labeled-field--stacked': stacked }" class="labeled-field">
		<label :for="id" class="labeled-field__label">{{ label }}</label>
		<NcTextArea v-if="type === 'textarea'"
					:id="id"
					v-model="model"
					:error="result === false"
					:label-outside="true"
					:loading="loading"
					:placeholder="placeholder"
					:success="result === true" />
		<NcTextField v-else
					 :id="id"
					 v-model="model"
					 :error="result === false"
					 :helper-text="helperText"
					 :label-outside="true"
					 :loading="loading"
					 :min="type === 'number' ? '1' : undefined"
					 :placeholder="placeholder"
					 :success="result === true"
					 :type="type"
					 @keydown="blockInvalidNumericInput" />
	</div>
</template>

<script setup>
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'

const model = defineModel({ type: [String, Number], default: null })

const props = defineProps({
	id: { type: String, required: true },
	label: { type: String, required: true },
	/** 'text', 'number' or 'textarea' */
	type: { type: String, default: 'text' },
	/** Per-field save feedback: true = success, false = error, null = idle */
	result: { type: Boolean, default: null },
	loading: { type: Boolean, default: false },
	placeholder: { type: String, default: undefined },
	helperText: { type: String, default: '' },
	stacked: { type: Boolean, default: false },
})

/**
 * Blocks '-' (minus) and 'e' (scientific notation) in number inputs.
 * The min="1" attribute handles values after entry; this blocks them
 * during typing so invalid characters never appear in the field.
 *
 * @param {KeyboardEvent} event - The keyboard event from the input field
 */
function blockInvalidNumericInput(event) {
	if (props.type === 'number' && (event.key === '-' || event.key === 'e')) {
		event.preventDefault()
	}
}
</script>

<style scoped>
.labeled-field {
	display: grid;
	grid-template-columns: 130px 1fr;
	gap: 4px 16px;
	align-items: center;
	margin-bottom: 8px;
	max-width: 64em;
}

/* Stacked variant and narrow screens: label above the input */
.labeled-field--stacked {
	grid-template-columns: 1fr;
	align-items: start;
}

@media (max-width: 640px) {
	.labeled-field {
		grid-template-columns: 1fr;
	}
}
</style>

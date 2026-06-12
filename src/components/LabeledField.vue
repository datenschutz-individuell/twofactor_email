<!--
  - SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<!-- One admin settings field: a real label above the matching input. -->
<template>
	<div class="labeled-field">
		<label :for="id" class="labeled-field__label">{{ label }}</label>
		<NcTextArea v-if="type === 'textarea'"
					:id="id"
					v-model="model"
					:error="result === false"
					:helper-text="helperText"
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
/* Label above the input; clear separation from the text before the field */
.labeled-field {
	margin: 16px 0 8px;
	max-width: 64em;
}

.labeled-field__label {
	display: block;
	margin-bottom: 4px;
}
</style>

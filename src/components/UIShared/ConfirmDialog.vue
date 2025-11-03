<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextField from '@nextcloud/vue/components/NcTextField'

// FIXME use real types from @nextcloud/vue
type ClassType = string | Record<string, boolean | undefined>
type VueClassType = ClassType | ClassType[] | VueClassType[]
type NcDialogButtonProps = {
	label: string
	callback?: () => unknown | false | Promise<unknown | false>
	disabled?: boolean
	icon?: string
	type?: 'submit' | 'reset' | 'button'
	variant?: 'primary' | 'secondary' | 'tertiary' | 'tertiary-no-background' | 'tertiary-on-primary' | 'error' | 'warning' | 'success'
}
type NcDialogProps = {
	name: string
	buttons?: NcDialogButtonProps[]
	container?: string
	message?: string
	size?: 'small' | 'normal' | 'large' | 'full'
	additionalTrapElements?: Array<string | HTMLElement>
	closeOnClickOutside?: boolean
	contentClasses?: VueClassType
	dialogClasses?: VueClassType
	isForm?: boolean
	navigationAriaLabel?: string
	navigationAriaLabelledby?: string
	navigationClasses?: VueClassType
	noClose?: boolean
	outTransition?: boolean
}
type NcInputFieldProps = {
	disabled?: boolean
	error?: boolean
	id?: string
	value?: string
	label?: string
	labelOutside?: boolean
	placeholder?: string
	showTrailingButton?: boolean
}

const props = defineProps<NcDialogProps & {
	/** This replaces 'message' in slot */
	customMessages?: string[]
	inputProps?: NcInputFieldProps
}>()

const emit = defineEmits<{
	(event: 'close', value?: unknown): void
}>()

const inputValue = ref(props.inputProps?.value ?? '')

/**
 * Emit result, if any (for spawnDialog callback)
 *
 * @param result callback result
 */
function onClosing(result: unknown) {
	if (props.isForm && props.inputProps) {
		onSubmit(inputValue.value)
	} else {
		emit('close', result)
	}
}

/**
 * Emit submitted new input value (for spawnDialog callback)
 *
 * @param value new value
 */
function onSubmit(value: string) {
	emit('close', value)
}
</script>

<template>
	<NcDialog
		:name="name"
		:message="message"
		:container="container"
		:size="size"
		:buttons="buttons"
		@closing="onClosing">
		<template v-if="customMessages">
			<p v-for="customMessage in customMessages" :key="customMessage">
				{{ customMessage }}
			</p>
		</template>
		<template v-if="isForm && inputProps">
			<NcTextField
				v-model="inputValue"
				:label="inputProps.label"
				:disabled="inputProps.disabled"
				:show-trailing-button="inputProps.showTrailingButton"
				@keydown.enter="onSubmit(inputValue)" />
		</template>
	</NcDialog>
</template>

<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog'

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

const props = defineProps<NcDialogProps>()

const emit = defineEmits<{
	(event: 'close', value?: unknown): void
}>()

/**
 * Emit result, if any (for spawnDialog callback)
 *
 * @param result callback result
 */
function onClosing(result: unknown) {
	emit('close', result)
}
</script>

<template>
	<NcDialog :name="name"
		:message="message"
		:container="container"
		:size="size"
		:buttons="buttons"
		@closing="onClosing" />
</template>

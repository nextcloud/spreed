<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import NcDialog from '@nextcloud/vue/components/NcDialog'

type NcDialogButtonProps = {
	label: string,
	callback?: () => unknown | false | Promise<unknown | false>,
	disabled?: boolean,
	icon?: string,
	// FIXME deprecated, use type since 8.24.0
	nativeType?: string,
	// FIXME deprecated, use variant since 8.24.0
	type?: string,
	variant?: string,
}
type NcDialogProps = {
	name: string,
	buttons: NcDialogButtonProps[],
	container?: string,
	message?: string,
	size?: string,
}

const props = defineProps<NcDialogProps>()

const emit = defineEmits<{
	(event: 'close', value?: unknown): void,
}>()

/**
 * Emit result, if any (for spawnDialog callback)
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

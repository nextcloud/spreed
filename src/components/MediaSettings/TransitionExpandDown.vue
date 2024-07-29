<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
defineProps<{
	show: boolean,
}>()

const emit = defineEmits<{
	(event: 'after-enter'): void,
	(event: 'after-leave'): void,
}>()
</script>

<template>
	<Transition name="expand-down"
		@after-enter="emit('after-enter')"
		@after-leave="emit('after-leave')">
		<div v-show="show" class="expand-down-wrapper">
			<slot />
		</div>
	</Transition>
</template>

<style scoped>
.expand-down-wrapper {
	display: grid;
	grid-template-rows: 1fr;
	grid-template-columns: 1fr;
	overflow: hidden;
}

.expand-down-enter-active,
.expand-down-leave-active {
	transition: grid-template-rows ease var(--animation-slow);
}

.expand-down-enter,
.expand-down-leave-to {
	grid-template-rows: 0fr;
}

.expand-down-enter-to,
.expand-down-leave {
	grid-template-rows: 1fr;
}
</style>

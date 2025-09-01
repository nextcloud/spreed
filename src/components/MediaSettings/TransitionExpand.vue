<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
defineProps<{
	show: boolean
	direction: 'vertical' | 'horizontal'
}>()

const emit = defineEmits<{
	(event: 'after-enter'): void
	(event: 'after-leave'): void
}>()
</script>

<template>
	<Transition
		:name="`expand-${direction}`"
		@after-enter="emit('after-enter')"
		@after-leave="emit('after-leave')">
		<div v-show="show" class="expand-wrapper">
			<div class="expand-wrapper__content">
				<slot />
			</div>
		</div>
	</Transition>
</template>

<style lang="scss" scoped>
.expand-wrapper {
	display: grid;
	grid-template-rows: 1fr;
	grid-template-columns: 1fr;

	&__content {
		overflow: hidden;
	}
}

/*
 * Vertical expand transition
 */

.expand-vertical-enter-active,
.expand-vertical-leave-active {
	transition: grid-template-rows ease var(--animation-slow);
}

.expand-vertical-enter,
.expand-vertical-leave-to {
	grid-template-rows: 0fr;
}

.expand-vertical-enter-to,
.expand-vertical-leave {
	grid-template-rows: 1fr;
}

/*
 * Horizontal expand transition
 */

.expand-horizontal-enter-active,
.expand-horizontal-leave-active {
	transition: grid-template-columns ease var(--animation-slow);
}

.expand-horizontal-enter,
.expand-horizontal-leave-to {
	grid-template-columns: 0fr;
}

.expand-horizontal-enter-to,
.expand-horizontal-leave {
	grid-template-columns: 1fr;
}
</style>

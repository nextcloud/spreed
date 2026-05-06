<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Slot } from 'vue'

import { computed } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { AVATAR, CONVERSATION } from '../../constants.ts'
import { useSettingsStore } from '../../stores/settings.ts'

const { compact = undefined } = defineProps<{
	compact?: boolean | undefined
	active?: boolean
}>()

defineSlots<{
	default?: Slot
	icon?: Slot
	badge?: Slot
}>()

const settingsStore = useSettingsStore()
const isCompact = computed(() => compact ?? (settingsStore.conversationsListStyle === CONVERSATION.LIST_STYLE.COMPACT))

const iconSize = computed(() => (isCompact.value ? AVATAR.SIZE.COMPACT : AVATAR.SIZE.DEFAULT) + 'px')
</script>

<template>
	<NcButton
		class="left-sidebar-button"
		:variant="active ? 'primary' : 'tertiary'"
		alignment="start"
		wide>
		<template #icon>
			<slot name="icon" />
		</template>
		<span class="left-sidebar-button__content">
			<span class="left-sidebar-button__text">
				<slot />
			</span>
			<slot name="badge" />
		</span>
	</NcButton>
</template>

<style lang="scss" scoped>
.left-sidebar-button {
	// Align the padding with navigation items and list items
	// TODO: make it s public variable or add a way to customize content without having the pre-defined padding
	--button-padding: calc(var(--default-grid-baseline) - 1px /* transparent border */);

	// TODO: fix upstream - allow custom icon size
	:deep(.button-vue__icon) {
		width: v-bind(iconSize);
		min-width: v-bind(iconSize);
	}

	// TODO: fix upstream - the text container is not stretched
	:deep(.button-vue__text) {
		flex: 1 0 auto;
	}
}

.left-sidebar-button__content {
	display: inline-flex;
	align-items: center;
	justify-content: space-between;
	gap: calc(2 * var(--default-grid-baseline));
	padding-inline-start: calc(2 * var(--default-grid-baseline));
	width: 100%;
	// Reset NcButton's font-weight
	font-weight: unset;
}

.left-sidebar-button__text {
	font-weight: 500;
}
</style>

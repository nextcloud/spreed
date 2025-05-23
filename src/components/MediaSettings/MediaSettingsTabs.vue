<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { CSSProperties, Component } from 'vue'

import { isRTL } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'

import TransitionExpand from './TransitionExpand.vue'

type TabDefinition = {
	id: string
	label: string
	icon: Component
}

const props = defineProps<{
	tabs: TabDefinition[]
	active?: string
}>()

const emit = defineEmits<{
	(event: 'update:active', value?: string): void
}>()

/** Whether the tab panel is open */
const isOpen = ref(!!props.active)

const isRTLDirection = isRTL()

// A11y ReferenceIDs
const randomId = Math.random().toString(36).substring(7)
const getRefId = (scope: 'tab' | 'panel', key: string) => `tab-${randomId}-${scope}-${key}`

/** Index of the active tab for the transition effect */
const activeIndex = computed(() => props.tabs.findIndex((tab) => tab.id === props.active))
/** Inline styles to shift tabs */
const tabStyles = computed<CSSProperties | undefined>(() => {
	return activeIndex.value !== -1
		? { transform: `translateX(${(isRTLDirection ? 1 : -1) * activeIndex.value * 100}%)` }
		: undefined
})

/**
 * Whether the tab is active
 * @param tabId - Tab ID
 */
function isActive(tabId: string) {
	return tabId === props.active
}

/**
 * Whether the tab is selected on UI
 * @param tabId - Tab ID
 */
function isSelected(tabId: string) {
	return isOpen.value && isActive(tabId)
}

/**
 * Toggle the tab:
 * - Toggle the tab on the current tab click
 * - Switch and open tab on a new tab click
 * @param tabId - New selected tabId
 */
function handleTabClick(tabId: string) {
	if (isActive(tabId)) {
		isOpen.value = !isOpen.value
	} else {
		emit('update:active', tabId)
		isOpen.value = true
	}
}

/**
 * Handle the tab panel closing transition finish
 */
function handleTabsAfterClosed() {
	// Emit tab change to none only when the tab panel is fully closed
	// Otherwise visually open tab disappears with transition during closing
	emit('update:active', undefined)
}
</script>

<template>
	<div class="tabs">
		<div class="tab-list" role="tablist">
			<NcButton v-for="tab in tabs"
				:id="getRefId('tab', tab.id)"
				:key="tab.id"
				wide
				role="tab"
				:type="isSelected(tab.id) ? 'secondary' : 'tertiary'"
				:aria-selected="isSelected(tab.id) ? 'true' : 'false'"
				:aria-controls="getRefId('panel', tab.id)"
				@click.stop="handleTabClick(tab.id)">
				<template #icon>
					<component :is="tab.icon" :size="20" />
				</template>
				{{ tab.label }}
			</NcButton>
		</div>

		<TransitionExpand :show="isOpen"
			direction="vertical"
			@after-leave="handleTabsAfterClosed">
			<div class="tab-panels-container">
				<div v-for="tab in tabs"
					:id="getRefId('panel', tab.id)"
					:key="tab.id"
					class="tab-panel"
					role="tabpanel"
					:inert="!isActive(tab.id)"
					:aria-hidden="!isActive(tab.id)"
					:aria-labelledby="getRefId('tab', tab.id)"
					:style="tabStyles">
					<slot :name="`tab-panel:${tab.id}`" />
				</div>
			</div>
		</TransitionExpand>
	</div>
</template>

<style lang="scss" scoped>
.tab-list {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.tab-panels-container {
	display: flex;
	width: 100%;
	overflow: hidden;
	transition: height ease var(--animation-slow);
}

.tab-panel {
	width: 100%;
	flex: 1 0 100%;
	transition: transform ease var(--animation-slow);
}
</style>

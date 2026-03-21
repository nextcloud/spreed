<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../../types/index.ts'

import { useVirtualList } from '@vueuse/core'
import { computed, toRef } from 'vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'
import ConversationCategoryHeader from './ConversationCategoryHeader.vue'
import ConversationItem from './ConversationItem.vue'
import { AVATAR } from '../../../constants.ts'

export type CategoryHeaderItem = {
	_type: 'category-header'
	id: string
	name: string
	categoryId: number | string
	collapsed: boolean
	unreadCount: number
	isFirst?: boolean
	isLast?: boolean
}

export type ListItem = Conversation | CategoryHeaderItem

const props = defineProps<{
	conversations: ListItem[]
	loading?: boolean
	compact?: boolean
}>()

const emit = defineEmits<{
	(e: 'toggleCategoryCollapsed', categoryId: number | string): void
	(e: 'renameCategory', payload: { categoryId: number, name: string }): void
	(e: 'moveCategoryUp', categoryId: number): void
	(e: 'moveCategoryDown', categoryId: number): void
	(e: 'deleteCategory', categoryId: number): void
}>()

/**
 * Type guard to check if a list item is a category header
 *
 * @param item The list item to check
 */
function isCategoryHeader(item: ListItem): item is CategoryHeaderItem {
	return '_type' in item && item._type === 'category-header'
}

/**
 * Consider:
 * avatar size (and two lines of text) or compact mode (28px)
 * list-item padding
 * list-item__wrapper padding
 */
const itemHeight = computed(() => props.compact ? 28 + 2 * 2 : AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2)

const { list, containerProps, wrapperProps } = useVirtualList<ListItem>(toRef(() => props.conversations), {
	itemHeight: () => itemHeight.value,
	overscan: 10,
})

/**
 * Get an index of the first fully visible conversation in viewport
 * Math.ceil to include partially of (absolute number of items above viewport) + 1 (next item is in viewport) - 1 (index starts from 0)
 */
function getFirstItemInViewportIndex(): number {
	return Math.ceil(containerProps.ref.value!.scrollTop / itemHeight.value)
}

/**
 * Get an index of the last fully visible conversation in viewport
 * Math.floor to include only fully visible of (absolute number of items below and in viewport) - 1 (index starts from 0)
 */
function getLastItemInViewportIndex(): number {
	return Math.floor((containerProps.ref.value!.scrollTop + containerProps.ref.value!.clientHeight) / itemHeight.value) - 1
}

/**
 * Scroll to conversation by index
 *
 * @param index - index of conversation to scroll to
 */
function scrollToItem(index: number) {
	const firstItemIndex = getFirstItemInViewportIndex()
	const lastItemIndex = getLastItemInViewportIndex()

	const viewportHeight = containerProps.ref.value!.clientHeight

	/**
	 * Scroll to a position with smooth scroll imitation
	 *
	 * @param to - target position (in px)
	 */
	const doScroll = (to: number) => {
		const ITEMS_TO_BORDER_AFTER_SCROLL = 1
		const padding = ITEMS_TO_BORDER_AFTER_SCROLL * itemHeight.value
		const from = containerProps.ref.value!.scrollTop
		const direction = from < to ? 1 : -1

		// If we are far from the target - instantly scroll to a close position
		if (Math.abs(from - to) > viewportHeight) {
			containerProps.ref.value!.scrollTo({
				top: to - direction * viewportHeight,
				behavior: 'instant',
			})
		}

		// Scroll to the target with smooth scroll
		containerProps.ref.value!.scrollTo({
			top: to + padding * direction,
			behavior: 'smooth',
		})
	}

	if (index < firstItemIndex) { // Item is above
		doScroll(index * itemHeight.value)
	} else if (index > lastItemIndex) { // Item is below
		// Position of item + item's height and move to bottom
		doScroll((index + 1) * itemHeight.value - viewportHeight)
	}
}

/**
 * Scroll to conversation by token
 *
 * @param token - token of conversation to scroll to
 */
function scrollToConversation(token: string) {
	const index = props.conversations.findIndex((item) => !isCategoryHeader(item) && item.token === token)
	if (index !== -1) {
		scrollToItem(index)
	}
}

/**
 * Handle toggling category collapsed state
 *
 * @param categoryId The category ID to toggle
 */
function handleToggleCollapsed(categoryId: number | string) {
	emit('toggleCategoryCollapsed', categoryId)
}

/**
 * Handle renaming a category
 *
 * @param payload Object with categoryId and new name
 * @param payload.categoryId
 * @param payload.name
 */
function handleRenameCategory(payload: { categoryId: number, name: string }) {
	emit('renameCategory', payload)
}

/**
 * Handle moving a category up
 *
 * @param categoryId The category ID to move
 */
function handleMoveCategoryUp(categoryId: number) {
	emit('moveCategoryUp', categoryId)
}

/**
 * Handle moving a category down
 *
 * @param categoryId The category ID to move
 */
function handleMoveCategoryDown(categoryId: number) {
	emit('moveCategoryDown', categoryId)
}

/**
 * Handle deleting a category
 *
 * @param categoryId The category ID to delete
 */
function handleDeleteCategory(categoryId: number) {
	emit('deleteCategory', categoryId)
}

defineExpose({
	getFirstItemInViewportIndex,
	getLastItemInViewportIndex,
	scrollToItem,
	scrollToConversation,
})
</script>

<template>
	<li
		:ref="containerProps.ref"
		:style="containerProps.style"
		@scroll="containerProps.onScroll">
		<LoadingPlaceholder v-if="loading" type="conversations" />
		<ul
			v-else
			:style="wrapperProps.style">
			<template v-for="item in list" :key="item.data.id">
				<ConversationCategoryHeader
					v-if="isCategoryHeader(item.data)"
					:name="(item.data as CategoryHeaderItem).name"
					:categoryId="(item.data as CategoryHeaderItem).categoryId"
					:collapsed="(item.data as CategoryHeaderItem).collapsed"
					:unreadCount="(item.data as CategoryHeaderItem).unreadCount"
					:isFirst="(item.data as CategoryHeaderItem).isFirst ?? false"
					:isLast="(item.data as CategoryHeaderItem).isLast ?? false"
					@toggleCollapsed="handleToggleCollapsed"
					@renameCategory="handleRenameCategory"
					@moveCategoryUp="handleMoveCategoryUp"
					@moveCategoryDown="handleMoveCategoryDown"
					@deleteCategory="handleDeleteCategory" />
				<ConversationItem
					v-else
					:item="item.data as Conversation"
					:compact />
			</template>
		</ul>
	</li>
</template>

<style lang="scss" scoped>
// Overwrite NcListItem styles
// TOREMOVE: get rid of it or find better approach
:deep(.list-item) {
	outline-offset: -2px;
}

/* Overwrite NcListItem styles for compact view */
:deep(.list-item--compact) {
	padding-block: 0 !important;
}

:deep(.list-item--compact:not(:has(.list-item-content__subname))) {
	--list-item-height: calc(var(--clickable-area-small, 24px) + 4px) !important;
}

:deep(.list-item--compact .button-vue--size-normal) {
	--button-size: var(--clickable-area-small, 24px);
	--button-radius: var(--border-radius);
}

:deep(.list-item--compact .list-item-content__actions) {
	height: var(--clickable-area-small, 24px);
}
</style>

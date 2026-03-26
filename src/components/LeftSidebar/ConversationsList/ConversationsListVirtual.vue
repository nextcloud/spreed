<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { useVirtualList } from '@vueuse/core'
import { computed, toRef } from 'vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'
import ConversationCategoryHeader from './ConversationCategoryHeader.vue'
import ConversationItem from './ConversationItem.vue'
import { AVATAR } from '../../../constants.ts'
import { useConversationCategoriesStore } from '../../../stores/conversationCategories.ts'
import { hasUnreadMentions } from '../../../utils/conversation.ts'

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

export type ListItem = (Conversation | CategoryHeaderItem) & { _key?: string }

const props = defineProps<{
	conversations: Conversation[]
	loading?: boolean
	compact?: boolean
	/**
	 * When true, conversations are split into category sections defined by the server.
	 * Requires hasCustomCategories to be true; otherwise renders a plain list.
	 */
	showCategories?: boolean
	/**
	 * When true, empty custom categories are hidden (used when a search filter is active).
	 */
	isFiltered?: boolean
}>()

const categoriesStore = useConversationCategoriesStore()

/**
 * Type guard to check if a list item is a category header
 *
 * @param item The list item to check
 */
function isCategoryHeader(item: ListItem): item is CategoryHeaderItem {
	return '_type' in item && item._type === 'category-header'
}

/**
 * Build the flat list that is fed to the virtual scroller.
 * When showCategories is true and custom categories exist, conversations are interspersed
 * with category-header sentinel items so the virtual list can render section headers.
 */
const listItems = computed<ListItem[]>(() => {
	if (!props.showCategories || !categoriesStore.hasCustomCategories) {
		return props.conversations
	}

	const hasCategorizedConversations = props.conversations.some((c) => !c.isFavorite && c.categoryIds && c.categoryIds.length > 0)
	if (!hasCategorizedConversations) {
		return props.conversations
	}

	const categories = categoriesStore.sortedCategories
	const favoriteConversations = props.conversations.filter((c) => c.isFavorite)
	const categorizedConversations = props.conversations.filter((c) => !c.isFavorite && c.categoryIds?.length > 0)
	const uncategorizedConversations = props.conversations.filter((c) => !c.isFavorite && (!c.categoryIds || c.categoryIds.length === 0))

	const result: ListItem[] = []

	for (let i = 0; i < categories.length; i++) {
		const category = categories[i]

		if (category.type === 'favorites') {
			if (favoriteConversations.length === 0) {
				continue
			}
			result.push({
				_type: 'category-header',
				id: 'category-favorites',
				name: t('spreed', 'Favorites'),
				categoryId: 'favorites',
				collapsed: category.collapsed,
				unreadCount: favoriteConversations.reduce((sum, c) => sum + (c.unreadMessages || 0), 0),
				isFirst: i === 0,
				isLast: i === categories.length - 1,
			})
			if (!category.collapsed) {
				result.push(...favoriteConversations)
			}
		} else if (category.type === 'other') {
			if (uncategorizedConversations.length === 0) {
				continue
			}
			result.push({
				_type: 'category-header',
				id: 'category-other',
				name: t('spreed', 'Other'),
				categoryId: 'other',
				collapsed: category.collapsed,
				unreadCount: uncategorizedConversations.reduce((sum, c) => sum + (c.unreadMessages || 0), 0),
				isFirst: i === 0,
				isLast: i === categories.length - 1,
			})
			if (!category.collapsed) {
				result.push(...uncategorizedConversations)
			}
		} else {
			const categoryConvs = categorizedConversations.filter((c) => c.categoryIds.includes(String(category.id)))
			if (categoryConvs.length === 0) {
				continue
			}
			result.push({
				_type: 'category-header',
				id: `category-${category.id}`,
				name: category.name,
				categoryId: category.id,
				collapsed: category.collapsed,
				unreadCount: categoryConvs.reduce((sum, c) => sum + (c.unreadMessages || 0), 0),
				isFirst: i === 0,
				isLast: i === categories.length - 1,
			})
			if (!category.collapsed) {
				result.push(...categoryConvs.map((conv) => ({ ...conv, _key: `${category.id}:${conv.token}` })))
			}
		}
	}

	return result
})

/**
 * Consider:
 * avatar size (and two lines of text) or compact mode (28px)
 * list-item padding
 * list-item__wrapper padding
 */
const itemHeight = computed(() => props.compact ? 28 + 2 * 2 : AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2)

const { list, containerProps, wrapperProps } = useVirtualList<ListItem>(toRef(() => listItems.value), {
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
	const index = listItems.value.findIndex((item) => !isCategoryHeader(item) && item.token === token)
	if (index !== -1) {
		scrollToItem(index)
	}
}

/**
 * Find the virtual-list index of the last conversation below the viewport that has unread mentions.
 * Returns null if none is found.
 */
function findLastUnreadMentionBelowViewportIndex(): number | null {
	const lastVisible = getLastItemInViewportIndex()
	for (let i = listItems.value.length - 1; i > lastVisible; i--) {
		const item = listItems.value[i]
		if (!isCategoryHeader(item) && hasUnreadMentions(item)) {
			return i
		}
	}
	return null
}

defineExpose({
	getFirstItemInViewportIndex,
	getLastItemInViewportIndex,
	scrollToItem,
	scrollToConversation,
	findLastUnreadMentionBelowViewportIndex,
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
			<template v-for="item in list" :key="item.data._key ?? item.data.id">
				<ConversationCategoryHeader
					v-if="isCategoryHeader(item.data)"
					:item="item.data as CategoryHeaderItem" />
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

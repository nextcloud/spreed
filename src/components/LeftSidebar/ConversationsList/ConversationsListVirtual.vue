<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="conversations"
		:item-size="itemSize"
		key-field="token">
		<template #default="{ item }">
			<Conversation :item="item" :compact="compact" />
		</template>
		<template #after>
			<LoadingPlaceholder v-if="loading" type="conversations" />
		</template>
	</RecycleScroller>
</template>

<script>
import { computed } from 'vue'
import { RecycleScroller } from 'vue-virtual-scroller'

import Conversation from './Conversation.vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'

import { AVATAR } from '../../../constants.ts'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

export default {
	name: 'ConversationsListVirtual',

	components: {
		LoadingPlaceholder,
		Conversation,
		RecycleScroller,
	},

	props: {
		conversations: {
			type: Array,
			required: true,
		},

		loading: {
			type: Boolean,
			default: false,
		},

		compact: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		/* Consider:
		* avatar size (and two lines of text) or compact mode (28px)
		* list-item padding
		* list-item__wrapper padding
		*/
		const itemSize = computed(() => props.compact ? 28 + 2 * 2 + 0 * 2 : AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2)
		return {
			itemSize,
		}
	},

	methods: {
		/**
		 * Get an index of the first fully visible conversation in viewport
		 *
		 * @public
		 * @return {number}
		 */
		getFirstItemInViewportIndex() {
			// (ceil to include partially) of (absolute number of items above viewport) + 1 (next item is in viewport) - 1 (index starts from 0)
			return Math.ceil(this.$refs.scroller.$el.scrollTop / this.itemSize)
		},

		/**
		 * Get an index of the last fully visible conversation in viewport
		 *
		 * @public
		 * @return {number}
		 */
		getLastItemInViewportIndex() {
			// (floor to include only fully visible) of (absolute number of items below and in viewport) - 1 (index starts from 0)
			return Math.floor((this.$refs.scroller.$el.scrollTop + this.$refs.scroller.$el.clientHeight) / this.itemSize) - 1
		},

		/**
		 * Scroll to conversation by index
		 *
		 * @public
		 * @param {number} index - index of conversation to scroll to
		 * @return {Promise<void>}
		 */
		async scrollToItem(index) {
			const firstItemIndex = this.getFirstItemInViewportIndex()
			const lastItemIndex = this.getLastItemInViewportIndex()

			const viewportHeight = this.$refs.scroller.$el.clientHeight

			/**
			 * Scroll to a position with smooth scroll imitation
			 *
			 * @param {number} to - target position
			 * @return {void}
			 */
			const doScroll = (to) => {
				const ITEMS_TO_BORDER_AFTER_SCROLL = 1
				const padding = ITEMS_TO_BORDER_AFTER_SCROLL * this.itemSize
				const from = this.$refs.scroller.$el.scrollTop
				const direction = from < to ? 1 : -1

				// If we are far from the target - instantly scroll to a close position
				if (Math.abs(from - to) > viewportHeight) {
					this.$refs.scroller.scrollToPosition(to - direction * viewportHeight)
				}

				// Scroll to the target with smooth scroll
				this.$refs.scroller.$el.scrollTo({
					top: to + padding * direction,
					behavior: 'smooth',
				})
			}

			if (index < firstItemIndex) { // Item is above
				await doScroll(index * this.itemSize)
			} else if (index > lastItemIndex) { // Item is below
				// Position of item + item's height and move to bottom
				await doScroll((index + 1) * this.itemSize - viewportHeight)
			}
		},

		/**
		 * Scroll to conversation by token
		 *
		 * @param {string} token - token of conversation to scroll to
		 * @return {void}
		 */
		scrollToConversation(token) {
			const index = this.conversations.findIndex((conversation) => conversation.token === token)
			if (index !== -1) {
				this.scrollToItem(index)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
// Overwrite NcListItem styles
:deep(.list-item) {
	overflow: hidden;
	outline-offset: -2px;

	.avatardiv .avatardiv__user-status {
		inset-inline-end: -2px !important;
		bottom: -2px !important;
		min-height: 11px !important;
		min-width: 11px !important;
	}
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

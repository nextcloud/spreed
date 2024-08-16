<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="conversations"
		:item-size="CONVERSATION_ITEM_SIZE"
		key-field="token">
		<template #default="{ item }">
			<Conversation :item="item" />
		</template>
		<template #after>
			<LoadingPlaceholder v-if="loading" type="conversations" />
		</template>
	</RecycleScroller>
</template>

<script>
import { RecycleScroller } from 'vue-virtual-scroller'

import Conversation from './Conversation.vue'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'

import { AVATAR } from '../../../constants.js'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

/* Consider:
 * avatar size (and two lines of text)
 * list-item padding
 * list-item__wrapper padding
 */
const CONVERSATION_ITEM_SIZE = AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2

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
	},

	setup() {
		return {
			CONVERSATION_ITEM_SIZE,
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
			return Math.ceil(this.$refs.scroller.$el.scrollTop / CONVERSATION_ITEM_SIZE)
		},

		/**
		 * Get an index of the last fully visible conversation in viewport
		 *
		 * @public
		 * @return {number}
		 */
		getLastItemInViewportIndex() {
			// (floor to include only fully visible) of (absolute number of items below and in viewport) - 1 (index starts from 0)
			return Math.floor((this.$refs.scroller.$el.scrollTop + this.$refs.scroller.$el.clientHeight) / CONVERSATION_ITEM_SIZE) - 1
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
				const padding = ITEMS_TO_BORDER_AFTER_SCROLL * CONVERSATION_ITEM_SIZE
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
				await doScroll(index * CONVERSATION_ITEM_SIZE)
			} else if (index > lastItemIndex) { // Item is below
				// Position of item + item's height and move to bottom
				await doScroll((index + 1) * CONVERSATION_ITEM_SIZE - viewportHeight)
			}
		},

		/**
		 * Scroll to conversation by token
		 *
		 * @param {string} token - token of conversation to scroll to
		 * @return {void}
		 */
		scrollToConversation(token) {
			const index = this.conversations.findIndex(conversation => conversation.token === token)
			if (index !== -1) {
				this.scrollToItem(index)
			}
		},
	},
}
</script>

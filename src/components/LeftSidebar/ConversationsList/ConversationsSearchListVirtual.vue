<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="conversations"
		:item-size="CONVERSATION_ITEM_SIZE"
		key-field="token">
		<template #default="{ item }">
			<ConversationSearchResult :item="item" @click="onClick" />
		</template>
		<template #after>
			<LoadingPlaceholder v-if="loading" type="conversations" />
		</template>
	</RecycleScroller>
</template>

<script>
import { RecycleScroller } from 'vue-virtual-scroller'
import LoadingPlaceholder from '../../UIShared/LoadingPlaceholder.vue'
import ConversationSearchResult from './ConversationSearchResult.vue'
import { AVATAR } from '../../../constants.ts'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

const CONVERSATION_ITEM_SIZE = AVATAR.SIZE.DEFAULT + 2 * 4 + 2 * 2

export default {
	name: 'ConversationsSearchListVirtual',

	components: {
		LoadingPlaceholder,
		ConversationSearchResult,
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

	emits: ['select'],

	setup() {
		return {
			CONVERSATION_ITEM_SIZE,
		}
	},

	methods: {
		onClick(item) {
			this.$emit('select', item)
		},
	},
}
</script>

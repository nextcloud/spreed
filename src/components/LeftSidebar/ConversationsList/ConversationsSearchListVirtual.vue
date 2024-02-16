<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Grigorii Shartsev <me@shgk.me>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<RecycleScroller ref="scroller"
		item-tag="ul"
		:items="conversations"
		:item-size="CONVERSATION_ITEM_SIZE"
		key-field="token">
		<template #default="{ item }">
			<ConversationSearchResult :item="item" :expose-messages="exposeMessages" @click="onClick" />
		</template>
		<template #after>
			<LoadingPlaceholder v-if="loading" type="conversations" />
		</template>
	</RecycleScroller>
</template>

<script>
import { RecycleScroller } from 'vue-virtual-scroller'

import ConversationSearchResult from './ConversationSearchResult.vue'
import LoadingPlaceholder from '../../LoadingPlaceholder.vue'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

const CONVERSATION_ITEM_SIZE = 66

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
		exposeMessages: {
			type: Boolean,
			default: false,
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

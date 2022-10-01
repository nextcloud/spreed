<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license GNU AGPL version 3 or any later version
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
	<el :is="element"
		v-bind="source.props"
		:item="source"
		v-on="source.listeners" />
</template>

<script>
import Conversation from './ConversationsList/Conversation.vue'
import ConversationSearchResult from '../ConversationSearchResult.vue'

export default {
	name: 'VirtualScrollerItem',

	components: {
		Conversation,
		ConversationSearchResult,
	},

	props: {
		/**
		 * The item to be rendered
		 */
		source: {
			type: Object,
			required: true,
		},
	},

	computed: {
		element() {
			if (this.source.is === 'Conversation') {
				return Conversation
			} else {
				return ConversationSearchResult
			}
		},
	},
}
</script>

<style lang="scss" scoped>

</style>

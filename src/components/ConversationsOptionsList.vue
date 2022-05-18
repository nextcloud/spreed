<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
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
	<ul class="contacts-list">
		<ListItem v-for="item of items"
			:key="item.id"
			:title="item.label"
			@click="onClick(item)">
			<template #icon>
				<ConversationIcon :item="iconData(item)"
					:disable-menu="true" />
			</template>
		</ListItem>
	</ul>
</template>

<script>
import ConversationIcon from './ConversationIcon.vue'
import ListItem from '@nextcloud/vue/dist/Components/ListItem'
import { CONVERSATION } from '../constants.js'

export default {
	name: 'ConversationsOptionsList',
	components: {
		ConversationIcon,
		ListItem,
	},
	props: {
		items: {
			type: Array,
			required: true,
		},
		isLoading: {
			type: Boolean,
			default: false,
		},
	},
	methods: {
		// forward click event
		onClick(item) {
			this.$emit('click', item)
		},
		iconData(item) {
			if (item.source === 'users') {
				return {
					type: CONVERSATION.TYPE.ONE_TO_ONE,
					displayName: item.label,
					name: item.id,
				}
			}
			return {
				type: CONVERSATION.TYPE.GROUP,
				objectType: item.source,
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.ellipsis {
	text-overflow: ellipsis;
}

// Override vue overflow rules for <ul> elements within app-navigation
.contacts-list {
	overflow: visible !important;
}
</style>

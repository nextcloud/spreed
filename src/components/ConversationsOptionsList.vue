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
		<AppContentListItem
			v-for="item of items"
			:key="item.id"
			:title="item.label"
			@click="onClick(item)">
			<template
				v-slot:icon>
				<ConversationIcon
					:item="iconData(item)" />
			</template>
		</AppContentListItem>
	</ul>
</template>

<script>
import ConversationIcon from './ConversationIcon'
import AppContentListItem from './LeftSidebar/ConversationsList/AppContentListItem/AppContentListItem'
import { CONVERSATION } from '../constants'

export default {
	name: 'ConversationsOptionsList',
	components: {
		ConversationIcon,
		AppContentListItem,
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

.contacts-list {
	overflow: visible;
	display: block;
}
</style>

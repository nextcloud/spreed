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
			<template v-if="!useAvatar"
				v-slot:icon>
				<ConversationIcon
					:item="iconData" />
			</template>
			<Avatar v-else
				slot="icon"
				:size="44"
				:user="item.id"
				:display-name="item.label" />
		</AppContentListItem>
	</ul>
</template>

<script>
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import ConversationIcon from './ConversationIcon'
import AppContentListItem from './LeftSidebar/ConversationsList/AppContentListItem/AppContentListItem'
import { CONVERSATION } from '../constants'

export default {
	name: 'ParticipantOptionsList',
	components: {
		Avatar,
		ConversationIcon,
		AppContentListItem,
	},
	props: {
		items: {
			type: Array,
			required: true,
		},
		type: {
			type: Number,
			default: CONVERSATION.TYPE.GROUP,
		},
		isLoading: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		useAvatar() {
			return this.type === CONVERSATION.TYPE.ONE_TO_ONE
		},
		iconData() {
			return {
				type: this.icon,
			}
		},
	},
	methods: {
		// forward click event
		onClick(item) {
			this.$emit('click', item)
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

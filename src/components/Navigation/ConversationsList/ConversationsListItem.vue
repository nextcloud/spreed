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
	<AppContentListItem
		:title="item.displayName"
		@click.prevent.exact="joinConversation(item.token)">
		<ConversationIcon
			slot="icon"
			:item="item" />
		<template slot="subtitle">
			{{ item.lastMessage.message }}
		</template>
		<AppNavigationCounter v-if="item.unreadMessages"
			slot="counter"
			:highlighted="true">
			{{ item.unreadMessages }}
		</AppNavigationCounter>
		<template slot="actions">
			<ActionButton
				icon="icon-delete"
				@click.prevent.exact="deleteConversation(item.token)">
				{{ t('spreed', 'Leave Conversation') }}
			</ActionButton>
			<ActionButton
				icon="icon-delete"
				@click.prevent.exact="deleteConversation(item.token)">
				{{ t('spreed', 'Leave Conversation2') }}
			</ActionButton>
		</template>
	</AppContentListItem>
</template>

<script>
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import ConversationIcon from './../../ConversationIcon'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import AppContentListItem from './AppContentListItem/AppContentListItem'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import { joinConversation, removeCurrentUserFromConversation } from '../../../services/participantsService'

export default {
	name: 'ConversationsListItem',
	components: {
		ActionButton,
		AppContentListItem,
		AppNavigationCounter,
		ConversationIcon
	},
	props: {
		item: {
			type: Object,
			default: function() {
				return {
					unreadMessages: 0,
					objectType: '',
					type: 0,
					displayName: ''
				}
			}
		}
	},
	computed: {
	},
	methods: {
		async joinConversation(token) {
			await joinConversation(token)
		},
		/**
		 * Deletes the current user from the conversation.
		 * @param {string} token The token of the conversation to be left.
		 */
		async deleteConversation(token) {
			const response = await removeCurrentUserFromConversation(token)
			console.debug(response)
		}
	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}

.ellipsis {
	text-overflow: ellipsis;
}
</style>

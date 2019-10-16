<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me
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
	<ul class="conversations">
		<AppContentListItem
			v-for="item of conversationsList"
			:key="item.id"
			:to="{ name: 'conversation', params: { token: item.token }}"
			:title="item.displayName"
			@click.prevent.exact="joinConversation(item.token)">
			<ConversationIcon
				slot="icon"
				:item="item" />
			<template slot="subtitle">
				{{ item.lastMessage.message }}
			</template>
			<AppNavigationCounter
				slot="counter"
				:highlighted="true">
				3
			</AppNavigationCounter>
			<template slot="actions">
				<ActionButton
					icon="icon-edit"
					@click="alert('Edit')">
					Edit
				</ActionButton>
				<ActionButton
					icon="icon-delete"
					@click.prevent.exact="deleteConversation(item.token)">
					{{ t('spreed', 'Leave Conversation') }}
				</ActionButton>
			</template>
		</AppContentListItem>
	</ul>
</template>

<script>
import ConversationIcon from '../../ConversationIcon'
import AppNavigationCounter from 'nextcloud-vue/dist/Components/AppNavigationCounter'
import AppContentListItem from './AppContentListItem/AppContentListItem'
import ActionButton from 'nextcloud-vue/dist/Components/ActionButton'
import { fetchConversations } from '../../../services/conversationsService'
import { joinConversation, removeCurrentUserFromConversation } from '../../../services/participantsService'

export default {
	name: 'ConversationsList',
	components: {
		ConversationIcon,
		AppNavigationCounter,
		ActionButton,
		AppContentListItem
	},
	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		conversations() {
			return this.$store.getters.conversations
		}
	},
	async beforeMount() {
		/** Fetches the conversations from the server and then
		 * adds them one by one to the store.
		 */
		const conversations = await fetchConversations()
		conversations.data.ocs.data.forEach(conversation => {
			this.$store.dispatch('addConversation', conversation)
		})
	},
	methods: {
		async joinConversation(token) {
			await joinConversation(token)
		},
		handleInput(payload) {
			const selectedConversationToken = payload.token
			this.joinConversation(selectedConversationToken)
			this.$router.push({ path: `/call/${selectedConversationToken}` })
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
.conversations {
	overflow: visible;
}
</style>

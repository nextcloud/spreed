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
		<ConversationsListItem
			v-for="item of conversationsList"
			:key="item.id"
			:item="item" />
	</ul>
</template>

<script>
import ConversationsListItem from './ConversationsListItem'
import { fetchConversations } from '../../../services/conversationsService'

export default {
	name: 'ConversationsList',
	components: {
		ConversationsListItem
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
		handleInput(payload) {
			const selectedConversationToken = payload.token
			this.joinConversation(selectedConversationToken)
			this.$router.push({ path: `/call/${selectedConversationToken}` })
		}
	}
}
</script>

<style lang="scss" scoped>
.conversations {
	overflow: visible;
}
</style>

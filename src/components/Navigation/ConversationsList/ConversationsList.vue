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
		<Conversation
			v-for="item of sortedConversationsList"
			:key="item.id"
			:item="item" />
	</ul>
</template>

<script>
import Conversation from './Conversation'
import { fetchConversations } from '../../../services/conversationsService'

export default {
	name: 'ConversationsList',
	components: {
		Conversation
	},
	computed: {
		conversationsList() {
			return this.$store.getters.conversationsList
		},
		sortedConversationsList() {
			return this.conversationsList.slice().sort(this.sortConversations)
		}
	},
	beforeMount() {
		this.fetchConversations()
	},
	mounted() {
		/** Refreshes the conversations every 30 seconds */
		window.setInterval(() => {
			this.fetchConversations()
		}, 30000)
	},
	methods: {
		sortConversations(conversation1, conversation2) {
			if (conversation1.isFavorite !== conversation2.isFavorite) {
				return conversation1.isFavorite ? -1 : 1
			}

			return conversation2.lastActivity - conversation1.lastActivity
		},
		handleInput(payload) {
			const selectedConversationToken = payload.token
			this.joinConversation(selectedConversationToken)
			this.$router.push({ path: `/call/${selectedConversationToken}` })
		},
		async fetchConversations() {
			/** Fetches the conversations from the server and then adds them one by one
			 * to the store.
			 */
			const conversations = await fetchConversations()
			this.$store.dispatch('purgeConversationsStore')
			conversations.data.ocs.data.forEach(conversation => {
				this.$store.dispatch('addConversation', conversation)
			})
		}
	}
}
</script>

<style lang="scss" scoped>
.conversations {
	overflow: visible;
}
</style>

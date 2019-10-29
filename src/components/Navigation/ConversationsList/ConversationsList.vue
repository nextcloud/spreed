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
			v-for="item of conversationsList"
			:key="item.id"
			:item="item" />
		<Hint v-if="searchText"
			:hint="t('spreed', 'No search results')" />
	</ul>
</template>

<script>
import Conversation from './Conversation'
import Hint from '../Hint/Hint'
import { fetchConversations } from '../../../services/conversationsService'
import { EventBus } from '../../../services/EventBus'

export default {
	name: 'ConversationsList',
	components: {
		Conversation,
		Hint,
	},
	props: {
		searchText: {
			type: String,
			default: '',
		},
	},
	computed: {
		conversationsList() {
			let conversations = this.$store.getters.conversationsList

			if (this.searchText !== '') {
				conversations = conversations.filter(conversation => conversation.displayName.toLowerCase().indexOf(this.searchText.toLowerCase()) !== -1)
			}

			return conversations.sort(this.sortConversations)
		},
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
			/**
			 * Emits a global event that is used in App.vue to update the page title once the
			 * ( if the current route is a conversation and once the conversations are received)
			 */
			EventBus.$emit('conversationsReceived')
		},
	},
}
</script>

<style lang="scss" scoped>
.conversations {
	overflow: visible;
}
</style>

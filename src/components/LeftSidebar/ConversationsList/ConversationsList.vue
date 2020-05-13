<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
			:item="item"
			@click.native="handleConversationClick" />
		<template
			v-if="!initialisedConversations">
			<LoadingHint
				v-for="n in 5"
				:key="n" />
		</template>
		<Hint v-else-if="searchText && !conversationsList.length"
			:hint="t('spreed', 'No matches')" />
	</ul>
</template>

<script>
import Conversation from './Conversation'
import Hint from '../../Hint'
import LoadingHint from '../../LoadingHint'
import { fetchConversations } from '../../../services/conversationsService'
import { joinConversation, leaveConversation } from '../../../services/participantsService'
import { EventBus } from '../../../services/EventBus'
import debounce from 'debounce'

export default {
	name: 'ConversationsList',
	components: {
		Conversation,
		Hint,
		LoadingHint,
	},
	props: {
		searchText: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			initialisedConversations: false,
			isFetchingConversations: false,
		}
	},

	computed: {
		conversationsList() {
			let conversations = this.$store.getters.conversationsList

			if (this.searchText !== '') {
				const lowerSearchText = this.searchText.toLowerCase()
				conversations = conversations.filter(conversation => conversation.displayName.toLowerCase().indexOf(lowerSearchText) !== -1 || conversation.name.toLowerCase().indexOf(lowerSearchText) !== -1)
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
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 30000)

		EventBus.$on('routeChange', this.onRouteChange)
		EventBus.$on('shouldRefreshConversations', this.debounceFetchConversations)
	},
	beforeDestroy() {
		EventBus.$off('routeChange', this.onRouteChange)
		EventBus.$off('shouldRefreshConversations', this.debounceFetchConversations)
	},
	methods: {
		onRouteChange({ from, to }) {
			if (from.name === 'conversation') {
				leaveConversation(from.params.token)
			}
			if (to.name === 'conversation') {
				joinConversation(to.params.token)
				this.$store.dispatch('markConversationRead', to.params.token)
			}
		},
		sortConversations(conversation1, conversation2) {
			if (conversation1.isFavorite !== conversation2.isFavorite) {
				return conversation1.isFavorite ? -1 : 1
			}

			return conversation2.lastActivity - conversation1.lastActivity
		},

		debounceFetchConversations: debounce(function() {
			if (!this.isFetchingConversations) {
				this.fetchConversations()
			}
		}, 3000),

		async fetchConversations() {
			this.isFetchingConversations = true

			/**
			 * Fetches the conversations from the server and then adds them one by one
			 * to the store.
			 */
			try {
				const conversations = await fetchConversations()
				this.initialisedConversations = true
				this.$store.dispatch('purgeConversationsStore')
				conversations.data.ocs.data.forEach(conversation => {
					this.$store.dispatch('addConversation', conversation)
					if (conversation.token === this.$store.getters.getToken()) {
						this.$store.dispatch('markConversationRead', this.$store.getters.getToken())
					}
				})
				/**
				 * Emits a global event that is used in App.vue to update the page title once the
				 * ( if the current route is a conversation and once the conversations are received)
				 */
				EventBus.$emit('conversationsReceived', {
					singleConversation: false,
				})
				this.isFetchingConversations = false
			} catch (error) {
				console.debug('Error while fetching conversations: ', error)
				this.isFetchingConversations = false
			}
		},
		// Emit the click event so the search text in the leftsidebar can be reset.
		handleConversationClick() {
			this.$emit('click-conversation')
		},
	},
}
</script>

<style lang="scss" scoped>
.conversations {
	overflow: visible;
}
</style>

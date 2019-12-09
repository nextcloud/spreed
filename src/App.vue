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
	<Content :class="{'icon-loading': loading, 'in-call': showChatInSidebar}" app-name="Talk">
		<LeftSidebar v-if="getUserId" />
		<AppContent>
			<router-view />
		</AppContent>
		<RightSidebar
			:show-chat-in-sidebar="showChatInSidebar" />
	</Content>
</template>

<script>
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'
import LeftSidebar from './components/LeftSidebar/LeftSidebar'
import Router from './router/router'
import RightSidebar from './components/RightSidebar/RightSidebar'
import { EventBus } from './services/EventBus'
import { getCurrentUser } from '@nextcloud/auth'
import { fetchConversation } from './services/conversationsService'
import { joinConversation } from './services/participantsService'
import { PARTICIPANT } from './constants'

export default {
	name: 'App',
	components: {
		Content,
		AppContent,
		RightSidebar,
		LeftSidebar,
	},
	data: function() {
		return {
			savedLastMessageMap: {},
			defaultPageTitle: false,
			loading: false,
			windowIsVisible: true,
		}
	},

	computed: {
		conversations() {
			return this.$store.getters.conversations
		},

		getUserId() {
			return this.$store.getters.getUserId()
		},

		participant() {
			if (typeof this.token === 'undefined') {
				return {
					inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				}
			}

			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex !== -1) {
				return this.$store.getters.getParticipant(this.token, participantIndex)
			}

			return {
				inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			}
		},

		showChatInSidebar() {
			return this.participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},

		/**
		 * Keeps a list for all last message ids
		 * @returns {object} Map with token => lastMessageId
		 */
		lastMessageMap() {
			const conversationList = this.$store.getters.conversationsList
			if (conversationList.length === 0) {
				return {}
			}

			const lastMessage = {}
			conversationList.forEach(conversation => {
				lastMessage[conversation.token] = 0 + (conversation.lastMessage && conversation.lastMessage.id ? conversation.lastMessage.id : 0)
			})
			return lastMessage
		},

		/**
		 * @returns {boolean} Returns true, if
		 * - a conversation is newly added to lastMessageMap
		 * - a conversation has a different last message id then previously
		 */
		atLeastOneLastMessageIdChanged() {
			let modified = false
			Object.keys(this.lastMessageMap).forEach(token => {
				if (!this.savedLastMessageMap[token]
					|| this.savedLastMessageMap[token] !== this.lastMessageMap[token]) {
					modified = true
				}
			})

			return modified
		},

		/**
		 * The current conversation token
		 * @returns {string} The token.
		 */
		token() {
			return this.$route.params.token
		},
	},

	watch: {
		atLeastOneLastMessageIdChanged() {
			if (this.windowIsVisible) {
				return
			}

			this.setPageTitle(this.getConversationName(this.token), this.atLeastOneLastMessageIdChanged)
		},
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		window.addEventListener('resize', this.onResize)
		document.addEventListener('visibilitychange', this.changeWindowVisibility)

		this.onResize()
		/**
		 * Listens to the conversationsReceived globalevent, emitted by the conversationsList
		 * component each time a new batch of conversations is received and processed in
		 * the store.
		 */
		EventBus.$once('conversationsReceived', () => {
			if (this.$route.name === 'conversation') {
				// Adjust the page title once the conversation list is loaded
				this.setPageTitle(this.getConversationName(this.token), false)

				// Automatically join the conversation as well
				joinConversation(this.token)
			}

			if (!getCurrentUser()) {
				// Set the current actor/participant for guests
				const conversation = this.$store.getters.conversations[this.token]
				this.$store.dispatch('setCurrentParticipant', conversation)
			}
		})

		EventBus.$on('conversationsReceived', () => {
			if (this.$route.name === 'conversation'
				&& !this.$store.getters.conversations[this.token]) {
				this.$router.push('/apps/spreed')
			}
		})

		/**
		 * Global before guard, this is called whenever a navigation is triggered.
		*/
		Router.beforeEach((to, from, next) => {
			/**
			 * This runs whenever the new route is a conversation.
			 */
			if (to.name === 'conversation') {
				// Page title
				const NEXT_CONVERSATION_NAME = this.getConversationName(to.params.token)
				this.setPageTitle(NEXT_CONVERSATION_NAME)
			}
			/**
			 * Fires a global event that tells the whole app that the route has changed. The event
			 * carries the from and to objects as payload
			 */
			EventBus.$emit('routeChange', { from, to })

			next()
		})

		if (getCurrentUser()) {
			console.debug('Setting current user')
			this.$store.dispatch('setCurrentUser', getCurrentUser())
		} else {
			console.debug('Can not set current user because it\'s a guest')
		}

		if (this.getUserId === null) {
			this.fetchSingleConversation(this.token)
			window.setInterval(() => {
				this.fetchSingleConversation(this.token)
			}, 30000)
		}
	},

	methods: {
		changeWindowVisibility() {
			this.windowIsVisible = !document.hidden
			if (this.windowIsVisible) {
				// Remove the potential "*" marker for unread chat messages
				this.setPageTitle(this.getConversationName(this.token), false)
			} else {
				// Copy the last message map to the saved version,
				// this will be our reference to check if any chat got a new
				// message since the last visit
				this.savedLastMessageMap = this.lastMessageMap
			}
		},

		/**
		 * Set the page title to the conversation name
		 * @param {string} title Prefix for the page title e.g. conversation name
		 * @param {boolean} showAsterix Prefix for the page title e.g. conversation name
		 */
		setPageTitle(title, showAsterix) {
			if (this.defaultPageTitle === false) {
				// On the first load we store the current page title "Talk - Nextcloud",
				// so we can append it every time again
				this.defaultPageTitle = window.document.title
				// When a conversation is opened directly, the "Talk - " part is
				// missing from the title
				if (this.defaultPageTitle.indexOf(t('spreed', 'Talk') + ' - ') !== 0) {
					this.defaultPageTitle = t('spreed', 'Talk') + ' - ' + this.defaultPageTitle
				}
			}

			if (title !== '') {
				window.document.title = (showAsterix ? '* ' : '') + `${title} - ${this.defaultPageTitle}`
			} else {
				window.document.title = (showAsterix ? '* ' : '') + this.defaultPageTitle
			}
		},

		onResize() {
			this.windowHeight = window.innerHeight - document.getElementById('header').clientHeight
		},

		/**
		 * Get a conversation's name.
		 * @param {string} token The conversation's token
		 * @returns {string} The conversation's name
		 */
		getConversationName(token) {
			if (!this.$store.getters.conversations[token]) {
				return ''
			}

			return this.$store.getters.conversations[token].displayName
		},

		async fetchSingleConversation(token) {
			/** Fetches the conversations from the server and then adds them one by one
			 * to the store.
			 */
			const response = await fetchConversation(token)
			// this.$store.dispatch('purgeConversationsStore')
			this.$store.dispatch('addConversation', response.data.ocs.data)

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
#content {
	height: 100%;

	&.in-call {
		::v-deep #app-navigation-toggle:before {
			/* Force white handle when inside a call */
			color: #FFFFFF;
		}
	}
}
</style>

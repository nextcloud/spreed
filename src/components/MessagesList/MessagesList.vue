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
<docs>

This component is a wrapper for the list of messages. It's main purpose it to
get the messagesList array and loop through the list to generate the messages.
In order not to render each and every messages that is in the store, we use
the Vue virtual scroll list component, whose docs you can find [here.](https://github.com/tangbc/vue-virtual-scroll-list)

</docs>

<template>
	<!-- size and remain refer to the amount and initial height of the items that
	are outside of the viewport -->
	<virtual-list :size="40"
		:remain="8"
		:variable="true"
		class="scroller">
		<MessagesGroup
			v-for="item of messagesGroupedByAuthor"
			:key="item[0].id"
			:style="{ height: item.height + 'px' }"
			v-bind="item"
			:messages="item"
			@deleteMessage="handleDeleteMessage" />
	</virtual-list>
</template>

<script>
import virtualList from 'vue-virtual-scroll-list'
import MessagesGroup from './MessagesGroup/MessagesGroup'
import { cancelableFetchMessages, cancelableLookForNewMessages } from '../../services/messagesService'
import { EventBus } from '../../services/EventBus'

export default {
	name: 'MessagesList',
	components: {
		MessagesGroup,
		virtualList,
	},

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},
	},

	data: function() {
		return {
			/**
			 * Keeps track of the state of the component in order to trigger the scroll to
			 * bottom.
			 */
			isInitiated: false,
			/**
			 * Stores the cancel function returned by `cancelableLookForNewMessages`,
			 * which allows to cancel the previous long polling request for new
			 * messages before making another one.
			 */
			cancelLookForNewMessages: null,
			/**
			 * Stores the cancel function returned by `cancelableFetchMessages`,
			 * which allows to cancel the previous request for old messages
			 * when quickly switching to a new conversation.
			 */
			cancelFetchMessages: null,
		}
	},

	computed: {
		/**
		 * Gets the messages array. We need this because the DynamicScroller needs an array to
		 * loop through.
		 *
		 * @returns {array}
		 */
		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},
		/**
		 * Gets the messages object, which is structured so that the key of each message element
		 * corresponds to the id of the message, and makes it easy and efficient to access the
		 * individual message object.
		 *
		 * @returns {object}
		 */
		messages() {
			return this.$store.getters.messages(this.token)
		},
		/**
		 * Creates an array of messages grouped in nested arrays by same autor.
		 * @returns {array}
		 */
		messagesGroupedByAuthor() {
			const groups = []
			let lastMessage = null
			for (const message of this.messagesList) {
				if (!this.messagesShouldBeGrouped(message, lastMessage)) {
					groups.push([message])
					lastMessage = message
				} else {
					groups[groups.length - 1].push(message)
				}
			}
			return groups
		},
	},

	/**
	 * Fetches the messages when the MessageList created. The router mounts this
	 * component only if the token is passed in so there's no need to check the
	 * token prop.
	 */
	created() {
		this.onRouteChange()
		/**
		 * Add a listener for routeChange event emitted by the App.vue component.
		 * Call the onRouteChange method function whenever the route changes.
		 */
		EventBus.$on('routeChange', () => {
			this.onRouteChange()
		})
	},

	beforeUpdate() {
		/**
		 * If the component is not initiated, scroll to the bottom of the message list.
		 */
		if (!this.isInitiated) {
			this.scrollToBottom()
			this.isInitiated = true
		}
	},

	methods: {
		/**
		 * Compare two messages to decide if they should be grouped
		 *
		 * @param {object} message1 The new message
		 * @param {string} message1.actorType Actor type of the new message
		 * @param {string} message1.actorId Actor id of the new message
		 * @param {string} message1.systemMessage System message content of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.actorType Actor type of the previous message
		 * @param {string} message2.actorId Actor id of the previous message
		 * @param {string} message2.systemMessage System message content of the previous message
		 * @returns {bool} Boolean if the messages should be grouped or not
		 */
		messagesShouldBeGrouped(message1, message2) {
			return message2 // Is there a previous message
				&& (
					message1.actorType !== 'bots' // Don't group messages of commands and bots
					|| message1.actorId === 'changelog') // Apart from the changelog bot
				&& (message1.systemMessage.length === 0) === (message2.systemMessage.length === 0) // Only group system messages with each others
				&& message1.actorType === message2.actorType // To have the same author, the type
				&& message1.actorId === message2.actorId //     and the id of the author must be the same
		},

		/**
		 * Fetches the messages of a conversation given the conversation token. Triggers
		 * a long-polling request for new messages.
		 */
		onRouteChange() {
			this.isInitiated = false
			this.getMessages()
		},
		async getMessages() {
			// Gets the history of the conversation.
			await this.getOldMessages()
			// Once the history is received, startslooking for new messages.
			this.getNewMessages()
		},
		async getOldMessages() {
			/**
			 * If there's already one pending request from a previous call
			 * of this method, we call the `cancelFetchMessages` function to clear it and reset
			 * the cancelRequest to null in the component's data.
			 */
			if (typeof this.cancelFetchMessages === 'function') {
				this.cancelFetchMessages('canceled')
				this.cancelFetchMessages = null
			}
			// Get a new request function and cancel function pair
			const { fetchMessages, cancelFetchMessages } = cancelableFetchMessages()
			// Assign the new cancel function to our data value
			this.cancelFetchMessages = cancelFetchMessages
			// Make the request
			try {
				const messages = await fetchMessages(this.token)
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					this.$store.dispatch('processMessage', message)
				})
			} catch {
				// No need to do anything here...
			}
		},
		/**
		 * Creates a long polling request for a new message.
		 */
		async getNewMessages() {
			/**
			 * If there's already one pending long polling request from a previous call
			 * of this method, we call the `cancelRequest` function to clear it and reset
			 * the cancelRequest to null in the component's data.
			 */
			if (typeof this.cancelLookForNewMessages === 'function') {
				this.cancelLookForNewMessages('canceled')
				this.cancelLookForNewMessages = null
			}
			// Get a new request function and cancel function pair
			const { lookForNewMessages, cancelLookForNewMessages } = cancelableLookForNewMessages()
			// Assign the new cancel function to our data value
			this.cancelLookForNewMessages = cancelLookForNewMessages
			const lastKnownMessageId = this.getLastKnownMessageId()

			try {
				const messages = await lookForNewMessages(this.token, lastKnownMessageId)
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					this.$store.dispatch('processMessage', message)
				})
				this.scrollToBottom()
			} catch (exception) {
				if (exception.message === 'canceled') {
					return
				}
			}
			/**
			 * If there are no new messages, the variable messages will be undefined, so the
			 * previous code block will be skipped and this method recursively calls itself.
			 */
			this.getNewMessages()
		},
		/**
		 * Dispatches the deleteMessages action.
		 * @param {object} event The deleteMessage event emitted by the Message component.
		 */
		handleDeleteMessage(event) {
			this.$store.dispatch('deleteMessage', event.message)
		},
		/**
		 * Scrolls to the bottom of the list.
		 */
		scrollToBottom() {
			this.$nextTick(function() {
				document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
			})
		},

		/**
		 * gets the last known message id.
		 * @returns {string} The last known message id.
		 */
		getLastKnownMessageId() {
			if (this.messagesList[this.messagesList.length - 1]) {
				return this.messagesList[this.messagesList.length - 1].id
			}
			return '0'
		},

	},
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}
</style>

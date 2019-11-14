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
import moment from '@nextcloud/moment'
import virtualList from 'vue-virtual-scroll-list'
import MessagesGroup from './MessagesGroup/MessagesGroup'
import { fetchMessages, lookForNewMessages } from '../../services/messagesService'
import { EventBus } from '../../services/EventBus'
import CancelableRequest from '../../utils/cancelableRequest'
import Axios from '@nextcloud/axios'

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
			cancelLookForNewMessages: () => {},
			/**
			 * Stores the cancel function returned by `cancelableFetchMessages`,
			 * which allows to cancel the previous request for old messages
			 * when quickly switching to a new conversation.
			 */
			cancelFetchMessages: () => {},
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
					// Add the date separator for different days
					if (this.messagesHaveDifferentDate(message, lastMessage)) {
						message.dateSeparator = this.generateDateSeparator(message)
					}

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
		 * @param {string} message1.id The ID of the new message
		 * @param {string} message1.actorType Actor type of the new message
		 * @param {string} message1.actorId Actor id of the new message
		 * @param {string} message1.systemMessage System message content of the new message
		 * @param {int} message1.timestamp Timestamp of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.id The ID of the second message
		 * @param {string} message2.actorType Actor type of the previous message
		 * @param {string} message2.actorId Actor id of the previous message
		 * @param {string} message2.systemMessage System message content of the previous message
		 * @param {int} message2.timestamp Timestamp of the second message
		 * @returns {boolean} Boolean if the messages should be grouped or not
		 */
		messagesShouldBeGrouped(message1, message2) {
			return message2 // Is there a previous message
				&& (
					message1.actorType !== 'bots' // Don't group messages of commands and bots
					|| message1.actorId === 'changelog') // Apart from the changelog bot
				&& (message1.systemMessage.length === 0) === (message2.systemMessage.length === 0) // Only group system messages with each others
				&& message1.actorType === message2.actorType // To have the same author, the type
				&& message1.actorId === message2.actorId //     and the id of the author must be the same
				&& !this.messagesHaveDifferentDate(message1, message2) // Posted on the same day
		},

		/**
		 * Check if 2 messages are from the same date
		 *
		 * @param {object} message1 The new message
		 * @param {string} message1.id The ID of the new message
		 * @param {int} message1.timestamp Timestamp of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.id The ID of the second message
		 * @param {int} message2.timestamp Timestamp of the second message
		 * @returns {boolean} Boolean if the messages have the same date
		 */
		messagesHaveDifferentDate(message1, message2) {
			return !message2 // There is no previous message
				|| this.getDateOfMessage(message1).format('YYYY-MM-DD') !== this.getDateOfMessage(message2).format('YYYY-MM-DD')
		},

		/**
		 * Generate the date header between the messages
		 *
		 * @param {object} message The message object
		 * @param {string} message.id The ID of the message
		 * @param {int} message.timestamp Timestamp of the message
		 * @returns {string} Translated string of "<Today>, <November 11th, 2019>", "<3 days ago>, <November 8th, 2019>"
		 */
		generateDateSeparator(message) {
			const date = this.getDateOfMessage(message)
			const dayOfYear = date.format('YYYY-DDD')
			let relativePrefix = date.fromNow()

			// Use the relative day for today and yesterday
			const dayOfYearToday = moment().format('YYYY-DDD')
			if (dayOfYear === dayOfYearToday) {
				relativePrefix = t('spreed', 'Today')
			} else {
				const dayOfYearYesterday = moment().subtract(1, 'days').format('YYYY-DDD')
				if (dayOfYear === dayOfYearYesterday) {
					relativePrefix = t('spreed', 'Yesterday')
				}
			}

			// <Today>, <November 11th, 2019>
			return t('spreed', '{relativeDate}, {absoluteDate}', {
				relativeDate: relativePrefix,
				// 'LL' formats a localized date including day of month, month
				// name and year
				absoluteDate: date.format('LL'),
			}, undefined, {
				escape: false, // French "Today" has a ' in it
			})
		},

		/**
		 * Generate the date of the messages
		 *
		 * @param {object} message The message object
		 * @param {string} message.id The ID of the message
		 * @param {int} message.timestamp Timestamp of the message
		 * @returns {object} MomentJS object
		 */
		getDateOfMessage(message) {
			if (message.id.toString().startsWith('temp-')) {
				return moment()
			}
			return moment.unix(message.timestamp)
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
			this.$nextTick(() => {
				this.getNewMessages()
			})
		},
		async getOldMessages() {
			/**
			 * Clear previous requests if there's one pending
			 */
			this.cancelFetchMessages('canceled')

			// Get a new cancelable request function and cancel function pair
			const { request, cancel } = CancelableRequest(fetchMessages)
			// Assign the new cancel function to our data value
			this.cancelFetchMessages = cancel
			// Make the request
			try {
				const messages = await request({ token: this.token })
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					this.$store.dispatch('processMessage', message)
				})
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
			}
		},
		/**
		 * Creates a long polling request for a new message.
		 */
		async getNewMessages() {
			// Clear previous requests if there's one pending
			this.cancelLookForNewMessages('canceled')
			// Get a new cancelable request function and cancel function pair
			const { request, cancel } = CancelableRequest(lookForNewMessages)
			// Assign the new cancel function to our data value
			this.cancelLookForNewMessages = cancel
			// Get the last message's id
			const lastKnownMessageId = this.getLastKnownMessageId()
			// Make the request
			try {
				const messages = await request({ token: this.token, lastKnownMessageId })
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					this.$store.dispatch('processMessage', message)
				})
				this.getNewMessages()
			} catch (exception) {
				if (exception.response) {
					/**
					 * Recursively call the same method if no new messages are returned
					 */
					if (exception.response.status === 304) {
						this.getNewMessages()
					}
				}
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
			}
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
			// Reverse a copy of the messages array
			const reversedMessages = this.messagesList.slice().reverse()
			// Get the id of the last non-temporary message
			for (const message of reversedMessages) {
				const id = message.id.toString()
				if (!id.startsWith('temp-')) {
					return id
				}
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

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

</docs>

<template>
	<!-- size and remain refer to the amount and initial height of the items that
	are outside of the viewport -->
	<div
		ref="scroller"
		class="scroller"
		@scroll="debounceHandleScroll">
		<div
			v-if="displayMessagesLoader"
			class="scroller__loading"
			disabled>
			<div
				class="icon-loading" />
		</div>
		<MessagesGroup
			v-for="item of messagesGroupedByAuthor"
			:key="item[0].id"
			:style="{ height: item.height + 'px' }"
			v-bind="item"
			:messages="item"
			:last-read-message="unreadMessageMarkerId"
			@deleteMessage="handleDeleteMessage" />
		<template v-if="!messagesGroupedByAuthor.length">
			<LoadingPlaceholder
				type="messages"
				:count="15" />
		</template>
		<transition name="fade">
			<button v-show="!isChatScrolledToBottom"
				:aria-label="scrollToBottomAriaLabel"
				class="scroll-to-bottom"
				@click="smoothScrollToBottom">
				<ChevronDown
					decorative
					title=""
					:size="20" />
			</button>
		</transition>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import MessagesGroup from './MessagesGroup/MessagesGroup'
import { fetchMessages, lookForNewMessages } from '../../services/messagesService'
import CancelableRequest from '../../utils/cancelableRequest'
import Axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import isInLobby from '../../mixins/isInLobby'
import debounce from 'debounce'
import { EventBus } from '../../services/EventBus'
import LoadingPlaceholder from '../LoadingPlaceholder'
import ChevronDown from 'vue-material-design-icons/ChevronDown'

export default {
	name: 'MessagesList',
	components: {
		LoadingPlaceholder,
		MessagesGroup,
		ChevronDown,
	},

	mixins: [
		isInLobby,
	],

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true,
		},

		isChatScrolledToBottom: {
			type: Boolean,
			required: true,
		},
	},

	data: function() {
		return {
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
			/**
			 * When scrolling to the top of the div .scroller we start loading previous
			 * messages. This boolean allows us to show/hide the loader.
			 */
			displayMessagesLoader: false,
			/**
			 * We store this value in order to determine whether the user has scrolled up
			 * or down at each iteration of the debounceHandleScroll method.
			 */
			previousScrollTopValue: null,

			pollingErrorTimeout: 1,

			oldMessagesPromise: null,

			lastReadMessage: 0,
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
				if (message.systemMessage === 'message_deleted') {
					continue
				}

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

		/**
		 * In order for the state of the component to be sticky,
		 * the div .scroller must be scrolled to the bottom.
		 * When isSticky is true, as new messages are appended to the list, the div .scroller
		 * automatically scrolls down to the last message, if it's false, new messages are
		 * appended but the scrolling position is not altered.
		 * @returns {boolean}
		 */
		isSticky() {
			return this.isChatScrolledToBottom
		},

		/**
		 * Returns whether the current participant is a participant of the
		 * current conversation or not.
		 *
		 * @returns {Boolean} true if it is already a participant, false
		 *          otherwise.
		 */
		isParticipant() {
			if (!this.conversation) {
				return false
			}

			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			return participantIndex !== -1
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		chatIdentifier() {
			return this.token + ':' + this.isParticipant + ':' + this.isInLobby
		},

		scrollToBottomAriaLabel() {
			return t('spreed', 'Scroll to bottom')
		},

		scroller() {
			return this.$refs.scroller
		},

		unreadMessageMarkerId() {
			if (this.lastReadMessage !== this.$store.getters.getLastKnownMessageId(this.token)) {
				return this.lastReadMessage
			}
			return 0
		},
	},

	watch: {
		chatIdentifier: {
			immediate: true,
			handler() {
				this.handleStartGettingMessagesPreconditions()
			},
		},
	},

	mounted() {
		this.scrollToBottom()
		EventBus.$on('scrollChatToBottom', this.handleScrollChatToBottomEvent)
		EventBus.$on('smoothScrollChatToBottom', this.smoothScrollToBottom)
		EventBus.$on('focusMessage', this.focusMessage)
		EventBus.$on('routeChange', this.onRouteChange)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
	},

	beforeDestroy() {
		EventBus.$off('scrollChatToBottom', this.handleScrollChatToBottomEvent)
		EventBus.$off('smoothScrollChatToBottom', this.smoothScrollToBottom)
		EventBus.$off('focusMessage', this.focusMessage)
		EventBus.$off('routeChange', this.onRouteChange)

		this.cancelLookForNewMessages()
		// Prevent further lookForNewMessages requests after the component was
		// destroyed.
		this.cancelLookForNewMessages = null

		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)
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
				&& message1.actorId === message2.actorId // and the id of the author must be the same
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

		handleStartGettingMessagesPreconditions() {
			if (this.token && this.isParticipant && !this.isInLobby) {
				this.lastReadMessage = this.conversation.lastReadMessage
				if (this.$store.getters.getFirstKnownMessageId(this.token) === null) {
					this.$store.dispatch('setFirstKnownMessageId', {
						token: this.token,
						id: this.conversation.lastReadMessage,
					})
					this.$store.dispatch('setLastKnownMessageId', {
						token: this.token,
						id: this.conversation.lastReadMessage,
					})

					this.getMessages(true)
				} else {
					this.getMessages(false)
				}
			} else if (this.cancelLookForNewMessages) {
				this.cancelLookForNewMessages()
			}
		},

		/**
		 * Fetches the messages of a conversation given the conversation token. Triggers
		 * a long-polling request for new messages.
		 * @param {boolean} loadOldMessages In case it is the first visit of this conversation, we need to load the history
		 */
		async getMessages(loadOldMessages) {
			let focussed = false
			if (loadOldMessages) {
				// Gets the history of the conversation.
				await this.getOldMessages(true)

				if (this.$route?.hash?.startsWith('#message_')) {
					// scroll to message in URL anchor
					focussed = this.focusMessage(this.$route.hash.substr(9), false)
				}
			}

			if (!focussed) {
				// if no anchor was present or the message to focus on did not exist,
				// simply scroll to bottom
				this.scrollToBottom()
			}

			// Once the history is received, starts looking for new messages.
			this.$nextTick(() => {
				if (this._isBeingDestroyed || this._isDestroyed) {
					console.debug('Prevent getting new messages on a destroyed MessagesList')
					return
				}

				const followInNewMessages = this.conversation.lastMessage
					&& this.conversation.lastReadMessage === this.conversation.lastMessage.id

				this.getNewMessages(followInNewMessages)
			})
		},

		/**
		 * Get messages history.
		 * @param {boolean} includeLastKnown Include or exclude the last known message in the response
		 */
		async getOldMessages(includeLastKnown) {
			/**
			 * Clear previous requests if there's one pending
			 */
			this.cancelFetchMessages('canceled')

			// Get a new cancelable request function and cancel function pair
			const { request, cancel } = CancelableRequest(fetchMessages)
			// Assign the new cancel function to our data value
			this.cancelFetchMessages = cancel

			const token = this.token
			const lastKnownMessageId = this.$store.getters.getFirstKnownMessageId(token)
			let newestKnownMessageId = 0

			// Make the request
			try {
				this.oldMessagesPromise = request({ token, lastKnownMessageId, includeLastKnown: includeLastKnown ? '1' : '0' })
				const messages = await this.oldMessagesPromise
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					if (message.actorType === 'guests') {
						this.$store.dispatch('setGuestNameIfEmpty', message)
					}
					this.$store.dispatch('processMessage', message)
					newestKnownMessageId = Math.max(newestKnownMessageId, message.id)
				})

				if (messages.headers['x-chat-last-given']) {
					this.$store.dispatch('setFirstKnownMessageId', {
						token: token,
						id: parseInt(messages.headers['x-chat-last-given'], 10),
					})
				}

				// For guests we also need to set the last known message id
				// after the first grab of the history, otherwise they start loading
				// the full history with getNewMessages().
				if (includeLastKnown && newestKnownMessageId
					&& !this.$store.getters.getLastKnownMessageId(token)) {
					this.$store.dispatch('setLastKnownMessageId', {
						token: token,
						id: newestKnownMessageId,
					})
				}
				this.oldMessagesPromise = null
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
				this.oldMessagesPromise = null
			}
		},

		/**
		 * Creates a long polling request for a new message.
		 * @param {boolean} scrollToBottom Whether we should try to automatically scroll to the bottom
		 */
		async getNewMessages(scrollToBottom = true) {
			if (!this.cancelLookForNewMessages) {
				return
			}

			// Clear previous requests if there's one pending
			this.cancelLookForNewMessages('canceled')
			// Get a new cancelable request function and cancel function pair
			const { request, cancel } = CancelableRequest(lookForNewMessages)
			// Assign the new cancel function to our data value
			this.cancelLookForNewMessages = cancel
			// Get the last message's id
			const token = this.token
			const lastKnownMessageId = this.$store.getters.getLastKnownMessageId(token)

			// Make the request
			try {
				const messages = await request({ token, lastKnownMessageId })
				this.pollingErrorTimeout = 1

				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					if (message.actorType === 'guests') {
						this.$store.dispatch('forceGuestName', message)
					}
					this.$store.dispatch('processMessage', message)
				})

				this.$store.dispatch('setLastKnownMessageId', {
					token: token,
					id: parseInt(messages.headers['x-chat-last-given'], 10),
				})

				// Scroll to the last message if sticky
				if (scrollToBottom && this.isSticky) {
					this.smoothScrollToBottom()
				}
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
					return
				}

				if (exception.response && exception.response.status === 304) {
					// 304 - Not modified
					// This is not an error, so reset error timeout and poll again
					this.pollingErrorTimeout = 1
					setTimeout(() => {
						this.getNewMessages()
					}, 500)
					return
				}

				if (this.pollingErrorTimeout < 30) {
					// Delay longer after each error
					this.pollingErrorTimeout += 5
				}

				console.debug('Error happened while getting chat messages. Trying again in ', this.pollingErrorTimeout, exception)

				setTimeout(() => {
					this.getNewMessages()
				}, this.pollingErrorTimeout * 1000)
				return
			}

			setTimeout(() => {
				this.getNewMessages()
			}, 500)
		},

		/**
		 * Dispatches the deleteMessages action.
		 * @param {object} event The deleteMessage event emitted by the Message component.
		 */
		handleDeleteMessage(event) {
			this.$store.dispatch('deleteMessage', event.message)
		},

		debounceHandleScroll: debounce(function() {
			this.handleScroll()
		}, 50),
		/**
		 * When the div is scrolled, this method checks if it's been scrolled to the top
		 * or to the bottom of the list bottom.
		 */
		async handleScroll() {
			const scrollHeight = this.scroller.scrollHeight
			const scrollTop = this.scroller.scrollTop
			const scrollOffset = scrollHeight - scrollTop
			const elementHeight = this.scroller.clientHeight
			const tolerance = 10
			if (scrollOffset < elementHeight + tolerance && scrollOffset > elementHeight - tolerance) {
				this.setChatScrolledToBottom(true)
				this.displayMessagesLoader = false
				this.previousScrollTopValue = scrollTop
			} else if (scrollHeight > elementHeight && scrollTop < 800 && scrollTop <= this.previousScrollTopValue) {
				if (this.oldMessagesPromise) {
					// already loading, don't do it twice
					return
				}
				if (scrollTop === 0) {
					this.displayMessagesLoader = true
				}
				await this.getOldMessages(false)
				this.displayMessagesLoader = false
				this.previousScrollTopValue = scrollTop
			} else {
				this.setChatScrolledToBottom(false)
				this.displayMessagesLoader = false
				this.previousScrollTopValue = scrollTop
			}
		},

		/**
		 * @param {object} options Event options
		 * @param {boolean} options.force Set to true, if the chat should be scrolled to the bottom even when it was not before
		 */
		handleScrollChatToBottomEvent(options) {
			if ((options && options.force) || this.isChatScrolledToBottom) {
				this.scrollToBottom()
				this.setChatScrolledToBottom(true)
			}
		},

		/**
		 * Scrolls to the bottom of the list smoothly.
		 */
		smoothScrollToBottom() {
			this.$nextTick(function() {
				if (this.$store.getters.windowIsVisible()) {
					// scrollTo is used when the user is watching
					this.scroller.scrollTo({
						top: this.scroller.scrollHeight,
						behavior: 'smooth',
					})
					this.setChatScrolledToBottom(true)
				} else {
					// Otherwise we jump half a message and stop autoscrolling, so the user can read up
					if (this.scroller.scrollHeight - this.scroller.scrollTop - this.scroller.offsetHeight < 40) {
						// Single new line from the previous author is 35px so scroll half a line
						this.scroller.scrollTop += 10
					} else {
						// Single new line from the new author is 75px so scroll half an avatar
						this.scroller.scrollTop += 40
					}
					this.setChatScrolledToBottom(false)
				}
			})
		},
		/**
		 * Scrolls to the bottom of the list.
		 */
		scrollToBottom() {
			this.$nextTick(function() {
				this.scroller.scrollTop = this.scroller.scrollHeight
				this.setChatScrolledToBottom(true)
			})

		},

		/**
		 * Temporarily highlight the given message id with a fade out effect.
		 *
		 * @param {string} messageId message id
		 * @param {boolean} smooth true to smooth scroll, false to jump directly
		 * @returns {bool} true if element was found, false otherwise
		 */
		focusMessage(messageId, smooth = true) {
			const element = document.getElementById(`message_${messageId}`)
			if (!element) {
				// TODO: in some cases might need to trigger a scroll up if this is an older message
				console.warn('Message to focus not found in DOM', messageId)
				return false
			}

			this.$nextTick(async() => {
				await element.scrollIntoView({
					behavior: smooth ? 'smooth' : 'auto',
					block: 'center',
					inline: 'nearest',
				})
				element.focus()
				element.highlightAnimation()
			})

			return true
		},

		/**
		 * gets the last known message id.
		 * @returns {string} The last known message id.
		 */
		getLastKnownMessageId() {
			let i = this.messagesList.length - 1

			while (i >= 0) {
				if (!this.messagesList[i].id.toString().startsWith('temp-')) {
					return this.messagesList[i].id
				}
				i--
			}
			return '0'
		},
		/**
		 * gets the first message's id.
		 * @returns {string}
		 */
		getFirstKnownMessageId() {
			return this.messagesList[0].id.toString()
		},

		handleNetworkOffline() {
			console.debug('Canceling message request as we are offline')
			if (this.cancelLookForNewMessages) {
				this.cancelLookForNewMessages()
			}
		},

		handleNetworkOnline() {
			console.debug('Restarting polling of new chat messages')
			this.getNewMessages()
		},

		onRouteChange({ from, to }) {
			if (from.name === 'conversation'
				&& to.name === 'conversation'
				&& from.token === to.token
				&& from.hash !== to.hash) {

				// the hash changed, need to focus/highlight another message
				if (to.hash && to.hash.startsWith('#message_')) {
					// need some delay (next tick is too short) to be able to run
					// after the browser's native "scroll to anchor" from
					// the hash
					window.setTimeout(() => {
						// scroll to message in URL anchor
						this.focusMessage(to.hash.substr(9), true)
					}, 2)
				}
			}
		},

		setChatScrolledToBottom(boolean) {
			this.$emit('setChatScrolledToBottom', boolean)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables.scss';

.scroller {
	flex: 1 0;
	overflow-y: auto;
	overflow-x: hidden;
	&__loading {
		height: 50px;
		display: flex;
		justify-content: center;
	}
}

.scroll-to-bottom {
	position: absolute;
	width: 44px;
	height: 44px;
	bottom: 76px;
	right: 24px;
	z-index: 2;
	padding: 0;
	margin: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

</style>

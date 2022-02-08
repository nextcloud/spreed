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
	<div ref="scroller"
		class="scroller"
		@scroll="debounceHandleScroll">
		<div v-if="displayMessagesLoader"
			class="scroller__loading"
			disabled>
			<div class="icon-loading" />
		</div>
		<MessagesGroup v-for="(item, index) of messagesGroupedByAuthor"
			:key="item[0].id"
			:style="{ height: item.height + 'px' }"
			v-bind="item"
			:last-read-message-id="visualLastReadMessageId"
			:messages="item"
			:next-message-id="(messagesGroupedByAuthor[index + 1] && messagesGroupedByAuthor[index + 1][0].id) || 0"
			:previous-message-id="(index > 0 && messagesGroupedByAuthor[index - 1][messagesGroupedByAuthor[index - 1].length - 1].id) || 0" />
		<template v-if="!messagesGroupedByAuthor.length">
			<LoadingPlaceholder type="messages"
				:count="15" />
		</template>
		<transition name="fade">
			<button v-show="!isChatScrolledToBottom"
				:aria-label="scrollToBottomAriaLabel"
				class="scroll-to-bottom"
				@click="smoothScrollToBottom">
				<ChevronDown decorative
					title=""
					:size="20" />
			</button>
		</transition>
	</div>
</template>

<script>
import moment from '@nextcloud/moment'
import MessagesGroup from './MessagesGroup/MessagesGroup'
import Axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import isInLobby from '../../mixins/isInLobby'
import { ATTENDEE } from '../../constants'
import debounce from 'debounce'
import { EventBus } from '../../services/EventBus'
import LoadingPlaceholder from '../LoadingPlaceholder'
import ChevronDown from 'vue-material-design-icons/ChevronDown'
import uniqueId from 'lodash/uniqueId'

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

		isVisible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			viewId: null,

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

			loadingOldMessages: false,

			destroying: false,
		}
	},

	computed: {
		isWindowVisible() {
			return this.$store.getters.windowIsVisible() && this.isVisible
		},

		visualLastReadMessageId() {
			return this.$store.getters.getVisualLastReadMessageId(this.token)
		},

		/**
		 * Gets the messages array. We need this because the DynamicScroller needs an array to
		 * loop through.
		 *
		 * @return {Array}
		 */
		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},
		/**
		 * Gets the messages object, which is structured so that the key of each message element
		 * corresponds to the id of the message, and makes it easy and efficient to access the
		 * individual message object.
		 *
		 * @return {object}
		 */
		messages() {
			// FIXME: remove if unused ?
			return this.$store.getters.messages(this.token)
		},
		/**
		 * Creates an array of messages grouped in nested arrays by same autor.
		 *
		 * @return {Array}
		 */
		messagesGroupedByAuthor() {
			const groups = []
			let lastMessage = null
			for (const message of this.messagesList) {
				if (message.systemMessage === 'message_deleted' || message.systemMessage === 'reaction') {
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
		 *
		 * @return {boolean}
		 */
		isSticky() {
			return this.isChatScrolledToBottom
		},

		/**
		 * Returns whether the current participant is a participant of the
		 * current conversation or not.
		 *
		 * @return {boolean} true if it is already a participant, false
		 *          otherwise.
		 */
		isParticipant() {
			if (!this.conversation) {
				return false
			}

			return !!this.$store.getters.findParticipant(this.token, this.$store.getters.getParticipantIdentifier())
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		chatIdentifier() {
			return this.token + ':' + this.isParticipant + ':' + this.isInLobby + ':' + this.viewId
		},

		scrollToBottomAriaLabel() {
			return t('spreed', 'Scroll to bottom')
		},

		scroller() {
			return this.$refs.scroller
		},
	},

	watch: {
		isWindowVisible(visible) {
			if (visible) {
				this.onWindowFocus()
			}
		},
		chatIdentifier: {
			immediate: true,
			handler(newValue, oldValue) {
				if (oldValue) {
					this.$store.dispatch('cancelLookForNewMessages', { requestId: oldValue })
				}
				this.handleStartGettingMessagesPreconditions()
			},
		},
	},

	mounted() {
		this.viewId = uniqueId('messagesList')
		this.scrollToBottom()
		EventBus.$on('scroll-chat-to-bottom', this.handleScrollChatToBottomEvent)
		EventBus.$on('smooth-scroll-chat-to-bottom', this.smoothScrollToBottom)
		EventBus.$on('focus-message', this.focusMessage)
		EventBus.$on('route-change', this.onRouteChange)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
		window.addEventListener('focus', this.onWindowFocus)
	},

	beforeDestroy() {
		window.removeEventListener('focus', this.onWindowFocus)
		EventBus.$off('scroll-chat-to-bottom', this.handleScrollChatToBottomEvent)
		EventBus.$off('smooth-scroll-chat-to-bottom', this.smoothScrollToBottom)
		EventBus.$off('focus-message', this.focusMessage)
		EventBus.$off('route-change', this.onRouteChange)

		this.$store.dispatch('cancelLookForNewMessages', { requestId: this.chatIdentifier })
		this.destroying = true

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
		 * @param {string} message1.actorDisplayName Actor displayname of the new message
		 * @param {string} message1.systemMessage System message content of the new message
		 * @param {number} message1.timestamp Timestamp of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.id The ID of the second message
		 * @param {string} message2.actorType Actor type of the previous message
		 * @param {string} message2.actorId Actor id of the previous message
		 * @param {string} message2.actorDisplayName Actor display name of previous message
		 * @param {string} message2.systemMessage System message content of the previous message
		 * @param {number} message2.timestamp Timestamp of the second message
		 * @return {boolean} Boolean if the messages should be grouped or not
		 */
		messagesShouldBeGrouped(message1, message2) {
			if (!message2) {
				return false // No previous message
			}

			if (message1.actorType === ATTENDEE.ACTOR_TYPE_BOTS // Don't group messages of commands and bots
				&& message1.actorId !== ATTENDEE.CHANGELOG_BOT_ID) { // Apart from the changelog bot
				return false
			}

			const message1IsSystem = message1.systemMessage.length !== 0
			const message2IsSystem = message2.systemMessage.length !== 0

			if (message1IsSystem !== message2IsSystem) {
				// Only group system messages with each others
				return false
			}

			if (!message1IsSystem // System messages are grouped independent from author
				&& ((message1.actorType !== message2.actorType // Otherwise the type and id need to match
					|| message1.actorId !== message2.actorId)
				|| (message1.actorType === ATTENDEE.ACTOR_TYPE.BRIDGED // Or, if the message is bridged, display names also need to match
					&& message1.actorDisplayName !== message2.actorDisplayName))) {
				return false
			}

			return !this.messagesHaveDifferentDate(message1, message2) // Posted on the same day
		},

		/**
		 * Check if 2 messages are from the same date
		 *
		 * @param {object} message1 The new message
		 * @param {string} message1.id The ID of the new message
		 * @param {number} message1.timestamp Timestamp of the new message
		 * @param {null|object} message2 The previous message
		 * @param {string} message2.id The ID of the second message
		 * @param {number} message2.timestamp Timestamp of the second message
		 * @return {boolean} Boolean if the messages have the same date
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
		 * @param {number} message.timestamp Timestamp of the message
		 * @return {string} Translated string of "<Today>, <November 11th, 2019>", "<3 days ago>, <November 8th, 2019>"
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
		 * @param {number} message.timestamp Timestamp of the message
		 * @return {object} MomentJS object
		 */
		getDateOfMessage(message) {
			if (message.id.toString().startsWith('temp-')) {
				return moment()
			}
			return moment.unix(message.timestamp)
		},

		scrollToFocussedMessage() {
			let focussed = null
			if (this.$route?.hash?.startsWith('#message_')) {
				// scroll to message in URL anchor
				focussed = this.focusMessage(this.$route.hash.substr(9), false)
			}

			if (!focussed && this.visualLastReadMessageId) {
				// scroll to last read message if visible in the current pages
				focussed = this.focusMessage(this.visualLastReadMessageId, false, false)
			}

			// TODO: in case the element is not in a page but does exist in the DB,
			// we need to scroll up / down to the page where it would exist after
			// loading said pages

			if (!focussed) {
				// if no anchor was present or the message to focus on did not exist,
				// scroll to bottom
				this.scrollToBottom()
			}

			// if no scrollbars, clear read marker directly as scrolling is not possible for the user to clear it
			// also clear in case lastReadMessage is zero which is due to an older bug
			if (this.visualLastReadMessageId === 0 || this.scroller.scrollHeight <= this.scroller.offsetHeight) {
				// clear after a delay, unless scrolling can resume in-between
				this.debounceUpdateReadMarkerPosition()
			}
		},

		async handleStartGettingMessagesPreconditions() {
			if (this.token && this.isParticipant && !this.isInLobby) {

				// prevent sticky mode before we have loaded anything
				this.setChatScrolledToBottom(false)

				this.$store.dispatch('setVisualLastReadMessageId', {
					token: this.token,
					id: this.conversation.lastReadMessage,
				})

				if (this.$store.getters.getFirstKnownMessageId(this.token) === null) {
					// first time load, initialize important properties
					this.$store.dispatch('setFirstKnownMessageId', {
						token: this.token,
						id: this.conversation.lastReadMessage,
					})
					this.$store.dispatch('setLastKnownMessageId', {
						token: this.token,
						id: this.conversation.lastReadMessage,
					})

					// get history before last read message
					await this.getOldMessages(true)
					// at this stage, the read marker will appear at the bottom of the view port since
					// we haven't fetched the messages that come after it yet
					// TODO: should we still show a spinner at this stage ?
				}

				let hasScrolled = false
				// if lookForNewMessages will long poll instead of returning existing messages,
				// scroll right away to avoid delays
				if (!this.$store.getters.hasMoreMessagesToLoad(this.token)) {
					hasScrolled = true
					this.$nextTick(() => {
						this.scrollToFocussedMessage()
					})
				}

				// get new messages
				await this.lookForNewMessages()

				// don't scroll if lookForNewMessages was polling as we don't want
				// to scroll back to the read marker after receiving new messages later
				if (!hasScrolled) {
					this.scrollToFocussedMessage()
				}
			} else {
				this.$store.dispatch('cancelLookForNewMessages', { requestId: this.chatIdentifier })
			}
		},

		/**
		 * Fetches the messages of a conversation given the conversation token. Triggers
		 * a long-polling request for new messages.
		 */
		async lookForNewMessages() {
			// Once the history is received, starts looking for new messages.
			if (this._isBeingDestroyed || this._isDestroyed) {
				console.debug('Prevent getting new messages on a destroyed MessagesList')
				return
			}

			const followInNewMessages = this.conversation.lastMessage
				&& this.conversation.lastReadMessage === this.conversation.lastMessage.id

			await this.getNewMessages(followInNewMessages)
		},

		/**
		 * Get messages history.
		 *
		 * @param {boolean} includeLastKnown Include or exclude the last known message in the response
		 */
		async getOldMessages(includeLastKnown) {
			// Make the request
			try {
				this.loadingOldMessages = true
				await this.$store.dispatch('fetchMessages', {
					token: this.token,
					lastKnownMessageId: this.$store.getters.getFirstKnownMessageId(this.token),
					includeLastKnown,
				})

				this.loadingOldMessages = false
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
				this.loadingOldMessages = false
			}
		},

		/**
		 * Creates a long polling request for a new message.
		 *
		 * @param {boolean} scrollToBottom Whether we should try to automatically scroll to the bottom
		 */
		async getNewMessages(scrollToBottom = true) {
			if (this.destroying) {
				return
			}

			// Make the request
			try {
				// TODO: move polling logic to the store and also cancel timers on cancel
				this.pollingErrorTimeout = 1
				await this.$store.dispatch('lookForNewMessages', {
					token: this.token,
					lastKnownMessageId: this.$store.getters.getLastKnownMessageId(this.token),
					requestId: this.chatIdentifier,
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

		debounceHandleScroll: debounce(function() {
			this.handleScroll()
		}, 50),

		/**
		 * When the div is scrolled, this method checks if it's been scrolled to the top
		 * or to the bottom of the list bottom.
		 */
		async handleScroll() {
			if (!this.$store.getters.getFirstKnownMessageId(this.token)) {
				// This can happen if the browser is fast enough to close the sidebar
				// when switching from a one-to-one to a group conversation.
				console.debug('Ignoring handleScroll as the messages history is empty')
				return
			}

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
				if (this.loadingOldMessages) {
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

			this.debounceUpdateReadMarkerPosition()
		},

		/**
		 * Finds the last message that is fully visible in the scroller viewport
		 *
		 * Starts searching forward after the given message element until we reach
		 * the bottom of the viewport.
		 *
		 * @param {object} messageEl message element after which to start searching
		 * @return {object} DOM element for the last visible message
		 */
		findFirstVisibleMessage(messageEl) {
			let el = messageEl
			let previousEl = el
			const scrollTop = this.scroller.scrollTop
			while (el) {
				// is the message element fully visible with no intersection with the bottom border ?
				if (el.offsetTop - scrollTop >= 0) {
					// this means that the previous message we had was fully visible,
					// so we return that
					return previousEl
				}

				previousEl = el
				el = document.getElementById('message_' + el.getAttribute('data-next-message-id'))
			}

			return previousEl
		},

		/**
		 * Sync the visual marker position with what is currently in the store.
		 * This separation exists to avoid jumpy marker while scrolling.
		 *
		 * Also see updateReadMarkerPosition() for the backend update.
		 */
		refreshReadMarkerPosition() {
			if (!this.conversation) {
				return
			}
			console.debug('setVisualLastReadMessageId token=' + this.token + ' id=' + this.conversation.lastReadMessage)
			this.$store.dispatch('setVisualLastReadMessageId', {
				token: this.token,
				id: this.conversation.lastReadMessage,
			})
		},

		debounceUpdateReadMarkerPosition: debounce(function() {
			this.updateReadMarkerPosition()
		}, 1000),

		/**
		 * Finds the first visual unread message element
		 *
		 * @return {object} DOM element of the first unread message
		 */
		getUnreadMessageElement() {
			let el = document.getElementById('message_' + this.visualLastReadMessageId)
			if (el) {
				el = el.closest('.message')
			}

			return el
		},

		/**
		 * Recalculates the current read marker position based on the first visible element,
		 * but only do so if the previous marker was already seen.
		 *
		 * The new marker position will be sent to the backend but not applied visually.
		 * Visually, the marker will only move the next time the user is focussing back to this
		 * conversation in refreshReadMarkerPosition()
		 */
		updateReadMarkerPosition() {
			if (!this.conversation) {
				return
			}

			// to fix issues, this scenario should not happen
			if (this.conversation.lastReadMessage === 0) {
				console.debug('clearLastReadMessage because lastReadMessage was 0 token=' + this.token)
				this.$store.dispatch('clearLastReadMessage', { token: this.token, updateVisually: true })
				return
			}

			if (this.conversation.lastReadMessage === this.conversation.lastMessage?.id) {
				// already at bottom, nothing to do
				return
			}

			const unreadMessageElement = this.getUnreadMessageElement()

			// first unread message has not been seen yet, so don't move it
			if (!unreadMessageElement || unreadMessageElement.getAttribute('data-seen') !== 'true') {
				return
			}

			// if we're at bottom of the chat with no more new messages to load, then simply clear the marker
			if (this.isSticky && !this.$store.getters.hasMoreMessagesToLoad(this.token)) {
				console.debug('clearLastReadMessage because of isSticky token=' + this.token)
				this.$store.dispatch('clearLastReadMessage', { token: this.token })
				return
			}

			if (unreadMessageElement.offsetTop - this.scroller.scrollTop > 0) {
				// still visible, hasn't disappeared at the top yet
				return
			}

			const firstVisibleMessage = this.findFirstVisibleMessage(unreadMessageElement)
			if (!firstVisibleMessage) {
				console.warn('First visible message not found: ', firstVisibleMessage)
				return
			}

			const messageId = parseInt(firstVisibleMessage.getAttribute('data-message-id'), 10)
			if (messageId <= this.conversation.lastReadMessage) {
				// it was probably a scroll up, don't update
				return
			}

			// we don't update visually here, it will update the next time the
			// user focusses back on the conversation. See refreshReadMarkerPosition().
			console.debug('updateLastReadMessage token=' + this.token + ' messageId=' + messageId)
			this.$store.dispatch('updateLastReadMessage', { token: this.token, id: messageId, updateVisually: false })
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
				if (this.isWindowVisible && document.hasFocus()) {
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
		 * @param {boolean} highlightAnimation true to highlight and set focus to the message
		 * @return {boolean} true if element was found, false otherwise
		 */
		focusMessage(messageId, smooth = true, highlightAnimation = true) {
			const element = document.getElementById(`message_${messageId}`)
			if (!element) {
				// TODO: in some cases might need to trigger a scroll up if this is an older message
				console.warn('Message to focus not found in DOM', messageId)
				return false
			}

			this.$nextTick(async () => {
				// FIXME: this doesn't wait for the smooth scroll to end
				await element.scrollIntoView({
					behavior: smooth ? 'smooth' : 'auto',
					block: 'center',
					inline: 'nearest',
				})
				if (!smooth) {
					// scroll the viewport slightly further to make sure the element is about 1/3 from the top
					this.scroller.scrollTop += this.scroller.offsetHeight / 4
				}
				if (highlightAnimation) {
					element.focus()
					element.highlightAnimation()
				}
			})

			return true
		},

		/**
		 * gets the last known message id.
		 *
		 * @return {string} The last known message id.
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
		 *
		 * @return {string}
		 */
		getFirstKnownMessageId() {
			return this.messagesList[0].id.toString()
		},

		handleNetworkOffline() {
			console.debug('Canceling message request as we are offline')
			if (this.cancelLookForNewMessages) {
				this.$store.dispatch('cancelLookForNewMessages', { requestId: this.chatIdentifier })
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
			this.$emit('set-chat-scrolled-to-bottom', boolean)
			if (boolean) {
				// mark as read if marker was seen
				// we have to do this early because unfocussing the window will remove the stickiness
				this.debounceUpdateReadMarkerPosition()
			}
		},

		onWindowFocus() {
			this.refreshReadMarkerPosition()
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

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

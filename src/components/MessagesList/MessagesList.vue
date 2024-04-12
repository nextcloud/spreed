<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  - @author Dorra Jaouad <dorra.jaoued1@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<!-- size and remain refer to the amount and initial height of the items that
	are outside of the viewport -->
	<div ref="scroller"
		:key="token"
		class="scroller messages-list__scroller"
		:class="{'scroller--chatScrolledToBottom': isChatScrolledToBottom,
			'scroller--isScrolling': isScrolling}"
		@scroll="onScroll"
		@scrollend="endScroll">
		<TransitionWrapper name="fade">
			<ul v-if="displayMessagesLoader" class="scroller__loading icon-loading" />
		</TransitionWrapper>

		<ul v-for="(list, dateTimestamp) in messagesGroupedByDateByAuthor"
			:key="`section_${dateTimestamp}`"
			:ref="`dateGroup-${token}`"
			:data-date-timestamp="dateTimestamp"
			:class="{'has-sticky': dateTimestamp === stickyDate}">
			<li class="messages-group__date">
				<span class="messages-group__date-text" role="heading" aria-level="3">
					{{ dateSeparatorLabels[dateTimestamp] }}
				</span>
			</li>
			<component :is="messagesGroupComponent(group)"
				v-for="group in list"
				:key="group.id"
				class="messages-group"
				:token="token"
				:messages="group.messages"
				:previous-message-id="group.previousMessageId"
				:next-message-id="group.nextMessageId" />
		</ul>

		<template v-if="showLoadingAnimation">
			<LoadingPlaceholder type="messages"
				class="messages-list__placeholder"
				:count="15" />
		</template>
		<NcEmptyContent v-else-if="showEmptyContent"
			class="messages-list__empty-content"
			:name="t('spreed', 'No messages')"
			:description="t('spreed', 'All messages have expired or have been deleted.')">
			<template #icon>
				<Message :size="64" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import debounce from 'debounce'
import uniqueId from 'lodash/uniqueId.js'

import Message from 'vue-material-design-icons/Message.vue'

import Axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import moment from '@nextcloud/moment'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import MessagesGroup from './MessagesGroup/MessagesGroup.vue'
import MessagesSystemGroup from './MessagesGroup/MessagesSystemGroup.vue'
import LoadingPlaceholder from '../UIShared/LoadingPlaceholder.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { ATTENDEE, CHAT } from '../../constants.js'
import { EventBus } from '../../services/EventBus.js'

export default {
	name: 'MessagesList',
	components: {
		LoadingPlaceholder,
		Message,
		NcEmptyContent,
		TransitionWrapper
	},

	provide() {
		return {
			getMessagesListScroller: () => this.$refs.scroller,
		}
	},

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
			default: true,
		},

		isVisible: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['update:is-chat-scrolled-to-bottom'],

	setup() {
		const isInCall = useIsInCall()
		return {
			isInCall,
		}
	},

	data() {
		return {
			/**
			 * A list of messages grouped by same day and then by author and time.
			 */
			messagesGroupedByDateByAuthor: {},

			viewId: uniqueId('messagesList'),

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

			isInitialisingMessages: false,

			isFocusingMessage: false,

			destroying: false,

			expirationInterval: null,

			debounceUpdateReadMarkerPosition: () => {},

			debounceHandleScroll: () => {},

			stopFetchingOldMessages: false,

			isScrolling: false,

			stickyDate: null,

			dateSeparatorLabels: {},

			endScrollTimeout: () => {},
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

		showLoadingAnimation() {
			return !this.$store.getters.isMessageListPopulated(this.token)
		},

		showEmptyContent() {
			return !this.messagesList.length
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
			return this.isChatScrolledToBottom && !this.isInitialisingMessages
		},

		hasMoreMessagesToLoad() {
			return this.$store.getters.hasMoreMessagesToLoad(this.token)
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

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		chatIdentifier() {
			return this.token + ':' + this.isParticipant + ':' + this.viewId
		},

		currentDay() {
			return moment().startOf('day').unix()
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

				// Remove expired messages when joining a room
				this.removeExpiredMessagesFromStore()
			},
		},

		token(newToken, oldToken) {
			// Expire older messages when navigating to another conversation
			this.$store.dispatch('easeMessageList', { token: oldToken })
			this.stopFetchingOldMessages = false
			this.messagesGroupedByDateByAuthor = this.prepareMessagesGroups(this.messagesList)
		},

		messagesList: {
			immediate: true,
			handler(newMessages, oldMessages) {
				// token watcher will handle the conversations change
				if (oldMessages?.length && newMessages.length && newMessages[0].token !== oldMessages?.at(0)?.token) {
					return
				}
				const newGroups = this.prepareMessagesGroups(newMessages)
				// messages were just loaded
				if (!oldMessages) {
					this.messagesGroupedByDateByAuthor = newGroups
				} else {
					this.softUpdateByDateGroups(this.messagesGroupedByDateByAuthor, newGroups)
				}

				// scroll to bottom if needed
				this.scrollToBottom({ smooth: true })
			},
		},
	},

	mounted() {
		this.debounceUpdateReadMarkerPosition = debounce(this.updateReadMarkerPosition, 1000)
		this.debounceHandleScroll = debounce(this.handleScroll, 50)

		EventBus.on('scroll-chat-to-bottom', this.scrollToBottom)
		EventBus.on('focus-message', this.focusMessage)
		EventBus.on('route-change', this.onRouteChange)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
		window.addEventListener('focus', this.onWindowFocus)

		/**
		 * Every 30 seconds we remove expired messages from the store
		 */
		this.expirationInterval = window.setInterval(() => {
			this.removeExpiredMessagesFromStore()
		}, 30000)
	},

	beforeDestroy() {
		this.debounceUpdateReadMarkerPosition.clear?.()
		this.debounceHandleScroll.clear?.()

		window.removeEventListener('focus', this.onWindowFocus)
		EventBus.off('scroll-chat-to-bottom', this.scrollToBottom)
		EventBus.off('focus-message', this.focusMessage)
		EventBus.off('route-change', this.onRouteChange)

		this.$store.dispatch('cancelLookForNewMessages', { requestId: this.chatIdentifier })
		this.destroying = true

		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)

		if (this.expirationInterval) {
			clearInterval(this.expirationInterval)
			this.expirationInterval = null
		}
	},

	methods: {
		prepareMessagesGroups(messages) {
			let prevGroupMap = null
			const groupsByDate = {}
			let lastMessage = null
			let groupId = null
			let dateTimestamp = null
			for (const message of messages) {
				if (!this.messagesShouldBeGrouped(message, lastMessage)) {
					groupId = message.id
					if (message.timestamp === 0) {
						// This is a temporary message, the timestamp is today
						dateTimestamp = this.currentDay
					} else {
						dateTimestamp = moment(message.timestamp * 1000).startOf('day').unix()
					}

					if (!this.dateSeparatorLabels[dateTimestamp]) {
						this.dateSeparatorLabels[dateTimestamp] = this.generateDateSeparator(dateTimestamp)
					}

					if (!groupsByDate[dateTimestamp]) {
						groupsByDate[dateTimestamp] = {}
					}

					groupsByDate[dateTimestamp][groupId] = {
						id: message.id,
						messages: [message],
						token: this.token,
						dateTimestamp,
						previousMessageId: lastMessage?.id || 0,
						nextMessageId: 0,
						isSystemMessagesGroup: message.systemMessage.length !== 0,
					}

					// Update the previous group with the next message id
					if (prevGroupMap) {
						groupsByDate[prevGroupMap.date][prevGroupMap.groupId].nextMessageId = message.id
					}

					// Update the previous group map points
					prevGroupMap = {
						date: dateTimestamp,
						groupId: message.id,
					}
				} else {
					// Group is the same, so we just append the message to the array of messages
					groupsByDate[prevGroupMap.date][prevGroupMap.groupId].messages.push(message)
				}
				lastMessage = message
			}
			return groupsByDate
		},

		softUpdateByDateGroups(oldDateGroups, newDateGroups) {
			const dateTimestamps = new Set([...Object.keys(oldDateGroups), ...Object.keys(newDateGroups)])

			dateTimestamps.forEach(dateTimestamp => {
				if (newDateGroups[dateTimestamp]) {
					if (oldDateGroups[dateTimestamp]) {
						// the group by date has changed, we update its content (groups by author)
						this.softUpdateAuthorGroups(oldDateGroups[dateTimestamp], newDateGroups[dateTimestamp], dateTimestamp)
					} else {
						// the group is new
						this.messagesGroupedByDateByAuthor[dateTimestamp] = newDateGroups[dateTimestamp]
					}
				} else {
					// the group is not in the new list, remove it
					delete this.messagesGroupedByDateByAuthor[dateTimestamp]
				}
			})
		},

		softUpdateAuthorGroups(oldGroups, newGroups, dateTimestamp) {
			Object.entries(newGroups).forEach(([id, newGroup]) => {
				if (!oldGroups[id]) {
					const oldId = Object.keys(oldGroups)
						.find(key => id < key && oldGroups[key].nextMessageId <= newGroup.nextMessageId)
					if (oldId) {
						// newGroup includes oldGroup and more old messages, remove oldGroup
						delete this.messagesGroupedByDateByAuthor[dateTimestamp][oldId]
					}
					// newGroup is not presented in the list, add it
					this.messagesGroupedByDateByAuthor[dateTimestamp][id] = newGroup
				} else if (!this.areGroupsIdentical(newGroup, oldGroups[id])) {
					// newGroup includes oldGroup and more recent messages
					this.messagesGroupedByDateByAuthor[dateTimestamp][id] = newGroup
				}
			})

			// Remove temporary messages that are not in the new list
			if (+dateTimestamp === this.currentDay) {
				const newGroupsMap = new Map(Object.entries(newGroups))
				for (const id of Object.keys(oldGroups).reverse()) {
					if (!id.toString().startsWith('temp-')) {
						break
					}
					if (!newGroupsMap.has(id)) {
						delete this.messagesGroupedByDateByAuthor[dateTimestamp][id]
					}
				}
			}
		},

		areGroupsIdentical(group1, group2) {
			if (group1.messages.length !== group2.messages.length
				|| JSON.stringify(group1.messages) !== JSON.stringify(group2.messages)
				|| group1.dateSeparator !== group2.dateSeparator
				|| group1.previousMessageId !== group2.previousMessageId
				|| group1.nextMessageId !== group2.nextMessageId) {
				return false
			}

			// Check for temporary messages, replaced with messages from server
			return group1.messages.every((message, index) => group2.messages[index].id === message.id)
		},

		removeExpiredMessagesFromStore() {
			this.$store.dispatch('removeExpiredMessages', {
				token: this.token,
			})
		},

		/**
		 * Compare two messages to decide if they should be grouped
		 *
		 * @param {object} message1 The new message
		 * @param {string} message1.id The ID of the new message
		 * @param {string} message1.actorType Actor type of the new message
		 * @param {string} message1.actorId Actor id of the new message
		 * @param {string} message1.actorDisplayName Actor display name of the new message
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

			if (!!message1.lastEditTimestamp || !!message2.lastEditTimestamp) {
				return false // Edited messages are not grouped
			}

			if (message1.actorType === ATTENDEE.ACTOR_TYPE.BOTS // Don't group messages of bots
				&& message1.actorId !== ATTENDEE.CHANGELOG_BOT_ID) { // Apart from the changelog bot
				return false
			}

			const message1IsSystem = message1.systemMessage.length !== 0
			const message2IsSystem = message2.systemMessage.length !== 0

			if (message1IsSystem !== message2IsSystem) {
				// Only group system messages with each others
				return false
			}

			if (!message1IsSystem // System messages are grouped independently of author
				&& ((message1.actorType !== message2.actorType // Otherwise the type and id need to match
						|| message1.actorId !== message2.actorId)
					|| (message1.actorType === ATTENDEE.ACTOR_TYPE.BRIDGED // Or, if the message is bridged, display names also need to match
						&& message1.actorDisplayName !== message2.actorDisplayName))) {
				return false
			}

			if (this.messagesHaveDifferentDate(message1, message2)) {
				// Not posted on the same day
				return false
			}

			// Only group messages within a short period of time (5 minutes), so unrelated messages are not grouped together
			return this.getDateOfMessage(message1).diff(this.getDateOfMessage(message2)) < 300 * 1000
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

		getRelativePrefix(date, diffDays) {
			switch (diffDays) {
			case 0:
				return t('spreed', 'Today')
			case 1:
				return t('spreed', 'Yesterday')
			case 7:
				return t('spreed', 'A week ago')
			default:
				return n('spreed', '%n day ago', '%n days ago', diffDays)
			}
		},

		/**
		 * Generate the date header between the messages
		 *
		 * @param {number} dateTimestamp The day and year timestamp
		 * @return {string} Translated string of "<Today>, <November 11th, 2019>", "<3 days ago>, <November 8th, 2019>"
		 */
		generateDateSeparator(dateTimestamp) {
			const date = moment.unix(dateTimestamp).startOf('day')
			const diffDays = moment().startOf('day').diff(date, 'days')
			// Relative date is only shown up to a week ago (inclusive)
			if (diffDays <= 7) {
				// TRANSLATORS: <Today>, <March 18th, 2024>
				return t('spreed', '{relativeDate}, {absoluteDate}', {
					relativeDate: this.getRelativePrefix(date, diffDays),
					// 'LL' formats a localized date including day of month, month
					// name and year
					absoluteDate: date.format('LL'),
				}, undefined, {
					escape: false, // French "Today" has a ' in it
				})
			} else {
				// TRANSLATORS: <March 18th, 2024>
				return t('spreed', '{absoluteDate}', { absoluteDate: date.format('LL') })
			}

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

		getMessageIdFromHash(hash = undefined) {
			if (hash) {
				return parseInt(hash.slice(9), 10)
			} else if (this.$route?.hash?.startsWith('#message_')) {
				return parseInt(this.$route.hash.slice(9), 10)
			}
			return null
		},

		scrollToFocusedMessage(focusMessageId) {
			let isFocused = null
			if (focusMessageId) {
				// scroll to message in URL anchor
				isFocused = this.focusMessage(focusMessageId, false)
			}

			if (!isFocused && this.visualLastReadMessageId) {
				// scroll to last read message if visible in the current pages
				isFocused = this.focusMessage(this.visualLastReadMessageId, false, false)
			}

			// TODO: in case the element is not in a page but does exist in the DB,
			// we need to scroll up / down to the page where it would exist after
			// loading said pages

			if (!isFocused) {
				// if no anchor was present or the message to focus on did not exist,
				// scroll to bottom
				this.scrollToBottom({ force: true })
			}

			// if no scrollbars, clear read marker directly as scrolling is not possible for the user to clear it
			// also clear in case lastReadMessage is zero which is due to an older bug
			if (this.visualLastReadMessageId === 0
				|| (this.$refs.scroller && this.$refs.scroller.scrollHeight <= this.$refs.scroller.offsetHeight)) {
				// clear after a delay, unless scrolling can resume in-between
				this.debounceUpdateReadMarkerPosition()
			}
		},

		async handleStartGettingMessagesPreconditions() {
			if (this.token && this.isParticipant && !this.isInLobby) {

				// prevent sticky mode before we have loaded anything
				this.isInitialisingMessages = true
				const focusMessageId = this.getMessageIdFromHash()

				this.$store.dispatch('setVisualLastReadMessageId', {
					token: this.token,
					id: this.conversation.lastReadMessage,
				})

				if (this.$store.getters.getFirstKnownMessageId(this.token) === null) {
					let startingMessageId = 0
					// first time load, initialize important properties
					if (focusMessageId === null) {
						// Start from unread marker
						this.$store.dispatch('setFirstKnownMessageId', {
							token: this.token,
							id: this.conversation.lastReadMessage,
						})
						startingMessageId = this.conversation.lastReadMessage
						this.$store.dispatch('setLastKnownMessageId', {
							token: this.token,
							id: this.conversation.lastReadMessage,
						})
					} else {
						// Start from message hash
						this.$store.dispatch('setFirstKnownMessageId', {
							token: this.token,
							id: focusMessageId,
						})
						startingMessageId = focusMessageId
						this.$store.dispatch('setLastKnownMessageId', {
							token: this.token,
							id: focusMessageId,
						})
					}

					// Get chat messages before last read message and after it
					await this.getMessageContext(startingMessageId)
					const startingMessageFound = this.focusMessage(startingMessageId, false, focusMessageId !== null)

					if (!startingMessageFound) {
						const fallbackStartingMessageId = this.$store.getters.getFirstDisplayableMessageIdBeforeReadMarker(this.token, startingMessageId)
						this.$store.dispatch('setVisualLastReadMessageId', {
							token: this.token,
							id: fallbackStartingMessageId,
						})
						this.focusMessage(fallbackStartingMessageId, false, false)
					}
				}

				let hasScrolled = false
				if (focusMessageId === null) {
					// if lookForNewMessages will long poll instead of returning existing messages,
					// scroll right away to avoid delays
					if (!this.hasMoreMessagesToLoad) {
						hasScrolled = true
						this.$nextTick(() => {
							this.scrollToFocusedMessage(focusMessageId)
						})
					}
				}

				this.isInitialisingMessages = false

				// get new messages
				await this.lookForNewMessages()

				if (focusMessageId === null) {
					// don't scroll if lookForNewMessages was polling as we don't want
					// to scroll back to the read marker after receiving new messages later
					if (!hasScrolled) {
						this.scrollToFocusedMessage(focusMessageId)
					}
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

			await this.getNewMessages()
		},

		async getMessageContext(messageId) {
			// Make the request
			this.loadingOldMessages = true
			try {
				await this.$store.dispatch('getMessageContext', {
					token: this.token,
					messageId,
					minimumVisible: CHAT.MINIMUM_VISIBLE,
				})
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
			}
			this.loadingOldMessages = false
		},

		/**
		 * Get messages history.
		 *
		 * @param {boolean} includeLastKnown Include or exclude the last known message in the response
		 */
		async getOldMessages(includeLastKnown) {
			// Make the request
			this.loadingOldMessages = true
			try {
				await this.$store.dispatch('fetchMessages', {
					token: this.token,
					lastKnownMessageId: this.$store.getters.getFirstKnownMessageId(this.token),
					includeLastKnown,
					minimumVisible: CHAT.MINIMUM_VISIBLE,
				})
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
				}
				if (exception?.response?.status === 304) {
					// 304 - Not modified
					this.stopFetchingOldMessages = true
				}
			}
			this.loadingOldMessages = false

			if (!this.stopFetchingOldMessages) {
				// Stop fetching old messages, if this is the beginning of the chat
				const firstMessage = this.messagesList?.at(0)
				const ChatBeginFlag = firstMessage?.messageType === 'system'
					&& ['conversation_created', 'history_cleared'].includes(firstMessage.systemMessage)
				if (ChatBeginFlag) {
					this.stopFetchingOldMessages = true
				}
			}
		},

		/**
		 * Creates a long polling request for a new message.
		 *
		 */
		async getNewMessages() {
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
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
					return
				}

				if (exception?.response?.status === 304) {
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

		checkSticky() {
			const ulElements = this.$refs['dateGroup-' + this.token]
			if (!ulElements) {
				return
			}

			const scrollerRect = this.$refs.scroller.getBoundingClientRect()
			ulElements.forEach((element) => {
				const rect = element.getBoundingClientRect()
				if (rect.top <= scrollerRect.top && rect.bottom >= scrollerRect.top) {
					this.stickyDate = element.getAttribute('data-date-timestamp')
				}
			})
		},

		onScroll() {
			// handle scrolling status
			if (this.isScrolling) {
				clearTimeout(this.endScrollTimeout)
			}
			this.isScrolling = true
			this.endScrollTimeout = setTimeout(this.endScroll, 3000)
			// handle sticky date
			if (this.$refs.scroller.scrollTop === 0) {
				this.stickyDate = null
			} else {
				this.checkSticky()
			}
			// handle scroll event
			this.debounceHandleScroll()
		},

		/**
		 * When the div is scrolled, this method checks if it's been scrolled to the top
		 * or to the bottom of the list bottom.
		 */
		async handleScroll() {
			if (!this.$refs.scroller) {
				return
			}

			if (!this.$store.getters.getFirstKnownMessageId(this.token)) {
				// This can happen if the browser is fast enough to close the sidebar
				// when switching from a one-to-one to a group conversation.
				console.debug('Ignoring handleScroll as the messages history is empty')
				return
			}

			if (this.isInitialisingMessages) {
				console.debug('Ignore handleScroll as we are initialising the message history')
				return
			}

			if (this.isFocusingMessage) {
				console.debug('Ignore handleScroll as we are programmatically scrolling to focus a message')
				return
			}

			const { scrollHeight, scrollTop, clientHeight } = this.$refs.scroller
			const scrollOffset = scrollHeight - scrollTop
			const tolerance = 10

			// For chats, scrolled to bottom or / and fitted in one screen
			if (scrollOffset < clientHeight + tolerance && scrollOffset > clientHeight - tolerance && !this.hasMoreMessagesToLoad) {
				this.setChatScrolledToBottom(true)
				this.displayMessagesLoader = false
				this.previousScrollTopValue = scrollTop
				this.debounceUpdateReadMarkerPosition()
				return
			}

			this.setChatScrolledToBottom(false)

			if (scrollHeight > clientHeight && scrollTop < 800 && scrollTop < this.previousScrollTopValue) {
				if (this.loadingOldMessages || this.stopFetchingOldMessages) {
					// already loading, don't do it twice
					return
				}
				this.displayMessagesLoader = true
				await this.getOldMessages(false)
				this.displayMessagesLoader = false

				if (this.$refs.scroller.scrollHeight !== scrollHeight) {
					// scroll to previous position + added height - loading spinner height
					this.$refs.scroller.scrollTo({
						top: scrollTop + (this.$refs.scroller.scrollHeight - scrollHeight) - 40,
					})
				}
			}

			this.previousScrollTopValue = this.$refs.scroller.scrollTop
			this.debounceUpdateReadMarkerPosition()
		},

		endScroll() {
			this.isScrolling = false
			clearTimeout(this.endScrollTimeout)
		},

		/**
		 * Finds the last message that is fully visible in the scroller viewport
		 *
		 * Starts searching forward after the given message element until we reach
		 * the bottom of the viewport.
		 *
		 * @param {object} messageEl message element after which to start searching
		 * @return {object|undefined} DOM element for the last visible message
		 */
		findFirstVisibleMessage(messageEl) {
			if (!this.$refs.scroller) {
				return
			}

			let el = messageEl

			// When the current message is not visible (reaction or expired)
			// we use the next message from the list start the scroller-visibility check
			if (!el) {
				const messageId = this.$store.getters.getFirstDisplayableMessageIdAfterReadMarker(this.token, this.conversation.lastReadMessage)
				el = document.getElementById('message_' + messageId)
			}
			let previousEl = el

			const { scrollTop } = this.$refs.scroller
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

		/**
		 * Finds the last visual read message element
		 *
		 * @return {object} DOM element of the last read message
		 */
		getVisualLastReadMessageElement() {
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
		 * Visually, the marker will only move the next time the user is focusing back to this
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

			const lastReadMessageElement = this.getVisualLastReadMessageElement()

			// first unread message has not been seen yet, so don't move it
			if (lastReadMessageElement && lastReadMessageElement.getAttribute('data-seen') !== 'true') {
				return
			}

			// if we're at bottom of the chat with no more new messages to load, then simply clear the marker
			if (this.isSticky && !this.hasMoreMessagesToLoad) {
				console.debug('clearLastReadMessage because of isSticky token=' + this.token)
				this.$store.dispatch('clearLastReadMessage', { token: this.token })
				return
			}

			if (lastReadMessageElement && this.$refs.scroller
				&& (lastReadMessageElement.offsetTop - this.$refs.scroller.scrollTop > 0)) {
				// still visible, hasn't disappeared at the top yet
				return
			}

			const firstVisibleMessage = this.findFirstVisibleMessage(lastReadMessageElement)
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
			// user focuses back on the conversation. See refreshReadMarkerPosition().
			console.debug('updateLastReadMessage token=' + this.token + ' messageId=' + messageId)
			this.$store.dispatch('updateLastReadMessage', { token: this.token, id: messageId, updateVisually: false })
		},

		/**
		 * Scrolls to the bottom of the list.
		 * @param {object} options Options for scrolling
		 * @param {boolean} [options.smooth] 'smooth' scrolling to the bottom ('auto' by default)
		 * @param {boolean} [options.force] force scrolling to the bottom (otherwise check for current position)
		 */
		scrollToBottom(options = {}) {
			this.$nextTick(() => {
				if (!this.$refs.scroller) {
					return
				}

				let newTop
				if (options?.force) {
					newTop = this.$refs.scroller.scrollHeight
					this.setChatScrolledToBottom(true)
				} else if (!this.isSticky) {
					// Reading old messages
					return
				} else if (!this.isWindowVisible) {
					const firstUnreadMessageHeight = this.$refs.scroller.scrollHeight - this.$refs.scroller.scrollTop - this.$refs.scroller.offsetHeight
					const scrollBy = firstUnreadMessageHeight < 40 ? 10 : 40
					// We jump half a message and stop autoscrolling, so the user can read up
					// Single new line from the previous author is 35px so scroll half a line (10px)
					// Single new line from the new author is 75px so scroll half an avatar (40px)
					newTop = this.$refs.scroller.scrollTop + scrollBy
					this.setChatScrolledToBottom(false)
				} else {
					newTop = this.$refs.scroller.scrollHeight
					this.setChatScrolledToBottom(true)
				}

				this.$refs.scroller.scrollTo({
					top: newTop,
					behavior: options?.smooth ? 'smooth' : 'auto',
				})
			})
		},

		/**
		 * Temporarily highlight the given message id with a fade out effect.
		 *
		 * @param {number} messageId message id
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

			console.debug('Scrolling to a focused message programmatically')
			this.isFocusingMessage = true

			this.$nextTick(async () => {
				// FIXME: this doesn't wait for the smooth scroll to end
				element.scrollIntoView({
					behavior: smooth ? 'smooth' : 'auto',
					block: 'center',
					inline: 'nearest',
				})
				if (this.$refs.scroller && !smooth) {
					// scroll the viewport slightly further to make sure the element is about 1/3 from the top
					this.$refs.scroller.scrollTop += this.$refs.scroller.offsetHeight / 4
				}
				if (highlightAnimation) {
					EventBus.emit('highlight-message', messageId)
				}
				this.isFocusingMessage = false
				await this.handleScroll()
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

		async onRouteChange({ from, to }) {
			if (from.name === 'conversation'
				&& to.name === 'conversation'
				&& from.token === to.token
				&& from.hash !== to.hash) {

				// the hash changed, need to focus/highlight another message
				if (to.hash && to.hash.startsWith('#message_')) {
					const focusedId = this.getMessageIdFromHash(to.hash)
					if (this.messagesList.find(m => m.id === focusedId)) {
						// need some delay (next tick is too short) to be able to run
						// after the browser's native "scroll to anchor" from
						// the hash
						window.setTimeout(() => {
							// scroll to message in URL anchor
							this.focusMessage(focusedId, true)
						}, 2)
					} else {
						// Update environment around context to fill the gaps
						this.$store.dispatch('setFirstKnownMessageId', {
							token: this.token,
							id: focusedId,
						})
						this.$store.dispatch('setLastKnownMessageId', {
							token: this.token,
							id: focusedId,
						})
						await this.getMessageContext(focusedId)
						this.focusMessage(focusedId, true)
					}
				}
			}
		},

		setChatScrolledToBottom(boolean) {
			this.$emit('update:is-chat-scrolled-to-bottom', boolean)
			if (boolean) {
				// mark as read if marker was seen
				// we have to do this early because unfocusing the window will remove the stickiness
				this.debounceUpdateReadMarkerPosition()
			}
		},

		onWindowFocus() {
			this.refreshReadMarkerPosition()
		},

		messagesGroupComponent(group) {
			return group.isSystemMessagesGroup ? MessagesSystemGroup : MessagesGroup
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.scroller {
	position: relative;
	flex: 1 0;
	padding-top: 20px;
	overflow-y: scroll;
	overflow-x: hidden;
	border-bottom: 1px solid var(--color-border);
	transition: $transition;

	&--chatScrolledToBottom {
		border-bottom-color: transparent;
	}

	&__loading {
		height: 40px;
		transform: translatex(-64px);
	}
}

.messages-list {
	&__placeholder {
		display: flex;
		flex-direction: column-reverse;
		overflow: hidden;
		height: 100%;
	}

	&__empty-content {
		height: 100%;
	}
}

.messages-group {
	&__date {
		position: sticky;
		top: 0;
		display: flex;
		justify-content: center;
		z-index: 2;
		margin-bottom: 5px;
	}

	&__date-text {
		margin-right: calc(var(--default-clickable-area) * 2);
		content: attr(data-date);
		padding: 4px 12px;
		left: 50%;
		color: var(--color-text-maxcontrast);
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius-pill);
	}

	&:last-child {
		margin-bottom: 16px;
	}
}

.has-sticky .messages-group__date {
	transition: opacity 0.3s ease-in-out;
	transition-delay: 2s;
	opacity: 0;
}

.scroller--isScrolling .has-sticky .messages-group__date {
	opacity: 1;
	transition: opacity 0s;
}
</style>

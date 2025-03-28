<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- size and remain refer to the amount and initial height of the items that
	are outside of the viewport -->
	<div ref="scroller"
		class="scroller messages-list__scroller"
		:class="{'scroller--chatScrolledToBottom': isChatScrolledToBottom,
			'scroller--isScrolling': isScrolling}"
		@scroll="onScroll"
		@scrollend="endScroll">
		<TransitionWrapper name="fade">
			<div class="scroller__loading">
				<NcLoadingIcon v-if="displayMessagesLoader" class="scroller__loading-element" :size="32" />
			</div>
		</TransitionWrapper>

		<ul v-for="(list, dateTimestamp) in messagesGroupedByDateByAuthor"
			:key="`section_${dateTimestamp}`"
			:ref="`dateGroup-${token}`"
			:data-date-timestamp="dateTimestamp"
			class="scroller__content"
			:class="{ 'has-sticky': dateTimestamp === stickyDate }">
			<li :key="dateSeparatorLabels[dateTimestamp]" class="messages-group__date">
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

		<template v-if="!isMessagesListPopulated">
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
import { computed } from 'vue'

import Message from 'vue-material-design-icons/Message.vue'

import Axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t, n } from '@nextcloud/l10n'

import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import MessagesGroup from './MessagesGroup/MessagesGroup.vue'
import MessagesSystemGroup from './MessagesGroup/MessagesSystemGroup.vue'
import LoadingPlaceholder from '../UIShared/LoadingPlaceholder.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { useDocumentVisibility } from '../../composables/useDocumentVisibility.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { ATTENDEE, CHAT, CONVERSATION, MESSAGE } from '../../constants.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useChatExtrasStore } from '../../stores/chatExtras.js'
import { debugTimer } from '../../utils/debugTimer.ts'
import { convertToUnix, formatRelativeTime } from '../../utils/formattedTime.ts'

const SCROLL_TOLERANCE = 10

export default {
	name: 'MessagesList',
	components: {
		LoadingPlaceholder,
		Message,
		NcEmptyContent,
		NcLoadingIcon,
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

	setup(props) {
		const isDocumentVisible = useDocumentVisibility()
		const isChatVisible = computed(() => isDocumentVisible.value && props.isVisible)
		return {
			isInCall: useIsInCall(),
			chatExtrasStore: useChatExtrasStore(),
			isChatVisible,
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

			isScrolling: null,

			stickyDate: null,

			dateSeparatorLabels: {},

			endScrollTimeout: () => {},
		}
	},

	computed: {
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

		isMessagesListPopulated() {
			return this.$store.getters.isMessagesListPopulated(this.token)
		},

		chatLoadedIdentifier() {
			return this.token + ':' + this.isMessagesListPopulated
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

			return !!this.$store.getters.findParticipant(this.token, this.conversation)?.attendeeId
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
			return convertToUnix(new Date().setHours(0, 0, 0, 0))
		},

		isChatBeginningReached() {
			return this.stopFetchingOldMessages || (this.messagesList?.[0]?.messageType === 'system'
				&& ['conversation_created', 'history_cleared'].includes(this.messagesList[0].systemMessage))
		}
	},

	watch: {
		isChatVisible(visible) {
			if (visible) {
				this.onWindowFocus()
			}
		},
		chatIdentifier: {
			immediate: true,
			handler(newValue, oldValue) {
				if (oldValue) {
					this.$store.dispatch('cancelPollNewMessages', { requestId: oldValue })
				}
				this.handleStartGettingMessagesPreconditions(this.token)

				// Remove expired messages when joining a room
				this.removeExpiredMessagesFromStore()
			},
		},

		token(newToken, oldToken) {
			// Expire older messages when navigating to another conversation
			this.$store.dispatch('easeMessageList', { token: oldToken })
		},

		messagesList: {
			immediate: true,
			handler(newMessages, oldMessages) {
				const newGroups = this.prepareMessagesGroups(newMessages)
				if (!oldMessages || (oldMessages?.length && newMessages.length && newMessages[0].token !== oldMessages?.at(0)?.token)) {
					// messages were just loaded or token has changed, reset the messages
					this.messagesGroupedByDateByAuthor = newGroups
				} else {
					this.softUpdateByDateGroups(this.messagesGroupedByDateByAuthor, newGroups)
				}

				// scroll to bottom if needed
				this.scrollToBottom({ smooth: false })

				this.$nextTick(() => {
					this.checkChatNotScrollable()

					if (this.conversation?.type === CONVERSATION.TYPE.NOTE_TO_SELF) {
						this.updateTasksCount()
					}
				})

			},
		},

		chatLoadedIdentifier() {
			// resetting to default values
			this.stickyDate = null
			this.stopFetchingOldMessages = false
			if (this.$refs.scroller) {
				this.$refs.scroller.removeEventListener('wheel', this.handleWheelEvent)
			}

			if (this.isMessagesListPopulated) {
				this.$nextTick(() => {
					this.checkSticky()
					// setting wheel event for non-scrollable chat
					if (!this.isChatBeginningReached && this.checkChatNotScrollable()) {
						this.$refs.scroller.addEventListener('wheel', this.handleWheelEvent, { passive: true })
					}
				})
			}
		},
	},

	mounted() {
		this.debounceUpdateReadMarkerPosition = debounce(this.updateReadMarkerPosition, 1000)
		this.debounceHandleScroll = debounce(this.handleScroll, 50)

		EventBus.on('scroll-chat-to-bottom', this.scrollToBottom)
		EventBus.on('focus-message', this.focusMessage)
		EventBus.on('route-change', this.onRouteChange)
		EventBus.on('message-height-changed', this.onMessageHeightChanged)
		subscribe('networkOffline', this.handleNetworkOffline)
		subscribe('networkOnline', this.handleNetworkOnline)
		window.addEventListener('focus', this.onWindowFocus)

		this.resizeObserver = new ResizeObserver(this.updateSize)
		this.resizeObserver.observe(this.$refs.scroller)

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
		EventBus.off('message-height-changed', this.onMessageHeightChanged)
		this.$store.dispatch('cancelPollNewMessages', { requestId: this.chatIdentifier })
		this.destroying = true

		unsubscribe('networkOffline', this.handleNetworkOffline)
		unsubscribe('networkOnline', this.handleNetworkOnline)

		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}

		if (this.expirationInterval) {
			clearInterval(this.expirationInterval)
			this.expirationInterval = null
		}
	},

	methods: {
		t,
		n,
		updateSize() {
			if (this.isChatScrolledToBottom) {
				this.$refs.scroller.scrollTo({
					top: this.$refs.scroller.scrollHeight,
				})
			} else {
				this.checkChatNotScrollable()
			}
		},

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
						dateTimestamp = convertToUnix(new Date(message.timestamp * 1000).setHours(0, 0, 0, 0))
					}

					if (!this.dateSeparatorLabels[dateTimestamp]) {
						this.$set(this.dateSeparatorLabels, dateTimestamp, formatRelativeTime(dateTimestamp * 1000, { weekPrefix: 'numeric', weekSuffix: 'LL', omitSameYear: true }))
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
						this.$set(this.messagesGroupedByDateByAuthor, dateTimestamp, newDateGroups[dateTimestamp])
					}
				} else {
					// the group is not in the new list, remove it
					this.$delete(this.messagesGroupedByDateByAuthor, dateTimestamp)
				}
			})
		},

		softUpdateAuthorGroups(oldGroups, newGroups, dateTimestamp) {
			const groupIds = new Set([...Object.keys(oldGroups), ...Object.keys(newGroups)])

			groupIds.forEach(id => {
				if (oldGroups[id] && !newGroups[id]) {
					// group no longer exists, remove
					this.$delete(this.messagesGroupedByDateByAuthor[dateTimestamp], id)
				} else if ((newGroups[id] && !oldGroups[id]) || !this.areGroupsIdentical(newGroups[id], oldGroups[id])) {
					// group did not exist before, or group differs from previous state, add
					this.$set(this.messagesGroupedByDateByAuthor[dateTimestamp], id, newGroups[id])
				}
			})
		},

		areGroupsIdentical(group1, group2) {
			// Compare plain values
			if (group1.messages.length !== group2.messages.length
				|| group1.dateSeparator !== group2.dateSeparator
				|| group1.previousMessageId !== group2.previousMessageId
				|| group1.nextMessageId !== group2.nextMessageId) {
				return false
			}

			// Compare ids and stringified messages (look for temporary, edited, deleted messages, replaced from server)
			return group1.messages.every((message, index) => group2.messages[index].id === message.id
				&& JSON.stringify(group2.messages[index]) === JSON.stringify(message))
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
				&& message1.actorId !== ATTENDEE.CHANGELOG_BOT_ID // Apart from the changelog bot
				&& message1.actorId !== ATTENDEE.SAMPLE_BOT_ID) { // Apart from the sample message
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

			const date1 = this.getDateOfMessage(message1)
			const date2 = this.getDateOfMessage(message2)

			if (date1.getFullYear() !== date2.getFullYear() || date1.getMonth() !== date2.getMonth() || date1.getDate() !== date2.getDate()) {
				// Not posted on the same day
				return false
			}

			// Only group messages within a short period of time (5 minutes), so unrelated messages are not grouped together
			return Math.abs(date1 - date2) < 300000
		},

		/**
		 * Generate the date of the messages
		 *
		 * @param {object} message The message object
		 * @param {string} message.id The ID of the message
		 * @param {number} message.timestamp Timestamp of the message (in UNIX format)
		 * @return {object} Date object
		 */
		getDateOfMessage(message) {
			if (message.id.toString().startsWith('temp-')) {
				return new Date()
			}
			return new Date(message.timestamp * 1000)
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
				this.focusMessage(focusMessageId, false)
				return
			}

			if (this.visualLastReadMessageId) {
				// scroll to last read message if visible in the current pages
				isFocused = this.focusMessage(this.visualLastReadMessageId, false, false)
			}

			if (!isFocused) {
				// Safeguard 1: scroll to first visible message before the read marker
				const fallbackLastReadMessageId = this.$store.getters.getFirstDisplayableMessageIdBeforeReadMarker(this.token, this.visualLastReadMessageId)
				if (fallbackLastReadMessageId) {
					isFocused = this.focusMessage(fallbackLastReadMessageId, false, false)
				}

				if (!isFocused) {
					// Safeguard 2: in case the fallback message is not found too
					// scroll to bottom
					this.scrollToBottom({ smooth: false, force: true })
				} else {
					this.$store.dispatch('setVisualLastReadMessageId', {
						token: this.token,
						id: fallbackLastReadMessageId,
					})
				}
			}

			// Update read marker in all cases except when the message is from URL anchor
			this.debounceUpdateReadMarkerPosition()
		},

		async handleStartGettingMessagesPreconditions(token) {
			if (token && this.isParticipant && !this.isInLobby) {
				// prevent sticky mode before we have loaded anything
				this.isInitialisingMessages = true
				const focusMessageId = this.getMessageIdFromHash()

				this.$store.dispatch('setVisualLastReadMessageId', { token, id: this.conversation.lastReadMessage })

				if (!this.$store.getters.getFirstKnownMessageId(token)) {
					try {
						// Start from message hash or unread marker
						const startingMessageId = focusMessageId !== null ? focusMessageId : this.conversation.lastReadMessage
						// First time load, initialize important properties
						if (!startingMessageId) {
							throw new Error(`[DEBUG] spreed: context message ID is ${startingMessageId}`)
						}
						this.$store.dispatch('setFirstKnownMessageId', { token, id: startingMessageId })
						this.$store.dispatch('setLastKnownMessageId', { token, id: startingMessageId })
						// If MESSAGE.CHAT_BEGIN_ID we need to get the context from the beginning
						// using 0 as the API does not support negative values
						// Get chat messages before last read message and after it
						await this.getMessageContext(token, startingMessageId !== MESSAGE.CHAT_BEGIN_ID ? startingMessageId : 0)
					} catch (exception) {
						console.debug(exception)
						// Request was cancelled, stop getting preconditions and restore initial state
						this.$store.dispatch('setFirstKnownMessageId', { token, id: null })
						this.$store.dispatch('setLastKnownMessageId', { token, id: null })
						return
					}
				}

				this.$nextTick(() => {
					// basically scrolling to either the last read message or the message in the URL anchor
					// and there is a fallback to scroll to the bottom if the message is not found
					this.scrollToFocusedMessage(focusMessageId)
				})

				this.isInitialisingMessages = false

				// Once the history is received, starts looking for new messages.
				await this.pollNewMessages(token)

			} else {
				this.$store.dispatch('cancelPollNewMessages', { requestId: this.chatIdentifier })
			}
		},

		async getMessageContext(token, messageId) {
			// Make the request
			this.loadingOldMessages = true
			try {
				debugTimer.start(`${token} | get context`)
				await this.$store.dispatch('getMessageContext', {
					token,
					messageId,
					minimumVisible: CHAT.MINIMUM_VISIBLE,
				})
				debugTimer.end(`${token} | get context`, 'status 200')
				this.loadingOldMessages = false
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					console.debug('The request has been canceled', exception)
					debugTimer.end(`${token} | get context`, 'cancelled')
					this.loadingOldMessages = false
					throw exception
				}

				if (exception?.response?.status === 304 && exception?.response?.data === '') {
					// 304 - Not modified
					// Empty chat, no messages to load
					debugTimer.end(`${token} | get context`, 'status 304')
					this.$store.dispatch('loadedMessagesOfConversation', { token: this.token })
					this.stopFetchingOldMessages = true
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
			if (this.isChatBeginningReached) {
				// Beginning of the chat reached, no more messages to load
				return
			}
			// Make the request
			this.loadingOldMessages = true
			try {
				debugTimer.start(`${this.token} | fetch history`)
				await this.$store.dispatch('fetchMessages', {
					token: this.token,
					lastKnownMessageId: this.$store.getters.getFirstKnownMessageId(this.token),
					includeLastKnown,
					minimumVisible: CHAT.MINIMUM_VISIBLE,
				})
				debugTimer.end(`${this.token} | fetch history`, 'status 200')
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					debugTimer.end(`${this.token} | fetch history`, 'cancelled')
					console.debug('The request has been canceled', exception)
				}
				if (exception?.response?.status === 304) {
					// 304 - Not modified
					debugTimer.end(`${this.token} | fetch history`, 'status 304')
					this.stopFetchingOldMessages = true
				}
			}
			this.loadingOldMessages = false
		},

		/**
		 * Fetches the messages of a conversation given the conversation token.
		 * Creates a long polling request for new messages.
		 * @param token token of conversation where a method was called
		 */
		async pollNewMessages(token) {
			if (this.destroying) {
				console.debug('Prevent polling new messages on MessagesList being destroyed')
				return
			}
			// Check that the token has not changed
			if (this.token !== token) {
				console.debug(`token has changed to ${this.token}, breaking the loop for ${token}`)
				return
			}
			// Make the request
			try {
				debugTimer.start(`${token} | long polling`)
				// TODO: move polling logic to the store and also cancel timers on cancel
				this.pollingErrorTimeout = 1
				await this.$store.dispatch('pollNewMessages', {
					token,
					lastKnownMessageId: this.$store.getters.getLastKnownMessageId(token),
					requestId: this.chatIdentifier,
				})
				debugTimer.end(`${token} | long polling`, 'status 200')
			} catch (exception) {
				if (Axios.isCancel(exception)) {
					debugTimer.end(`${token} | long polling`, 'cancelled')
					console.debug('The request has been canceled', exception)
					return
				}

				if (exception?.response?.status === 304) {
					debugTimer.end(`${token} | long polling`, 'status 304')
					// 304 - Not modified
					// This is not an error, so reset error timeout and poll again
					this.pollingErrorTimeout = 1
					setTimeout(() => {
						this.pollNewMessages(token)
					}, 500)
					return
				}

				if (this.pollingErrorTimeout < 30) {
					// Delay longer after each error
					this.pollingErrorTimeout += 5
				}

				debugTimer.end(`${token} | long polling`, `status ${exception?.response?.status}`)
				console.debug('Error happened while getting chat messages. Trying again in ', this.pollingErrorTimeout, exception)

				setTimeout(() => {
					this.pollNewMessages(token)
				}, this.pollingErrorTimeout * 1000)
				return
			}

			setTimeout(() => {
				this.pollNewMessages(token)
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

		onScroll(event) {
			// handle scrolling status
			if (this.isScrolling) {
				clearTimeout(this.endScrollTimeout)
			}
			this.isScrolling = this.previousScrollTopValue > event.target.scrollTop ? 'up' : 'down'
			this.previousScrollTopValue = event.target.scrollTop
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
		 * @param {object} data the wrapping object
		 * @param {boolean} data.skipHeightCheck whether to fetch messages without checking the height
		 *
		 */
		async handleScroll({ skipHeightCheck = false } = {}) {
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
			const scrollOffsetFromTop = scrollHeight - scrollTop
			const scrollOffsetFromBottom = Math.abs(scrollOffsetFromTop - clientHeight)

			// For chats that are scrolled to bottom and not fitted in one screen
			if (scrollOffsetFromBottom < SCROLL_TOLERANCE && !this.hasMoreMessagesToLoad && scrollTop > 0) {
				this.setChatScrolledToBottom(true)
				this.displayMessagesLoader = false
				this.debounceUpdateReadMarkerPosition()
				return
			}

			if (scrollOffsetFromBottom >= SCROLL_TOLERANCE) {
				this.setChatScrolledToBottom(false)
			}

			if ((scrollHeight > clientHeight && scrollTop < 800 && this.isScrolling === 'up')
				|| skipHeightCheck) {
				if (this.loadingOldMessages || this.isChatBeginningReached) {
					// already loading, don't do it twice
					return
				}
				this.displayMessagesLoader = true
				await this.getOldMessages(false)
				this.displayMessagesLoader = false
				if (this.$refs.scroller.scrollHeight !== scrollHeight) {
					// scroll to previous position + added height
					this.$refs.scroller.scrollTo({
						top: scrollTop + (this.$refs.scroller.scrollHeight - scrollHeight),
					})
				}
				this.setChatScrolledToBottom(false, { auto: true })
			}

			this.debounceUpdateReadMarkerPosition()
		},

		endScroll() {
			this.debounceHandleScroll.flush?.()
			this.isScrolling = null
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
			if (!el || el.offsetParent === null) {
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
				if (el === null || el.offsetParent === null) {
					// Exception: when the message remains not visible
					// e.g: it is the last message in collapsed group
					// unread marker is set to the combined system message.
					// Look for the unread marker itself
					el = document.querySelector('.message-unread-marker')
					if (el) {
						el = el.closest('.message')
					} else {
						console.warn('Visual last read message element not found')
					}
				}
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
				if (!this.$refs.scroller || this.isFocusingMessage) {
					return
				}

				let newTop
				if (options?.force) {
					newTop = this.$refs.scroller.scrollHeight
					this.setChatScrolledToBottom(true)
				} else if (!this.isSticky) {
					// Reading old messages
					return
				} else if (!this.isChatVisible) {
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
				// Message id doesn't exist
				// TODO: in some cases might need to trigger a scroll up if this is an older message
				// https://github.com/nextcloud/spreed/pull/10084
				console.warn('Message to focus not found in DOM', messageId)
				return false // element not found
			}

			let scrollElement = element
			if (this.isChatVisible && scrollElement.offsetParent === null) {
				console.debug('Message to focus is hidden, scrolling to its nearest visible parent', messageId)
				scrollElement = scrollElement.closest('ul[style="display: none;"]').parentElement
			}

			console.debug('Scrolling to a focused message programmatically')
			this.isFocusingMessage = true

			// TODO: doesn't work if chat is hidden. Need to store
			// delayed 'shouldScroll' and call after chat is visible
			// FIXME: because scrollToBottom is also triggered and it is wrapped in $nextTick
			// We need to trigger this at the same time (nextTick) to avoid focusing and then scrolling to bottom
			this.$nextTick(() => {
				scrollElement.scrollIntoView({
					behavior: smooth ? 'smooth' : 'auto',
					block: 'center',
					inline: 'nearest',
				})
			})

			if (this.$refs.scroller && !smooth) {
				// scroll the viewport slightly further to make sure the element is about 1/3 from the top
				this.$refs.scroller.scrollTop += this.$refs.scroller.offsetHeight / 4
			}

			this.checkChatNotScrollable()

			if (highlightAnimation && scrollElement === element) {
				// element is visible, highlight it
				element.classList.add('message--highlighted')
			}
			this.isFocusingMessage = false

			return true // element found
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
			this.$store.dispatch('cancelPollNewMessages', { requestId: this.chatIdentifier })
		},

		handleNetworkOnline() {
			console.debug('Restarting polling of new chat messages')
			this.pollNewMessages(this.token)
		},

		async onRouteChange({ from, to }) {
			if (from.name === 'conversation' && to.name === 'conversation'
				&& from.params.token === to.params.token
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
						await this.getMessageContext(this.token, focusedId)
						this.focusMessage(focusedId, true)
					}
				}
			}
		},

		setChatScrolledToBottom(value, { auto = false } = {}) {
			let isScrolledToBottom = value
			if (auto) {
				const scrollOffset = this.$refs.scroller.scrollHeight - this.$refs.scroller.scrollTop
				isScrolledToBottom = Math.abs(scrollOffset - this.$refs.scroller.clientHeight) < SCROLL_TOLERANCE
			}
			this.$emit('update:is-chat-scrolled-to-bottom', isScrolledToBottom)
			if (isScrolledToBottom) {
				// mark as read if marker was seen
				// we have to do this early because unfocusing the window will remove the stickiness
				this.debounceUpdateReadMarkerPosition()
			}
		},

		onWindowFocus() {
			// setTimeout is needed here for Safari to correctly remove the unread marker
			setTimeout(() => {
				this.refreshReadMarkerPosition()
				// Regenerate relative date separators
				Object.keys(this.dateSeparatorLabels).forEach(dateTimestamp => {
					this.$set(this.dateSeparatorLabels, dateTimestamp, formatRelativeTime(dateTimestamp * 1000, { weekPrefix: 'numeric', weekSuffix: 'LL', omitSameYear: true }))
				})
			}, 2)
		},

		messagesGroupComponent(group) {
			return group.isSystemMessagesGroup ? MessagesSystemGroup : MessagesGroup
		},

		onMessageHeightChanged({ heightDiff }) {
			// scroll down by the height difference
			this.$refs.scroller.scrollTop += heightDiff
		},

		updateTasksCount() {
			if (!this.$refs.scroller) {
				return
			}
			const tasksDoneCount = this.$refs.scroller.querySelectorAll('.checkbox-content__icon--checked')?.length
			const tasksCount = this.$refs.scroller.querySelectorAll('.task-list-item')?.length
			this.chatExtrasStore.setTasksCounters({ tasksCount, tasksDoneCount })
		},

		checkChatNotScrollable() {
			const isNotScrollable = this.$refs.scroller
				? this.$refs.scroller.clientHeight === this.$refs.scroller.scrollHeight
				: false

			if (isNotScrollable && !this.isChatScrolledToBottom) {
				this.setChatScrolledToBottom(true)
			}

			return isNotScrollable
		},

		handleWheelEvent(event) {
			// If messages fit in the viewport and user scrolls up, we need to trigger the loading of older messages
			if (event.deltaY < 0) {
				if (this.isChatBeginningReached) {
					// Remove event listener as it needs to be triggered
					// only when it's not confirmed that the chat beginning is reached
					this.$refs.scroller.removeEventListener('wheel', this.handleWheelEvent)
					return
				}

				this.isScrolling = 'up'
				this.debounceHandleScroll({ skipHeightCheck: true })
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';

.scroller {
	position: relative;
	flex: 1 0;
	padding-top: var(--default-grid-baseline);
	overflow-y: scroll;
	overflow-x: hidden;
	border-bottom: 1px solid var(--color-border);
	transition: $transition;

	&--chatScrolledToBottom {
		border-block-end-color: transparent;
	}

	&__content {
		max-width: $messages-list-max-width;
		margin: 0 auto;
	}

	&__loading {
		position: relative;
		height: 0;
		max-width: $messages-list-max-width;
		margin: 0 auto;

		&-element {
			position: absolute;
			top: 0;
			inset-inline-start: calc(2 * var(--default-grid-baseline));
		}
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
		display: grid;
		grid-template-columns: minmax(0, $messages-text-max-width) $messages-info-width;
		z-index: 2;
		margin-inline-start: calc($messages-avatar-width);
		margin-bottom: 5px;
		padding-inline: var(--default-grid-baseline);
		pointer-events: none;
	}

	&__date-text {
		margin: 0 auto;
		padding: var(--default-grid-baseline) calc(3 * var(--default-grid-baseline));
		text-wrap: nowrap;
		color: var(--color-text-maxcontrast);
		background-color: var(--color-background-dark);
		border-radius: var(--border-radius-element, var(--border-radius-pill));

		&::first-letter {
			text-transform: capitalize;
		}
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

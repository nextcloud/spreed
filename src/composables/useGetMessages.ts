/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosError } from '@nextcloud/axios'
import type {
	ChatMessage,
	Conversation,
} from '../types/index.ts'

import Axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useStore } from 'vuex'
import { CHAT, MESSAGE } from '../constants.ts'
import { debugTimer } from '../utils/debugTimer.ts'
import { useGetThreadId } from './useGetThreadId.ts'
import { useGetToken } from './useGetToken.ts'

/**
 * Check whether caught error is from OCS API
 */
function isAxiosErrorResponse(exception: unknown): exception is AxiosError<string> {
	return exception !== null && typeof exception === 'object' && 'response' in exception
}

let isUnmounting = false
let expirationInterval: NodeJS.Timeout | undefined

/**
 * Composable to provide control logic for fetching messages list
 */
export function useGetMessagesProvider() {
	const store = useStore()
	const route = useRoute()

	const currentToken = useGetToken()
	const threadId = useGetThreadId()
	const conversation = computed<Conversation | undefined>(() => store.getters.conversation(currentToken.value))
	const isInLobby = computed<boolean>(() => store.getters.isInLobby)

	const pollingErrorTimeout = ref(1)

	const loadingOldMessages = ref(false)
	const isInitialisingMessages = ref(false)
	const stopFetchingOldMessages = ref(false)

	/**
	 * Returns whether the current participant is a participant of current conversation.
	 */
	const isParticipant = computed<boolean>(() => {
		if (!conversation.value) {
			return false
		}

		return !!store.getters.findParticipant(currentToken.value, conversation.value)?.attendeeId
	})

	const chatIdentifier = computed(() => currentToken.value + ':' + isParticipant.value)

	const firstKnownMessage = computed<ChatMessage | undefined>(() => {
		return store.getters.message(currentToken.value, store.getters.getFirstKnownMessageId(currentToken.value))
	})
	const isChatBeginningReached = computed(() => {
		return stopFetchingOldMessages.value || (!!firstKnownMessage.value
			&& firstKnownMessage.value.messageType === MESSAGE.TYPE.SYSTEM
			&& ['conversation_created', 'history_cleared'].includes(firstKnownMessage.value.systemMessage))
	})

	watch(chatIdentifier, (newValue, oldValue) => {
		if (oldValue) {
			store.dispatch('cancelPollNewMessages', { requestId: oldValue })
		}
		handleStartGettingMessagesPreconditions(currentToken.value)

		/** Remove expired messages when joining a room */
		store.dispatch('removeExpiredMessages', { token: currentToken.value })
	}, { immediate: true })

	subscribe('networkOffline', handleNetworkOffline)
	subscribe('networkOnline', handleNetworkOnline)

	/** Every 30 seconds we remove expired messages from the store */
	expirationInterval = setInterval(() => {
		store.dispatch('removeExpiredMessages', { token: currentToken.value })
	}, 30_000)

	onBeforeUnmount(() => {
		unsubscribe('networkOffline', handleNetworkOffline)
		unsubscribe('networkOnline', handleNetworkOnline)

		store.dispatch('cancelPollNewMessages', { requestId: chatIdentifier.value })
		isUnmounting = true
		clearInterval(expirationInterval)
	})

	/**
	 * Stop polling due to offline
	 */
	function handleNetworkOffline() {
		console.debug('Canceling message request as we are offline')
		store.dispatch('cancelPollNewMessages', { requestId: chatIdentifier.value })
	}

	/**
	 * Resume polling, when back online
	 */
	function handleNetworkOnline() {
		console.debug('Restarting polling of new chat messages')
		pollNewMessages(currentToken.value)
	}

	/**
	 * Initialize chat context borders and start fetching messages
	 * @param token token of conversation where a method was called
	 */
	async function handleStartGettingMessagesPreconditions(token: string) {
		if (token && isParticipant.value && !isInLobby.value) {
			// prevent sticky mode before we have loaded anything
			isInitialisingMessages.value = true
			const focusMessageId = route?.hash?.startsWith('#message_') ? parseInt(route.hash.slice(9), 10) : null

			store.dispatch('setVisualLastReadMessageId', { token, id: conversation.value!.lastReadMessage })

			if (!store.getters.getFirstKnownMessageId(token)) {
				try {
					// Start from message hash or unread marker
					let startingMessageId = focusMessageId !== null ? focusMessageId : conversation.value!.lastReadMessage
					// Check if thread is initially opened
					if (threadId.value) {
						// FIXME temporary get thread messages from the start
						startingMessageId = threadId.value
					}

					// First time load, initialize important properties
					if (!startingMessageId) {
						throw new Error(`[DEBUG] spreed: context message ID is ${startingMessageId}`)
					}
					store.dispatch('setFirstKnownMessageId', { token, id: startingMessageId })
					store.dispatch('setLastKnownMessageId', { token, id: startingMessageId })
					// If MESSAGE.CHAT_BEGIN_ID we need to get the context from the beginning
					// using 0 as the API does not support negative values
					// Get chat messages before last read message and after it
					await getMessageContext(token, startingMessageId !== MESSAGE.CHAT_BEGIN_ID ? startingMessageId : 0)
				} catch (exception) {
					console.debug(exception)
					// Request was cancelled, stop getting preconditions and restore initial state
					store.dispatch('setFirstKnownMessageId', { token, id: null })
					store.dispatch('setLastKnownMessageId', { token, id: null })
					return
				}
			}

			isInitialisingMessages.value = false

			// Once the history is received, starts looking for new messages.
			await pollNewMessages(token)
		} else {
			store.dispatch('cancelPollNewMessages', { requestId: chatIdentifier.value })
		}
	}

	/**
	 * Fetches the messages of a conversation given the conversation token.
	 * Creates a long polling request for new messages.
	 * @param token token of conversation where a method was called
	 * @param messageId messageId
	 */
	async function getMessageContext(token: string, messageId: number) {
		// Make the request
		loadingOldMessages.value = true
		try {
			debugTimer.start(`${token} | get context`)
			await store.dispatch('getMessageContext', {
				token,
				messageId,
				minimumVisible: CHAT.MINIMUM_VISIBLE,
			})
			debugTimer.end(`${token} | get context`, 'status 200')
			loadingOldMessages.value = false
		} catch (exception) {
			if (Axios.isCancel(exception)) {
				console.debug('The request has been canceled', exception)
				debugTimer.end(`${token} | get context`, 'cancelled')
				loadingOldMessages.value = false
				throw exception
			}

			if (isAxiosErrorResponse(exception) && exception.response?.status === 304) {
				// 304 - Not modified
				// Empty chat, no messages to load
				debugTimer.end(`${token} | get context`, 'status 304')
				store.dispatch('loadedMessagesOfConversation', { token })

				stopFetchingOldMessages.value = true
			}
		}
		loadingOldMessages.value = false
	}

	/**
	 * Get messages history.
	 *
	 * @param includeLastKnown Include or exclude the last known message in the response
	 */
	async function getOldMessages(includeLastKnown: boolean) {
		if (isChatBeginningReached.value) {
			// Beginning of the chat reached, no more messages to load
			return
		}
		// Make the request
		loadingOldMessages.value = true
		try {
			debugTimer.start(`${currentToken.value} | fetch history`)
			await store.dispatch('fetchMessages', {
				token: currentToken.value,
				lastKnownMessageId: store.getters.getFirstKnownMessageId(currentToken.value),
				includeLastKnown,
				minimumVisible: CHAT.MINIMUM_VISIBLE,
			})
			debugTimer.end(`${currentToken.value} | fetch history`, 'status 200')
		} catch (exception) {
			if (Axios.isCancel(exception)) {
				debugTimer.end(`${currentToken.value} | fetch history`, 'cancelled')
				console.debug('The request has been canceled', exception)
			}
			if (isAxiosErrorResponse(exception) && exception?.response?.status === 304) {
				// 304 - Not modified
				debugTimer.end(`${currentToken.value} | fetch history`, 'status 304')
				stopFetchingOldMessages.value = true
			}
		}
		loadingOldMessages.value = false
	}

	/**
	 * Fetches the messages of a conversation given the conversation token.
	 * Creates a long polling request for new messages.
	 * @param token token of conversation where a method was called
	 */
	async function pollNewMessages(token: string) {
		if (isUnmounting) {
			console.debug('Prevent polling new messages on MessagesList being destroyed')
			return
		}

		// Check that the token has not changed
		if (currentToken.value !== token) {
			console.debug(`token has changed to ${currentToken.value}, breaking the loop for ${token}`)
			return
		}

		// Make the request
		try {
			debugTimer.start(`${token} | long polling`)
			// TODO: move polling logic to the store and also cancel timers on cancel
			pollingErrorTimeout.value = 1
			await store.dispatch('pollNewMessages', {
				token,
				lastKnownMessageId: store.getters.getLastKnownMessageId(token),
				requestId: chatIdentifier.value,
			})
			debugTimer.end(`${token} | long polling`, 'status 200')
		} catch (exception) {
			if (Axios.isCancel(exception)) {
				debugTimer.end(`${token} | long polling`, 'cancelled')
				console.debug('The request has been canceled', exception)
				return
			}

			if (isAxiosErrorResponse(exception) && exception?.response?.status === 304) {
				debugTimer.end(`${token} | long polling`, 'status 304')
				// 304 - Not modified
				// This is not an error, so reset error timeout and poll again
				pollingErrorTimeout.value = 1
				setTimeout(() => {
					pollNewMessages(token)
				}, 500)
				return
			}

			if (pollingErrorTimeout.value < 30) {
				// Delay longer after each error
				pollingErrorTimeout.value += 5
			}

			debugTimer.end(`${token} | long polling`, `status ${isAxiosErrorResponse(exception) ? exception?.response?.status : 'unknown'}`)
			console.debug('Error happened while getting chat messages. Trying again in ', pollingErrorTimeout.value, exception)

			setTimeout(() => {
				pollNewMessages(token)
			}, pollingErrorTimeout.value * 1000)
			return
		}

		setTimeout(() => {
			pollNewMessages(token)
		}, 500)
	}

	return {
		loadingOldMessages,
		isInitialisingMessages,
		stopFetchingOldMessages,
		isChatBeginningReached,

		getMessageContext,
		getOldMessages,
	}
}

/**
 * Composable to inject control logic for fetching messages list in the component
 */
export function useGetMessages() {
	// FIXME
	return useGetMessagesProvider()
}

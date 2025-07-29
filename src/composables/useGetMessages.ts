/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosError } from '@nextcloud/axios'
import type {
	ComputedRef,
	InjectionKey,
	Ref,
} from 'vue'
import type { RouteLocation } from 'vue-router'
import type {
	ChatMessage,
	Conversation,
} from '../types/index.ts'

import Axios from '@nextcloud/axios'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { computed, inject, onBeforeUnmount, provide, ref, watch } from 'vue'
import { START_LOCATION, useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex'
import { CHAT, MESSAGE } from '../constants.ts'
import { EventBus } from '../services/EventBus.ts'
import { useChatStore } from '../stores/chat.ts'
import { debugTimer } from '../utils/debugTimer.ts'
import { useGetThreadId } from './useGetThreadId.ts'
import { useGetToken } from './useGetToken.ts'

type GetMessagesContext = {
	contextMessageId: Ref<number>
	loadingOldMessages: Ref<boolean>
	isInitialisingMessages: Ref<boolean>
	stopFetchingOldMessages: Ref<boolean>
	isChatBeginningReached: ComputedRef<boolean>

	getOldMessages: (token: string, includeLastKnown: boolean) => Promise<void>
}

const GET_MESSAGES_CONTEXT_KEY: InjectionKey<GetMessagesContext> = Symbol.for('GET_MESSAGES_CONTEXT')

/**
 * Check whether caught error is from OCS API
 */
function isAxiosErrorResponse(exception: unknown): exception is AxiosError<string> {
	return exception !== null && typeof exception === 'object' && 'response' in exception
}

let pollingTimeout: NodeJS.Timeout | undefined
let expirationInterval: NodeJS.Timeout | undefined
let pollingErrorTimeout = 1_000

/**
 * Composable to provide control logic for fetching messages list
 */
export function useGetMessagesProvider() {
	const store = useStore()
	const router = useRouter()
	const route = useRoute()
	const chatStore = useChatStore()

	const currentToken = useGetToken()
	const contextThreadId = useGetThreadId()
	const conversation = computed<Conversation | undefined>(() => store.getters.conversation(currentToken.value))
	const isInLobby = computed<boolean>(() => store.getters.isInLobby)

	const contextMessageId = ref<number>(0)
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

	const firstKnownMessage = computed<ChatMessage | undefined>(() => {
		return store.getters.message(currentToken.value, store.getters.getFirstKnownMessageId(currentToken.value))
	})
	const isChatBeginningReached = computed(() => {
		return stopFetchingOldMessages.value || (!!firstKnownMessage.value
			&& firstKnownMessage.value.messageType === MESSAGE.TYPE.SYSTEM
			&& ['conversation_created', 'history_cleared'].includes(firstKnownMessage.value.systemMessage))
	})

	/** Initial check to ensure context is created once route is available */
	router.isReady().then(() => {
		if (currentToken.value && isParticipant.value && !isInLobby.value) {
			handleStartGettingMessagesPreconditions(currentToken.value)
		}
	})

	watch(
		[currentToken, () => isParticipant.value && !isInLobby.value],
		([newToken, canGetMessages], [oldToken, _oldCanGetMessages]) => {
			if (route.name === START_LOCATION.name) { // Direct object comparison does not work
				return
			}
			if (oldToken && oldToken !== newToken) {
				store.dispatch('cancelPollNewMessages', { requestId: oldToken })
			}

			if (newToken && canGetMessages) {
				handleStartGettingMessagesPreconditions(newToken)
			} else {
				store.dispatch('cancelPollNewMessages', { requestId: newToken })
			}

			/** Remove expired messages when joining a room */
			store.dispatch('removeExpiredMessages', { token: newToken })
		},
	)

	subscribe('networkOffline', handleNetworkOffline)
	subscribe('networkOnline', handleNetworkOnline)
	EventBus.on('route-change', onRouteChange)

	/** Every 30 seconds we remove expired messages from the store */
	expirationInterval = setInterval(() => {
		store.dispatch('removeExpiredMessages', { token: currentToken.value })
	}, 30_000)

	onBeforeUnmount(() => {
		unsubscribe('networkOffline', handleNetworkOffline)
		unsubscribe('networkOnline', handleNetworkOnline)
		EventBus.off('route-change', onRouteChange)

		store.dispatch('cancelPollNewMessages', { requestId: currentToken.value })
		clearInterval(pollingTimeout)
		clearInterval(expirationInterval)
	})

	/**
	 * Parse hash string to get message id
	 */
	function getMessageIdFromHash(hash?: string): number | null {
		return (hash && hash.startsWith('#message_')) ? parseInt(hash.slice(9), 10) : null
	}

	/**
	 * Stop polling due to offline
	 */
	function handleNetworkOffline() {
		if (currentToken.value) {
			console.debug('Canceling message request as we are offline')
			store.dispatch('cancelPollNewMessages', { requestId: currentToken.value })
		}
	}

	/**
	 * Resume polling, when back online
	 */
	function handleNetworkOnline() {
		if (currentToken.value) {
			console.debug('Restarting polling of new chat messages')
			pollNewMessages(currentToken.value)
		}
	}

	/**
	 * Handle route changes to initialize chat or thread, and focus given message
	 */
	async function onRouteChange({ from, to }: { from: RouteLocation, to: RouteLocation }) {
		if (from.name !== 'conversation' || to.name !== 'conversation'
			|| from.params.token !== to.params.token || typeof to.params.token !== 'string') {
			// Only handle route changes within the same conversation
			return
		}

		const focusMessageId = getMessageIdFromHash(to.hash)
		const threadId = +(to.query.threadId ?? 0)
		if (from.hash !== to.hash && focusMessageId !== null) {
			// the hash changed, need to focus/highlight another message
			contextMessageId.value = focusMessageId
		} else if (conversation.value?.lastReadMessage && conversation.value.lastReadMessage > contextMessageId.value) {
			// focus last read message first
			contextMessageId.value = conversation.value.lastReadMessage
		} else {
			// last known message in the most recent block store
			contextMessageId.value = Math.max(...chatStore.chatBlocks[to.params.token][0])
		}

		const hasMessageInStore = chatStore.hasMessage(to.params.token, { messageId: contextMessageId.value, threadId })
		if (!hasMessageInStore) {
			// message not found in the list, need to fetch it first
			await getMessageContext(to.params.token, contextMessageId.value, threadId)
		}

		// need some delay (next tick is too short) to be able to run
		// after the browser's native "scroll to anchor" from the hash
		window.setTimeout(() => {
			EventBus.emit('focus-message', contextMessageId.value)
		}, 2)
	}

	/**
	 * Initialize chat context borders and start fetching messages
	 * @param token token of conversation where a method was called
	 */
	async function handleStartGettingMessagesPreconditions(token: string) {
		// prevent sticky mode before we have loaded anything
		isInitialisingMessages.value = true

		// Start from message hash or unread marker
		const focusMessageId = getMessageIdFromHash(route.hash)
		contextMessageId.value = focusMessageId !== null ? focusMessageId : conversation.value!.lastReadMessage

		store.dispatch('setVisualLastReadMessageId', { token, id: conversation.value!.lastReadMessage })

		if (!chatStore.chatBlocks[token]) {
			try {
				// TODO id previously could be 0 in this place, need to block fetching the chat from beginning
				if (!contextMessageId.value) {
					throw new Error(`[DEBUG] spreed: context message ID is ${contextMessageId.value}`)
				}

				await getMessageContext(token, contextMessageId.value, contextThreadId.value)
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
	}

	/**
	 * Fetches the messages of a conversation given the conversation token.
	 * Creates a long polling request for new messages.
	 * @param token token of conversation where a method was called
	 * @param messageId context messageId
	 * @param threadId context thread id
	 */
	async function getMessageContext(token: string, messageId: number, threadId: number) {
		loadingOldMessages.value = true
		try {
			debugTimer.start(`${token} | get context`)
			// Update environment around context
			store.dispatch('setFirstKnownMessageId', { token, id: messageId })
			store.dispatch('setLastKnownMessageId', { token, id: messageId })
			// Make the request
			await store.dispatch('getMessageContext', {
				token,
				// If MESSAGE.CHAT_BEGIN_ID we need to get the context from the beginning
				// using 0 as the API does not support negative values
				// Get chat messages before last read message and after it
				messageId: messageId !== MESSAGE.CHAT_BEGIN_ID ? messageId : 0,
				threadId: threadId !== 0 ? threadId : undefined,
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
	 * @param token token of conversation where a method was called
	 * @param includeLastKnown Include or exclude the last known message in the response
	 */
	async function getOldMessages(token: string, includeLastKnown: boolean) {
		if (isChatBeginningReached.value) {
			// Beginning of the chat reached, no more messages to load
			return
		}
		// Make the request
		loadingOldMessages.value = true
		try {
			debugTimer.start(`${token} | fetch history`)
			await store.dispatch('fetchMessages', {
				token,
				lastKnownMessageId: store.getters.getFirstKnownMessageId(token),
				includeLastKnown,
				threadId: contextThreadId.value !== 0 ? contextThreadId.value : undefined,
				minimumVisible: CHAT.MINIMUM_VISIBLE,
			})
			debugTimer.end(`${token} | fetch history`, 'status 200')
		} catch (exception) {
			if (Axios.isCancel(exception)) {
				debugTimer.end(`${token} | fetch history`, 'cancelled')
				console.debug('The request has been canceled', exception)
			}
			if (isAxiosErrorResponse(exception) && exception?.response?.status === 304) {
				// 304 - Not modified
				debugTimer.end(`${token} | fetch history`, 'status 304')
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
		// Check that the token has not changed
		if (currentToken.value !== token) {
			console.debug(`token has changed to ${currentToken.value}, breaking the loop for ${token}`)
			return
		}

		// Make the request
		try {
			debugTimer.start(`${token} | long polling`)
			// TODO: move polling logic to the store and also cancel timers on cancel
			pollingErrorTimeout = 1_000
			await store.dispatch('pollNewMessages', {
				token,
				lastKnownMessageId: store.getters.getLastKnownMessageId(token),
				requestId: token,
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
				pollingErrorTimeout = 1_000
				clearTimeout(pollingTimeout)
				pollingTimeout = setTimeout(() => {
					pollNewMessages(token)
				}, 500)
				return
			}

			if (pollingErrorTimeout < 30_000) {
				// Delay longer after each error
				pollingErrorTimeout += 5_000
			}

			debugTimer.end(`${token} | long polling`, `status ${isAxiosErrorResponse(exception) ? exception?.response?.status : 'unknown'}`)
			console.debug('Error happened while getting chat messages. Trying again in %d seconds', pollingErrorTimeout / 1_000, exception)

			clearTimeout(pollingTimeout)
			pollingTimeout = setTimeout(() => {
				pollNewMessages(token)
			}, pollingErrorTimeout)
			return
		}

		clearTimeout(pollingTimeout)
		pollingTimeout = setTimeout(() => {
			pollNewMessages(token)
		}, 500)
	}

	provide(GET_MESSAGES_CONTEXT_KEY, {
		contextMessageId,
		loadingOldMessages,
		isInitialisingMessages,
		stopFetchingOldMessages,
		isChatBeginningReached,

		getOldMessages,
	})
}

/**
 * Composable to inject control logic for fetching messages list in the component
 */
export function useGetMessages() {
	return inject(GET_MESSAGES_CONTEXT_KEY)!
}

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	Conversation,
} from '../types/index.ts'

import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import { MESSAGE } from '../constants.ts'
import { useGetToken } from './useGetToken.ts'

/**
 * Composable to provide control logic for fetching messages list
 */
export function useGetMessagesProvider() {
	const store = useStore()
	const currentToken = useGetToken()
	const conversation = computed<Conversation | undefined>(() => store.getters.conversation(currentToken.value))
	const isInLobby = computed<boolean>(() => store.getters.isInLobby)

	const pollingErrorTimeout = ref(1)
	const destroying = ref(false)

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

	return {
		pollingErrorTimeout,
		loadingOldMessages,
		isInitialisingMessages,
		destroying,
		stopFetchingOldMessages,
		isParticipant,
		isInLobby,
		chatIdentifier,
		isChatBeginningReached,
	}
}

/**
 * Composable to inject control logic for fetching messages list in the component
 */
export function useGetMessages() {
	// FIXME
	return useGetMessagesProvider()
}

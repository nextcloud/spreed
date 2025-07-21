/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	TokenMap,
} from '../types/index.ts'

import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { useStore } from 'vuex'

/**
 * Store for conversation chats
 */
export const useChatStore = defineStore('chat', () => {
	const store = useStore()

	const chatBlocks = reactive<TokenMap<Set<number>[]>>({})

	/**
	 * Returns list of messages, belonging to current context
	 */
	function getMessagesList(token: string): ChatMessage[] {
		if (store.state.messagesStore.messages[token]) {
			return Object.values(store.state.messagesStore.messages[token])
		} else {
			return []
		}
	}

	return {
		chatBlocks,

		getMessagesList,
	}
})

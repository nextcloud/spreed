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

type GetMessagesListOptions = {
	/** if given, look for Set that has it */
	messageId?: number
	/** if given, look for thread Set */
	threadId?: number
}

type ProcessChatBlocksOptions = {
	/** if given, look for Set that has it */
	mergeBy?: number
}

/**
 * Check, if two sets intersect with each other
 * Same complexity and result as !Set.prototype.isDisjointFrom()
 */
function checkIfIntersect(parentBlock: Set<number>, childBlock: Set<number>): boolean {
	for (const id of childBlock) {
		if (parentBlock.has(id)) {
			return true
		}
	}
	return false
}

/**
 * Store for conversation chats
 */
export const useChatStore = defineStore('chat', () => {
	const store = useStore()

	const chatBlocks = reactive<TokenMap<Set<number>[]>>({})
	const firstKnown = reactive<TokenMap<number>>({})
	const lastKnown = reactive<TokenMap<number>>({})

	/**
	 * Returns list of messages, belonging to current context
	 */
	function getMessagesList(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): ChatMessage[] {
		if (!store.state.messagesStore.messages[token] || !chatBlocks[token]) {
			return []
		}

		if (threadId) {
			// FIXME temporary show all messages for given thread from all chat blocks - no behaviour change
			return prepareMessagesList(token, new Set(Array.from(chatBlocks[token].flatMap((set) => Array.from(set)))))
				.filter((message) => {
					return message.threadId === threadId
				})
		}

		if (messageId <= 0) {
			// Fallback or constant, return first block
			return prepareMessagesList(token, chatBlocks[token][0])
		}

		// Otherwise look for a set containing given context id
		const contextBlock = chatBlocks[token].find((set) => set.has(messageId)) ?? chatBlocks[token][0]
		return prepareMessagesList(token, contextBlock)
	}

	/**
	 * Returns list of messages from given set
	 */
	function prepareMessagesList(token: string, block: Set<number>): ChatMessage[] {
		return Array.from(block).sort((a, b) => a - b)
			.reduce<ChatMessage[]>((acc, id) => {
				const message = store.state.messagesStore.messages[token][id]
				if (message) {
					// If message is not found in the store, it's an invisible system or expired message
					acc.push(message)
				}
				return acc
			}, [])
	}

	/**
	 * Returns whether message is known in any of blocks (then it exists in store)
	 */
	function hasMessage(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): boolean {
		if (!chatBlocks[token]) {
			return false
		}

		if (threadId) {
			// FIXME temporary check all messages for given thread from all chat blocks
			return chatBlocks[token].findIndex((set) => set.has(messageId)) !== -1
		}

		return chatBlocks[token].findIndex((set) => set.has(messageId)) !== -1
	}

	/**
	 * Returns first known message, belonging to current context. Defaults to given messageId
	 */
	function getFirstKnown(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): number {
		if (!chatBlocks[token]) {
			return messageId
		}

		if (threadId) {
			// FIXME temporary check all messages for given thread from all chat blocks
			return Math.min(...prepareMessagesList(token, new Set(Array.from(chatBlocks[token].flatMap((set) => Array.from(set)))))
				.filter((message) => {
					return message.threadId === threadId
				}).map((message) => message.id))
		}

		if (messageId <= 0) {
			return firstKnown[token]
		}

		const contextBlock = chatBlocks[token].find((set) => set.has(messageId))
		return contextBlock ? Math.min(...contextBlock) : firstKnown[token]
	}

	/**
	 * Returns last known message, belonging to current context. Defaults to given messageId
	 */
	function getLastKnown(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): number {
		if (!chatBlocks[token]) {
			return messageId
		}

		if (threadId) {
			// FIXME temporary check all messages for given thread from all chat blocks
			return Math.max(...prepareMessagesList(token, new Set(Array.from(chatBlocks[token].flatMap((set) => Array.from(set)))))
				.filter((message) => {
					return message.threadId === threadId
				}).map((message) => message.id))
		}

		if (messageId <= 0) {
			return lastKnown[token]
		}

		const contextBlock = chatBlocks[token].find((set) => set.has(messageId))
		return contextBlock ? Math.max(...contextBlock) : lastKnown[token]
	}

	/**
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 * Afterwards, cache first and last known message ids for the first block
	 */
	function processChatBlocks(token: string, messages: ChatMessage[], options?: ProcessChatBlocksOptions): void {
		const newMessageIdsSet = new Set(messages.map((message) => message.id))

		if (!chatBlocks[token]) {
			// If no blocks exist, create a new one with the first message. First in array will be considered main block
			chatBlocks[token] = [newMessageIdsSet]
			firstKnown[token] = Math.min(...newMessageIdsSet)
			lastKnown[token] = Math.max(...newMessageIdsSet)
			return
		}

		if (options?.mergeBy) {
			newMessageIdsSet.add(options.mergeBy)
		}

		chatBlocks[token] = mergeAndSortChatBlocks(chatBlocks[token], newMessageIdsSet)
		firstKnown[token] = Math.min(...chatBlocks[token][0])
		lastKnown[token] = Math.max(...chatBlocks[token][0])
	}

	/**
	 * Check, if blocks are intersecting with each other, and merge them in this case
	 * Otherwise, sort them to expected position (sorted by max id in set)
	 */
	function mergeAndSortChatBlocks(blocks: Set<number>[], unsortedBlock: Set<number>): Set<number>[] {
		let isUnsortedBlockUsed = false

		const mergedBlocks = blocks.reduce<Set<number>[]>((acc, block) => {
			// If unsorted block is not used yet, try to merge it with current block
			if (!isUnsortedBlockUsed && tryMergeBlocks(block, unsortedBlock)) {
				isUnsortedBlockUsed = true
			}

			// Try to merge concurrent blocks, if unsorted was used (then there's a chance to overlap)
			if (acc.length === 0 || !isUnsortedBlockUsed || !tryMergeBlocks(acc[acc.length - 1], block)) {
				acc.push(block)
			}

			return acc
		}, [])

		if (!isUnsortedBlockUsed) {
			const unsortedMaxId = Math.max(...unsortedBlock)
			const insertIndex = mergedBlocks.findIndex((block) => Math.max(...block) < unsortedMaxId)
			if (insertIndex === -1) {
				// If no block is found with max id less than unsorted, append it to the end
				mergedBlocks.push(unsortedBlock)
			} else {
				mergedBlocks.splice(insertIndex, 0, unsortedBlock)
			}
		}

		return mergedBlocks
	}

	/**
	 * Check, if child block is intersecting with parent, and extend parent in this case
	 * Returns true if parent was extended, false otherwise
	 */
	function tryMergeBlocks(parentBlock: Set<number>, childBlock: Set<number>): boolean {
		if (checkIfIntersect(parentBlock, childBlock)) {
			for (const id of childBlock) {
				parentBlock.add(id)
			}
			return true
		}

		return false
	}

	/**
	 * Adds the message id to the main chat block
	 * (It is expected to appear in most recent one)
	 */
	function addMessageToChatBlocks(token: string, message: ChatMessage) {
		if (!chatBlocks[token]) {
			chatBlocks[token] = [new Set<number>([message.id])]
		} else {
			chatBlocks[token][0].add(message.id)
		}
	}

	/**
	 * Removes one or more message ids from all chat blocks
	 */
	function removeMessagesFromChatBlocks(token: string, messageIds: number | number[]) {
		if (!chatBlocks[token]) {
			return
		}

		const messageIdArray = Array.isArray(messageIds) ? messageIds : [messageIds]

		chatBlocks[token] = chatBlocks[token].reduce<Set<number>[]>((acc, block) => {
			messageIdArray.forEach((id) => block.delete(id))
			if (block.size > 0) {
				acc.push(block)
			}
			return acc
		}, [])

		if (chatBlocks[token].length === 0) {
			delete chatBlocks[token]
		}
	}

	return {
		chatBlocks,
		firstKnown,
		lastKnown,

		getMessagesList,
		hasMessage,
		getFirstKnown,
		getLastKnown,
		processChatBlocks,
		addMessageToChatBlocks,
		removeMessagesFromChatBlocks,
	}
})

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	IdMap,
	TokenIdMap,
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
	/** if given, look for thread Set */
	threadId?: number
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
 * Return an array of only numeric ids from given set
 * (temporary messages have a string id)
 */
function filterNumericIds(block: Set<number | string>): number[] {
	return Array.from(block).filter((id): id is number => Number.isInteger(id))
}

/**
 * Store for conversation chats
 */
export const useChatStore = defineStore('chat', () => {
	const store = useStore()

	const chatBlocks = reactive<TokenMap<Set<number>[]>>({})
	const threadBlocks = reactive<TokenIdMap<Set<number>[]>>({})

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
			if (!threadBlocks[token]?.[threadId]) {
				return []
			}
			const contextBlock = (messageId <= 0)
				? threadBlocks[token][threadId][0]
				: threadBlocks[token][threadId].find((set) => set.has(messageId)) ?? threadBlocks[token][threadId][0]
			return prepareMessagesList(token, contextBlock, threadId)
		}

		// Look for a set containing given context id (return first block as fallback for not found / constants)
		const contextBlock = (messageId <= 0)
			? chatBlocks[token][0]
			: chatBlocks[token].find((set) => set.has(messageId)) ?? chatBlocks[token][0]
		return prepareMessagesList(token, contextBlock)
	}

	/**
	 * Returns list of messages from given set
	 */
	function prepareMessagesList(token: string, block: Set<number>, threadId?: number): ChatMessage[] {
		return Array.from(block).sort((a, b) => a - b)
			.reduce<ChatMessage[]>((acc, id) => {
				const message = store.state.messagesStore.messages[token][id]
				// Check for exceptions (message should not be added to the displayed list):
				// - non-visible system message
				// - completely deleted (expired) message
				// - thread message in general view (apart from the topmost one)
				if (message) {
					// FIXME filter thread messages in general view (message.id === message.threadId)
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
			if (!threadBlocks[token]?.[threadId]) {
				return false
			}
			return threadBlocks[token][threadId].findIndex((set) => set.has(messageId)) !== -1
		}

		return chatBlocks[token].findIndex((set) => set.has(messageId)) !== -1
	}

	/**
	 * Returns first known message id, belonging to current context. Defaults to given messageId
	 */
	function getFirstKnownId(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): number {
		if (!chatBlocks[token]) {
			return messageId
		}

		if (threadId) {
			// If topmost message of thread is in the store, return its id
			if (hasMessage(token, { messageId: threadId, threadId }) || !threadBlocks[token]?.[threadId]) {
				return threadId
			}
			const contextBlock = (messageId <= 0)
				? threadBlocks[token][threadId][0]
				: threadBlocks[token][threadId].find((set) => set.has(messageId)) ?? threadBlocks[token][threadId][0]
			return Math.min(...filterNumericIds(contextBlock))
		}

		const contextBlock = (messageId <= 0)
			? chatBlocks[token][0]
			: chatBlocks[token].find((set) => set.has(messageId)) ?? chatBlocks[token][0]
		return Math.min(...filterNumericIds(contextBlock))
	}

	/**
	 * Returns last known message id, belonging to current context. Defaults to given messageId
	 */
	function getLastKnownId(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): number {
		if (!chatBlocks[token]) {
			return messageId
		}

		if (threadId) {
			if (!threadBlocks[token]?.[threadId]) {
				return threadId
			}
			const contextBlock = (messageId <= 0)
				? threadBlocks[token][threadId][0]
				: threadBlocks[token][threadId].find((set) => set.has(messageId)) ?? threadBlocks[token][threadId][0]
			return Math.max(...filterNumericIds(contextBlock))
		}

		const contextBlock = (messageId <= 0)
			? chatBlocks[token][0]
			: chatBlocks[token].find((set) => set.has(messageId)) ?? chatBlocks[token][0]
		return Math.max(...filterNumericIds(contextBlock))
	}

	/**
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 */
	function processChatBlocks(token: string, messages: ChatMessage[], options?: ProcessChatBlocksOptions): void {
		const threadIdsSet: IdMap<Set<number>> = {}
		const newMessageIdsSet = messages.reduce((acc, message) => {
			acc.add(message.id)
			if (message.isThread && message.threadId) {
				if (!threadIdsSet[message.threadId]) {
					threadIdsSet[message.threadId] = new Set<number>()
				}
				threadIdsSet[message.threadId].add(message.id)
			}
			return acc
		}, new Set<number>())

		if (options?.threadId) {
			if (!chatBlocks[token] && newMessageIdsSet.has(options.threadId)) {
				chatBlocks[token] = [new Set<number>([options.threadId])]
			}
			processThreadBlocks(token, options.threadId, newMessageIdsSet, options)
			return
		}

		if (options?.mergeBy) {
			newMessageIdsSet.add(options.mergeBy)

			const threadsToUpdate = Object.keys(threadIdsSet)
			if (threadsToUpdate.length) {
				const chatBlockWithMergeBy: Set<number> = chatBlocks[token].find((set) => set.has(options.mergeBy!))!
				// Populate thread blocks from chat blocks
				threadsToUpdate.forEach((threadId) => {
					const maxMessageId = Math.max(...Array.from(chatBlockWithMergeBy).filter((id) => {
						const message = store.state.messagesStore.messages[token][id]
						return message && message.threadId === +threadId
					}))
					if (maxMessageId) {
						processThreadBlocks(token, threadId, threadIdsSet[threadId], {
							mergeBy: maxMessageId,
						})
					}
				})
			}
		}

		chatBlocks[token] = mergeAndSortChatBlocks(chatBlocks[token], newMessageIdsSet)
		if (!options?.mergeBy) {
			Object.entries(threadIdsSet).forEach(([threadId, threadMessageIdsSet]) => {
				processThreadBlocks(token, threadId, threadMessageIdsSet)
			})
		}	
	}

	/**
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 */
	function processThreadBlocks(token: string, threadId: string | number, threadMessagesSet: Set<number>, options?: ProcessChatBlocksOptions): void {
		if (!threadBlocks[token]) {
			threadBlocks[token] = {}
		}
		if (!threadBlocks[token][threadId]) {
			// If no blocks exist, create a new one with the first message. First in array will be considered main block
			threadBlocks[token][threadId] = [threadMessagesSet]
			return
		}

		if (options?.mergeBy) {
			threadMessagesSet.add(options.mergeBy)
		}

		threadBlocks[token][threadId] = mergeAndSortChatBlocks(threadBlocks[token][threadId], threadMessagesSet)
	}

	/**
	 * Check, if blocks are intersecting with each other, and merge them in this case
	 * Otherwise, sort them to expected position (sorted by max id in set)
	 */
	function mergeAndSortChatBlocks(blocks: Set<number>[], unsortedBlock: Set<number>): Set<number>[] {
		if (blocks.length === 0) {
			return [unsortedBlock]
		}
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
			// FIXME only add thread first messages
			chatBlocks[token] = [new Set<number>([message.id])]
		} else {
			chatBlocks[token][0].add(message.id)
		}

		if (message.threadId && message.isThread) {
			if (!threadBlocks[token]) {
				threadBlocks[token] = {}
			}
			if (!threadBlocks[token][message.threadId]) {
				threadBlocks[token][message.threadId] = [new Set<number>([message.id])]
			} else {
				threadBlocks[token][message.threadId][0].add(message.id)
			}
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
			purgeChatStore(token)
			return
		}

		const knownThreadIds = Object.keys(threadBlocks[token] || {})
		const newThreadBlocks: IdMap<Set<number>[]> = {}
		for (const threadId of knownThreadIds) {
			newThreadBlocks[threadId] = threadBlocks[token][threadId].reduce<Set<number>[]>((acc, block) => {
				messageIdArray.forEach((id) => block.delete(id))
				if (block.size > 0) {
					acc.push(block)
				}
				return acc
			}, [])
			if (newThreadBlocks[threadId].length === 0) {
				delete newThreadBlocks[threadId]
			}
		}

		if (Object.keys(newThreadBlocks).length === 0) {
			delete threadBlocks[token]
		} else {
			threadBlocks[token] = newThreadBlocks
		}
	}

	/**
	 * Clears the messages entry from the store for the given token starting from defined id
	 */
	function clearMessagesHistory(token: string, idToDelete: number) {
		if (!chatBlocks[token]) {
			return
		}

		const deleteIndex = chatBlocks[token].findIndex((block) => Math.max(...block) < idToDelete)
		if (deleteIndex === -1) {
			// Not found, nothing to delete
		} else if (deleteIndex === 0) {
			// If first block is to be deleted, remove all blocks
			purgeChatStore(token)
			return
		} else {
			// Remove all blocks with max id less than given id
			chatBlocks[token] = chatBlocks[token].slice(0, deleteIndex)
			const lastBlock = chatBlocks[token].at(-1)!
			for (const id of lastBlock) {
				if (id < idToDelete) {
					lastBlock.delete(id)
				}
			}
		}

		const knownThreadIds = Object.keys(threadBlocks[token] || {})
		const newThreadBlocks: IdMap<Set<number>[]> = {}
		for (const threadId of knownThreadIds) {
			const deleteIndex = threadBlocks[token][threadId].findIndex((block) => Math.max(...block) < idToDelete)
			if (deleteIndex === -1) {
				// Not found, nothing to delete (copying as-is)
				newThreadBlocks[threadId] = threadBlocks[token][threadId]
			} else if (deleteIndex === 0) {
				// If first block is to be deleted, remove all blocks (simply not copying)
			} else {
				// Remove all blocks with max id less than given id
				newThreadBlocks[threadId] = threadBlocks[token][threadId].slice(0, deleteIndex)
				const lastBlock = newThreadBlocks[threadId].at(-1)!
				for (const id of lastBlock) {
					if (id < idToDelete) {
						lastBlock.delete(id)
					}
				}
			}
		}

		if (Object.keys(newThreadBlocks).length === 0) {
			delete threadBlocks[token]
		} else {
			threadBlocks[token] = newThreadBlocks
		}
	}

	/**
	 * Clears the store for the given token
	 */
	function purgeChatStore(token: string) {
		delete chatBlocks[token]
		delete threadBlocks[token]
	}

	return {
		chatBlocks,
		threadBlocks,

		getMessagesList,
		hasMessage,
		getFirstKnownId,
		getLastKnownId,
		processChatBlocks,
		addMessageToChatBlocks,
		removeMessagesFromChatBlocks,
		clearMessagesHistory,
		purgeChatStore,
	}
})

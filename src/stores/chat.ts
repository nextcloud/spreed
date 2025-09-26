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
import { isHiddenSystemMessage } from '../utils/message.ts'

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
 *
 * @param parentBlock
 * @param childBlock
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
 * Check, if message belongs to the current context (main chat or thread)
 *
 * @param message
 * @param threadId
 */
function checkIfBelongsToContext(message: ChatMessage, threadId?: number): boolean {
	return threadId
		// In thread context, only thread messages with given threadId are allowed
		? threadId === message.threadId
		// In main context, only non-thread messages, topmost thread messages and temporary messages are allowed
		: (!message.isThread || message.id === message.threadId || message.id.toString().startsWith('temp-'))
}

/**
 * Return an array of only numeric ids from given set
 * (temporary messages have a string id)
 *
 * @param block
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
	 *
	 * @param token
	 * @param payload
	 * @param payload.messageId
	 * @param payload.threadId
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
	 *
	 * @param token
	 * @param block
	 * @param threadId
	 */
	function prepareMessagesList(token: string, block: Set<number>, threadId?: number): ChatMessage[] {
		return Array.from(block).sort((a, b) => a - b)
			.reduce<ChatMessage[]>((acc, id) => {
				const message = store.state.messagesStore.messages[token][id]
				// Check for exceptions (message should not be added to the displayed list):
				// - non-visible system message
				// - completely deleted (expired) message
				// - thread message in general view (apart from the topmost one)
				if (message && !isHiddenSystemMessage(message)
					&& checkIfBelongsToContext(message, threadId)
				) {
					acc.push(message)
				}
				return acc
			}, [])
	}

	/**
	 * Returns whether message is known in any of blocks (then it exists in store)
	 *
	 * @param token
	 * @param payload
	 * @param payload.messageId
	 * @param payload.threadId
	 */
	function hasMessage(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): boolean {
		if (threadId) {
			if (!threadBlocks[token]?.[threadId]) {
				return false
			}
			return threadBlocks[token][threadId].findIndex((set) => set.has(messageId)) !== -1
		}

		if (!chatBlocks[token]) {
			return false
		}

		return chatBlocks[token].findIndex((set) => set.has(messageId)) !== -1
	}

	/**
	 * Returns first known message id, belonging to current context. Defaults to given messageId
	 *
	 * @param token
	 * @param payload
	 * @param payload.messageId
	 * @param payload.threadId
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
			if (hasMessage(token, { messageId: threadId, threadId })) {
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
	 *
	 * @param token
	 * @param payload
	 * @param payload.messageId
	 * @param payload.threadId
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
	 * Returns nearest known message id, belonging to current context
	 *
	 * @param token
	 * @param payload
	 * @param payload.messageId
	 * @param payload.threadId
	 */
	function getNearestKnownContextId(
		token: string,
		{ messageId = 0, threadId = 0 }: GetMessagesListOptions = { messageId: 0, threadId: 0 },
	): number | undefined {
		const message = store.state.messagesStore.messages[token][messageId]
		if (!message) {
			return undefined
		}

		if (checkIfBelongsToContext(message, threadId)) {
			return messageId
		}

		// Get last item from prepared messages list (already represents current context)
		return getMessagesList(token, { messageId, threadId }).at(-1)?.id
	}

	/**
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 *
	 * @param token
	 * @param messages
	 * @param options
	 */
	function processChatBlocks(token: string, messages: ChatMessage[], options?: ProcessChatBlocksOptions): void {
		const threadIdSetsToUpdate: IdMap<Set<number>> = {}
		const newMessageIdsSet = messages.reduce((acc, message) => {
			acc.add(message.id)
			if (message.isThread && message.threadId) {
				if (!threadIdSetsToUpdate[message.threadId]) {
					threadIdSetsToUpdate[message.threadId] = new Set<number>()
				}
				threadIdSetsToUpdate[message.threadId].add(message.id)
			}
			return acc
		}, new Set<number>())

		if (options?.threadId) {
			processThreadBlocks(token, options.threadId, newMessageIdsSet, options)
			return
		}

		if (options?.mergeBy) {
			newMessageIdsSet.add(options.mergeBy)

			const threadIds = Object.keys(threadIdSetsToUpdate)
			if (threadIds.length) {
				const chatBlockWithMergeBy: Set<number> | undefined = chatBlocks[token]?.find((set) => set.has(options.mergeBy!))
				if (chatBlockWithMergeBy) {
					// Populate thread blocks from chat blocks
					threadIds.forEach((threadId) => {
						for (const messageId of chatBlockWithMergeBy) {
							const message = store.state.messagesStore.messages[token][messageId]
							if (message && message.threadId === +threadId) {
								threadIdSetsToUpdate[message.threadId].add(messageId)
								break
							}
						}
					})
				}
			}
		}

		chatBlocks[token] = mergeAndSortChatBlocks(chatBlocks[token], newMessageIdsSet)
		Object.entries(threadIdSetsToUpdate).forEach(([threadId, threadMessageIdsSet]) => {
			processThreadBlocks(token, threadId, threadMessageIdsSet)
		})
	}

	/**
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 *
	 * @param token
	 * @param threadId
	 * @param threadMessagesSet
	 * @param options
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
	 *
	 * @param blocks
	 * @param unsortedBlock
	 */
	function mergeAndSortChatBlocks(blocks: Set<number>[] | undefined, unsortedBlock: Set<number>): Set<number>[] {
		if (!blocks || blocks.length === 0) {
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
	 *
	 * @param parentBlock
	 * @param childBlock
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
	 *
	 * @param token
	 * @param message
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
	 *
	 * @param token
	 * @param messageIds
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
	 *
	 * @param token
	 * @param idToDelete
	 */
	function clearMessagesHistory(token: string, idToDelete: number) {
		if (!chatBlocks[token]) {
			return
		}

		const deleteIndex = chatBlocks[token].findIndex((block) => Math.max(...block) < idToDelete)
		if (deleteIndex === 0) {
			// If first block is to be deleted, remove all blocks
			delete chatBlocks[token]
		} else if (deleteIndex !== -1) {
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
			if (deleteIndex === 0) {
				// If first block is to be deleted, remove all blocks (simply not copying)
			} else if (deleteIndex !== -1) {
				// Remove all blocks with max id less than given id
				newThreadBlocks[threadId] = threadBlocks[token][threadId].slice(0, deleteIndex)
				const lastBlock = newThreadBlocks[threadId].at(-1)!
				for (const id of lastBlock) {
					if (id < idToDelete) {
						lastBlock.delete(id)
					}
				}
			} else {
				// Not found, nothing to delete (copying as-is)
				newThreadBlocks[threadId] = threadBlocks[token][threadId]
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
	 *
	 * @param token
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
		getNearestKnownContextId,
		processChatBlocks,
		addMessageToChatBlocks,
		removeMessagesFromChatBlocks,
		clearMessagesHistory,
		purgeChatStore,
	}
})

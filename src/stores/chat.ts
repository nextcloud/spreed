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

	/**
	 * Returns list of messages, belonging to current context
	 */
	function getMessagesList(token: string): ChatMessage[] {
		if (!store.state.messagesStore.messages[token] || !chatBlocks[token]) {
			return []
		}

		// FIXME temporary show all messages from al blocks - no behaviour change
		return Array.from(chatBlocks[token].flatMap((set) => Array.from(set)))
			.sort((a, b) => a - b)
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
	 * Populate chat blocks from given arrays of messages
	 * If blocks already exist, try to extend them
	 */
	function processChatBlocks(token: string, messages: ChatMessage[], options?: ProcessChatBlocksOptions): void {
		const newMessageIds = messages.map((message) => message.id)
		const newMessageIdsSet = new Set(newMessageIds)

		if (!chatBlocks[token]) {
			// If no blocks exist, create a new one with the first message. First in array will be considered main block
			chatBlocks[token] = [newMessageIdsSet]
			return
		}

		if (options?.mergeBy) {
			newMessageIdsSet.add(options.mergeBy)
		}

		chatBlocks[token] = mergeAndSortChatBlocks(chatBlocks[token], newMessageIdsSet)
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

	return {
		chatBlocks,

		getMessagesList,
		processChatBlocks,
	}
})

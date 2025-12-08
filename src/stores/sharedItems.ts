/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	SharedItems,
	SharedItemsOverview,
} from '../types/index.ts'

import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { getSharedItems, getSharedItemsOverview } from '../services/sharedItemsService.ts'
import { getItemTypeFromMessage } from '../utils/getItemTypeFromMessage.ts'

type SharedItemType = keyof SharedItemsOverview

type SharedItemsPoolType = Record<string, Record<SharedItemType, Record<number, SharedItems[keyof SharedItems]>>>

/**
 * Store for shared items shown in RightSidebar
 */
export const useSharedItemsStore = defineStore('sharedItems', () => {
	const sharedItemsPool = reactive<SharedItemsPoolType>({})
	const overviewLoaded = reactive<Record<string, boolean>>({})

	/**
	 * Returns shared items for a given conversation token
	 *
	 * @param token
	 */
	function sharedItems(token: string) {
		if (!sharedItemsPool[token]) {
			sharedItemsPool[token] = {}
		}

		return sharedItemsPool[token]
	}

	/**
	 * Ensure the existence of shared items pool for given token and type
	 *
	 * @param token
	 * @param type
	 */
	function checkForExistence(token: string, type: SharedItemType) {
		if (token && !sharedItemsPool[token]) {
			sharedItemsPool[token] = {}
		}
		if (type && !sharedItemsPool[token][type]) {
			sharedItemsPool[token][type] = {}
		}
	}

	/**
	 * Process server response and add shared items to the store
	 *
	 * @param token conversation token
	 * @param data server response
	 */
	function addSharedItemsFromOverview(token: string, data: SharedItemsOverview) {
		for (const type of Object.keys(data)) {
			if (Object.keys(data[type]).length) {
				checkForExistence(token, type)
				for (const message of data[type]) {
					sharedItemsPool[token][type][message.id] = message
				}
			}
		}

		overviewLoaded[token] = true
	}

	/**
	 *  Add a shared item from a message to the store
	 *
	 * @param token conversation token
	 * @param message message with shared items
	 */
	function addSharedItemFromMessage(token: string, message: ChatMessage) {
		const type = getItemTypeFromMessage(message)
		checkForExistence(token, type)
		sharedItemsPool[token][type][message.id] = message
	}

	/**
	 * Delete a shared item from a message in the store
	 *
	 * @param token conversation token
	 * @param messageId id of message to be deleted
	 */
	function deleteSharedItemFromMessage(token: string, messageId: number) {
		if (!sharedItemsPool[token]) {
			return
		}

		for (const type of Object.keys(sharedItemsPool[token])) {
			if (sharedItemsPool[token][type][messageId]) {
				delete sharedItemsPool[token][type][messageId]
				if (Object.keys(sharedItemsPool[token][type]).length === 0) {
					delete sharedItemsPool[token][type]
				}
			}
		}
	}

	/**
	 *  Add shared items from multiple messages to the store
	 *
	 * @param token conversation token
	 * @param type type of shared item
	 * @param messages message with shared items
	 */
	function addSharedItemsFromMessages(token: string, type: string, messages: ChatMessage[]) {
		checkForExistence(token, type)

		messages.forEach((message) => {
			sharedItemsPool[token][type][message.id] = message
		})
	}

	/**
	 * Purge shared items from the store
	 *
	 * @param token conversation token
	 * @param messageId starting message id to purge shared items from older messages
	 * If messageId is not provided, all shared items in this conversation will be deleted.
	 */
	function purgeSharedItemsStore(token: string, messageId: number | null = null) {
		if (!sharedItemsPool[token]) {
			return
		}
		if (messageId) {
			// Delete older messages starting from messageId
			for (const type of Object.keys(sharedItemsPool[token])) {
				for (const id of Object.keys(sharedItemsPool[token][type])) {
					if (+id < +messageId) {
						delete sharedItemsPool[token][type][+id]
					}
				}
				if (Object.keys(sharedItemsPool[token][type]).length === 0) {
					delete sharedItemsPool[token][type]
				}
			}
			if (Object.keys(sharedItemsPool[token]).length === 0) {
				delete sharedItemsPool[token]
			}
		} else {
			delete sharedItemsPool[token]
		}
	}

	/**
	 * Fetch shared items of a specific type for a conversation
	 *
	 * @param token conversation token
	 * @param type type of shared item
	 */
	async function fetchSharedItems(token: string, type: string): Promise<{ hasMoreItems: boolean, messages: ChatMessage[] }> {
		// function is called from Message or SharedItemsBrowser, poll should not be empty at the moment
		if (!sharedItemsPool[token] || !sharedItemsPool[token][type]) {
			console.error(`Missing shared items poll of type '${type}' in conversation ${token}`)
			return { hasMoreItems: false, messages: [] }
		}

		const limit = 20
		const lastKnownMessageId = Math.min(...Object.keys(sharedItemsPool[token][type]).map(Number))
		try {
			const response = await getSharedItems({ token, objectType: type, lastKnownMessageId, limit })
			const messages = Object.values(response.data.ocs.data)
			if (messages.length) {
				addSharedItemsFromMessages(token, type, messages)
			}
			return { hasMoreItems: messages.length >= limit, messages }
		} catch (error) {
			console.error(error)
			return { hasMoreItems: false, messages: [] }
		}
	}

	/**
	 * Fetch shared items overview for a conversation
	 *
	 * @param token conversation token
	 */
	async function fetchSharedItemsOverview(token: string) {
		if (overviewLoaded[token]) {
			return
		}

		try {
			const response = await getSharedItemsOverview({ token, limit: 7 })
			addSharedItemsFromOverview(token, response.data.ocs.data)
		} catch (error) {
			console.error(error)
		}
	}

	return {
		sharedItemsPool,
		overviewLoaded,
		sharedItems,
		checkForExistence,
		addSharedItemsFromOverview,
		addSharedItemFromMessage,
		deleteSharedItemFromMessage,
		addSharedItemsFromMessages,
		purgeSharedItemsStore,
		fetchSharedItems,
		fetchSharedItemsOverview,
	}
})

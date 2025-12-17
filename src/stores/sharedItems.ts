/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	PinnedChatMessage,
	SharedItems,
	SharedItemsOverview,
} from '../types/index.ts'

import { defineStore } from 'pinia'
import { reactive } from 'vue'
import { useStore } from 'vuex'
import { SHARED_ITEM } from '../constants.ts'
import {
	hidePinnedMessage,
	pinMessage,
	unpinMessage,
} from '../services/messagesService.ts'
import { getSharedItems, getSharedItemsOverview } from '../services/sharedItemsService.ts'
import { getItemTypeFromMessage } from '../utils/getItemTypeFromMessage.ts'

type SharedItemType = keyof SharedItemsOverview

type SharedItemsPoolType = Record<string, {
	[K: string]: Record<number, SharedItems[keyof SharedItems]>
}>

type MetaData = PinnedChatMessage['metaData']

/**
 * Store for shared items shown in RightSidebar
 */
export const useSharedItemsStore = defineStore('sharedItems', () => {
	const store = useStore()

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
	 * @param type type of shared item (optional)
	 */
	function addSharedItemFromMessage(token: string, message: ChatMessage, type?: SharedItemType) {
		const itemType = type ?? getItemTypeFromMessage(message)
		checkForExistence(token, itemType)
		sharedItemsPool[token][itemType][message.id] = message
	}

	/**
	 * Delete a shared item from a message in the store
	 *
	 * @param token conversation token
	 * @param messageId id of message to be deleted
	 * @param type type of shared item (optional)
	 */
	function deleteSharedItemFromMessage(token: string, messageId: number, type?: SharedItemType) {
		if (!sharedItemsPool[token]) {
			return
		}

		// If type is not provided, search in all types
		const typesToDelete = type ? [type] : Object.keys(sharedItemsPool[token])

		for (const type of typesToDelete) {
			if (sharedItemsPool[token][type]?.[messageId]) {
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
	 *  Purge expired shared items from the store
	 *
	 * @param token
	 * @param timestamp
	 */
	function purgeExpiredSharedItems(token: string, timestamp: number) {
		if (!sharedItemsPool[token]) {
			return
		}

		for (const type of Object.keys(sharedItemsPool[token])) {
			for (const id of Object.keys(sharedItemsPool[token][type])) {
				const message = sharedItemsPool[token][type][+id]
				if (message.expirationTimestamp && message.expirationTimestamp < timestamp) {
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

	/**
	 * Update other stores with pin information (conversation store and messages store).
	 *
	 * @param data
	 * @param data.token
	 * @param data.messageId
	 * @param data.metaData
	 * @param data.action
	 */
	function updateOtherStoresWithPinInformation({ token, messageId, metaData, action }: { token: string, messageId: number, metaData?: MetaData, action?: 'pin' | 'unpin' }) {
		const mostRecentPinnedId = findMostRecentPinnedMessageId(token)
		store.dispatch('setConversationProperties', {
			token,
			properties: {
				lastPinnedId: mostRecentPinnedId ?? 0,
			},
		})
		store.dispatch('updateMessageMetadata', {
			token,
			id: messageId,
			metaData: (action === 'pin' && metaData) ? metaData : {},
		})
	}

	/**
	 * Pin a message for everyone
	 *
	 * @param token
	 * @param messageId
	 * @param pinUntil
	 */
	async function handlePinMessage(token: string, messageId: number, pinUntil?: number) {
		try {
			const response = await pinMessage({ token, messageId, pinUntil })
			const pinnedMessage = response.data.ocs.data?.parent
			if (!pinnedMessage || 'deleted' in pinnedMessage) {
				return
			}
			addSharedItemFromMessage(token, pinnedMessage, SHARED_ITEM.TYPES.PINNED)
			updateOtherStoresWithPinInformation({ token, messageId, metaData: pinnedMessage.metaData, action: 'pin' })
		} catch (error) {
			console.error('Error while toggling pin message:', error)
		}
	}

	/**
	 * Find most recent pinned message
	 *
	 * @param token
	 */
	function findMostRecentPinnedMessageId(token: string): number | null {
		const pinnedMessages = Object.values(sharedItemsPool[token]?.pinned ?? {})
		if (!pinnedMessages.length) {
			return null
		}
		const { id } = pinnedMessages.reduce<{ id: number | null, pinnedAt: number }>((acc, message) => {
			const messagePinnedAt = message.metaData!.pinnedAt!
			if (messagePinnedAt > acc.pinnedAt) {
				return { id: message.id, pinnedAt: messagePinnedAt }
			}
			return acc
		}, { id: null, pinnedAt: 0 })
		return id
	}

	/**
	 * Unpin a message for everyone
	 *
	 * @param token
	 * @param messageId
	 */
	async function handleUnpinMessage(token: string, messageId: number) {
		try {
			await unpinMessage({ token, messageId })
			deleteSharedItemFromMessage(token, messageId, SHARED_ITEM.TYPES.PINNED)
			updateOtherStoresWithPinInformation({ token, messageId, action: 'unpin' })
		} catch (error) {
			console.error('Error while unpinning message:', error)
		}
	}

	/**
	 * fetches pinned messages
	 *
	 * @param token
	 */
	async function fetchPinnedMessages(token: string) {
		try {
			const response = await getSharedItems({ token, objectType: SHARED_ITEM.TYPES.PINNED, limit: 5 })
			const messages = Object.values(response.data.ocs.data)
			if (messages.length) {
				addSharedItemsFromMessages(token, SHARED_ITEM.TYPES.PINNED, messages)
			}
		} catch (error) {
			console.error(error)
		}
	}

	/**
	 * hide pinned message for self
	 *
	 * @param token
	 * @param messageId
	 */
	async function handleHidePinnedMessage(token: string, messageId: number) {
		try {
			await hidePinnedMessage({ token, messageId })
			// Instant update conversation hiddenPinnedId
			await store.dispatch('setConversationProperties', {
				token,
				properties: {
					hiddenPinnedId: messageId,
				},
			})
		} catch (error) {
			console.error('Error while hiding pinned message:', error)
		}
	}

	return {
		sharedItemsPool,
		overviewLoaded,
		sharedItems,
		checkForExistence,
		addSharedItemsFromOverview,
		addSharedItemFromMessage,
		addSharedItemsFromMessages,
		deleteSharedItemFromMessage,
		purgeExpiredSharedItems,
		purgeSharedItemsStore,
		fetchSharedItems,
		fetchSharedItemsOverview,
		handlePinMessage,
		handleUnpinMessage,
		handleHidePinnedMessage,
		fetchPinnedMessages,
		findMostRecentPinnedMessageId,
		updateOtherStoresWithPinInformation,
	}
})

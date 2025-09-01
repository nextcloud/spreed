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
import { getSharedItems, getSharedItemsOverview } from '../services/sharedItemsService.ts'
import { getItemTypeFromMessage } from '../utils/getItemTypeFromMessage.ts'

type SharedItemType = keyof SharedItemsOverview

type SharedItemsPoolType = Record<string, Record<SharedItemType, Record<number, SharedItems[keyof SharedItems]>>>

type State = {
	sharedItemsPool: SharedItemsPoolType
	overviewLoaded: Record<string, boolean>
}

/**
 * Store for shared items shown in RightSidebar
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useSharedItemsStore = defineStore('sharedItems', {
	state: (): State => ({
		sharedItemsPool: {},
		overviewLoaded: {},
	}),

	getters: {
		sharedItems: (state) => (token: string) => {
			if (!state.sharedItemsPool[token]) {
				state.sharedItemsPool[token] = {}
			}

			return state.sharedItemsPool[token]
		},
	},

	actions: {
		checkForExistence(token: string, type: SharedItemType) {
			if (token && !this.sharedItemsPool[token]) {
				this.sharedItemsPool[token] = {}
			}
			if (type && !this.sharedItemsPool[token][type]) {
				this.sharedItemsPool[token][type] = {}
			}
		},

		/**
		 * @param token conversation token
		 * @param data server response
		 */
		addSharedItemsFromOverview(token: string, data: SharedItemsOverview) {
			for (const type of Object.keys(data)) {
				if (Object.keys(data[type]).length) {
					this.checkForExistence(token, type)
					for (const message of data[type]) {
						if (!this.sharedItemsPool[token][type][message.id]) {
							this.sharedItemsPool[token][type][message.id] = message
						}
					}
				}
			}

			this.overviewLoaded[token] = true
		},

		/**
		 * @param token conversation token
		 * @param message message with shared items
		 */
		addSharedItemFromMessage(token: string, message: ChatMessage) {
			const type = getItemTypeFromMessage(message)
			this.checkForExistence(token, type)

			if (!this.sharedItemsPool[token][type][message.id]) {
				this.sharedItemsPool[token][type][message.id] = message
			}
		},

		/**
		 * @param token conversation token
		 * @param messageId id of message to be deleted
		 */
		deleteSharedItemFromMessage(token: string, messageId: number) {
			if (!this.sharedItemsPool[token]) {
				return
			}

			for (const type of Object.keys(this.sharedItemsPool[token])) {
				if (this.sharedItemsPool[token][type][messageId]) {
					delete this.sharedItemsPool[token][type][messageId]
					if (Object.keys(this.sharedItemsPool[token][type]).length === 0) {
						delete this.sharedItemsPool[token][type]
					}
				}
			}
		},

		/**
		 * @param token conversation token
		 * @param type type of shared item
		 * @param messages message with shared items
		 */
		addSharedItemsFromMessages(token: string, type: string, messages: SharedItems[keyof SharedItems][]) {
			this.checkForExistence(token, type)

			messages.forEach((message) => {
				if (!this.sharedItemsPool[token][type][message.id]) {
					this.sharedItemsPool[token][type][message.id] = message
				}
			})
		},

		/**
		 * @param token conversation token
		 * @param messageId starting message id to purge shared items from older messages
		 * If messageId is not provided, all shared items in this conversation will be deleted.
		 */
		purgeSharedItemsStore(token: string, messageId: number | null = null) {
			if (!this.sharedItemsPool[token]) {
				return
			}
			if (messageId) {
				// Delete older messages starting from messageId
				for (const type of Object.keys(this.sharedItemsPool[token])) {
					for (const id of Object.keys(this.sharedItemsPool[token][type])) {
						if (+id < +messageId) {
							delete this.sharedItemsPool[token][type][+id]
						}
					}
					if (Object.keys(this.sharedItemsPool[token][type]).length === 0) {
						delete this.sharedItemsPool[token][type]
					}
				}
				if (Object.keys(this.sharedItemsPool[token]).length === 0) {
					delete this.sharedItemsPool[token]
				}
			} else {
				delete this.sharedItemsPool[token]
			}
		},

		/**
		 * @param token conversation token
		 * @param type type of shared item
		 */
		async getSharedItems(token: string, type: string) {
			// function is called from Message or SharedItemsBrowser, poll should not be empty at the moment
			if (!this.sharedItemsPool[token] || !this.sharedItemsPool[token][type]) {
				console.error(`Missing shared items poll of type '${type}' in conversation ${token}`)
				return { hasMoreItems: false, messages: [] }
			}

			const limit = 20
			const lastKnownMessageId = Math.min(...Object.keys(this.sharedItemsPool[token][type]).map(Number))
			try {
				const response = await getSharedItems({ token, objectType: type, lastKnownMessageId, limit })
				const messages = Object.values(response.data.ocs.data)
				if (messages.length) {
					this.addSharedItemsFromMessages(token, type, messages)
				}
				return { hasMoreItems: messages.length >= limit, messages }
			} catch (error) {
				console.error(error)
				return { hasMoreItems: false, messages: [] }
			}
		},

		/**
		 * @param token conversation token
		 */
		async getSharedItemsOverview(token: string) {
			if (this.overviewLoaded[token]) {
				return
			}

			try {
				const response = await getSharedItemsOverview({ token, limit: 7 })
				this.addSharedItemsFromOverview(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
			}
		},
	},
})

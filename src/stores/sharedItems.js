/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'
import { getSharedItems, getSharedItemsOverview } from '../services/sharedItemsService.js'
import { getItemTypeFromMessage } from '../utils/getItemTypeFromMessage.ts'

/**
 * @typedef {'media'|'file'|'voice'|'audio'|'location'|'deckcard'|'other'} Type
 * @typedef {string} Token
 */

/**
 * @typedef {object} Message
 * @property {string} token - conversation token
 * @property {number} id - message id
 */

/**
 * @typedef {object} Messages
 * @property {{[key: number]: Message}} messages - messages with shared items for this conversation
 */

/**
 * @typedef {object} State
 * @property {{[key: Token]: {[key: Type]: Messages}}} sharedItemsPool - The shared items pool.
 * @property {{[key: Token]: boolean}} overviewLoaded - The overview loaded state.
 */

/**
 * Store for shared items shown in RightSidebar
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useSharedItemsStore = defineStore('sharedItems', {
	state: () => ({
		sharedItemsPool: {},
		overviewLoaded: {},
	}),

	getters: {
		sharedItems: (state) => (token) => {
			if (!state.sharedItemsPool[token]) {
				Vue.set(state.sharedItemsPool, token, {})
			}

			return state.sharedItemsPool[token]
		},
	},

	actions: {
		checkForExistence(token, type) {
			if (token && !this.sharedItemsPool[token]) {
				Vue.set(this.sharedItemsPool, token, {})
			}
			if (type && !this.sharedItemsPool[token][type]) {
				Vue.set(this.sharedItemsPool[token], type, {})
			}
		},

		/**
		 * @param {Token} token conversation token
		 * @param {{[key: Type]: Message[]}} data server response
		 */
		addSharedItemsFromOverview(token, data) {
			for (const type of Object.keys(data)) {
				if (Object.keys(data[type]).length) {
					this.checkForExistence(token, type)
					for (const message of data[type]) {
						if (!this.sharedItemsPool[token][type][message.id]) {
							Vue.set(this.sharedItemsPool[token][type], message.id, message)
						}
					}
				}
			}

			Vue.set(this.overviewLoaded, token, true)
		},

		/**
		 * @param {Token} token conversation token
		 * @param {Message} message message with shared items
		 */
		addSharedItemFromMessage(token, message) {
			const type = getItemTypeFromMessage(message)
			this.checkForExistence(token, type)

			if (!this.sharedItemsPool[token][type][message.id]) {
				Vue.set(this.sharedItemsPool[token][type], message.id, message)
			}
		},

		/**
		 * @param {Token} token conversation token
		 * @param {Type} type type of shared item
		 * @param {Message[]} messages message with shared items
		 */
		addSharedItemsFromMessages(token, type, messages) {
			this.checkForExistence(token, type)

			messages.forEach((message) => {
				if (!this.sharedItemsPool[token][type][message.id]) {
					Vue.set(this.sharedItemsPool[token][type], message.id, message)
				}
			})
		},

		/**
		 * @param {Token} token conversation token
		 * @param {Type} type type of shared item
		 */
		async getSharedItems(token, type) {
			// function is called from Message or SharedItemsBrowser, poll should not be empty at the moment
			if (!this.sharedItemsPool[token] || !this.sharedItemsPool[token][type]) {
				console.error(`Missing shared items poll of type '${type}' in conversation ${token}`)
				return { hasMoreItems: false, messages: [] }
			}

			const limit = 20
			const lastKnownMessageId = Math.min.apply(Math, Object.keys(this.sharedItemsPool[token][type]))
			try {
				const response = await getSharedItems(token, type, lastKnownMessageId, limit)
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
		 * @param {Token} token conversation token
		 */
		async getSharedItemsOverview(token) {
			if (this.overviewLoaded[token]) {
				return
			}

			try {
				const response = await getSharedItemsOverview(token, 7)
				this.addSharedItemsFromOverview(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
			}
		},
	},
})

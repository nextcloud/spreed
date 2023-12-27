/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { getSharedItemsOverview, getSharedItems } from '../services/sharedItemsService.js'
import { getItemTypeFromMessage } from '../utils/getItemTypeFromMessage.js'

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
		 * @param {Message} message message with shared items
		 */
		addSharedItemFromMessage(message) {
			const token = message.token
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

			messages.forEach(message => {
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

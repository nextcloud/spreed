/**
 * @copyright Copyright (c) 2023 Dorra Jaouad <dorra.jaoued7@gmail.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 * @author Dorra Jaouad <dorra.jaoued7@gmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import {
	getReactionsDetails,
} from '../services/messagesService.js'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {number} MessageId
 */

/**
 * @typedef {object} Reactions
 * @property {string} emoji - reaction emoji
 * @property {object} participant - reacting participant
 */

/**
 * @typedef {object} State
 * @property {{[key: Token]: {[key: MessageId]: Reactions}}} reactions - The reactions per message.
 */

/**
 * Store for conversation extra chat features apart from messages
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useReactionsStore = defineStore('reactions', {
	state: () => ({
		reactions: {},
	}),

	getters: {
		getReactions: (state) => (token, messageId) => {
			if (state.reactions?.[token]?.[messageId]) {
				return state.reactions[token][messageId]
			} else {
				return undefined
			}
		},

		reactionsLoaded: (state) => (token, messageId) => {
			if (state.reactions?.[token]?.[messageId]) {
				return true
			} else {
				return false
			}
		},
	},

	actions: {
		/**
		 * Adds reactions for a given message.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {object} payload.reactions The list of reactions with details for a given message
		 *
		 */
		addReactions({ token, messageId, reactions }) {
			if (!this.reactions[token]) {
				Vue.set(this.reactions, token, {})

			}
			Vue.set(this.reactions[token], messageId, reactions)
		},

		/**
		 * Delete all reactions for a given message.
		 *
		 * @param {string} token The conversation token
		 * @param {number} messageId The id of message
		 *
		 */
		resetReactions(token, messageId) {
			if (!this.reactions[token]) {
				Vue.set(this.reactions, token, {})
			}
			Vue.delete(this.reactions[token], messageId)
		},

		/**
		 * Updates reactions for a given message.
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.messageId The id of message
		 * @param {object} payload.reactionsDetails The list of reactions with details for a given message
		 *
		 */
		updateReactions({ token, messageId, reactionsDetails }) {
			// TODO: patch reactions instead of replacing them
			this.addReactions({
				token,
				messageId,
				reactions: reactionsDetails,
			})
		},

		/**
		 * Gets the full reactions list for a given message.
		 *
		 * @param {string} token The conversation token
		 * @param {number} messageId The id of message
		 */
		async fetchReactions(token, messageId) {
			console.debug('getting reactions details')
			try {
				const response = await getReactionsDetails(token, messageId)
				this.addReactions({
					token,
					messageId,
					reactions: response.data.ocs.data,
				})

				return response
			} catch (error) {
				console.debug(error)
			}
		},

	},
})

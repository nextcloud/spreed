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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { getUserAbsence } from '../services/participantsService.js'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {object} State
 * @property {{[key: Token]: object}} absence - The absence status per conversation.
 * @property {{[key: Token]: number}} messagesToBeReplied - The parent message id to reply per conversation.
 * @property {{[key: Token]: string}} currentMessageInput -The input value per conversation.
 */

/**
 * Store for conversation extra chat features apart from messages
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useChatExtrasStore = defineStore('chatExtras', {
	state: () => ({
		absence: {},
		messagesToBeReplied: {},
		currentMessageInput: {},
	}),

	getters: {
		getMessageToBeReplied: (state) => (token) => {
			if (state.messagesToBeReplied[token]) {
				return state.messagesToBeReplied[token]
			}
		},

		getCurrentMessageInput: (state) => (token) => {
			return state.currentMessageInput[token] ?? ''
		},
	},

	actions: {
		/**
		 * Fetch an absence status for user and save to store
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {string} payload.userId The id of user
		 *
		 */
		async getUserAbsence({ token, userId }) {
			try {
				const response = await getUserAbsence(userId)
				Vue.set(this.absence, token, response.data.ocs.data)
				return this.absence[token]
			} catch (error) {
				if (error?.response?.status === 404) {
					Vue.set(this.absence, token, null)
					return null
				}
				console.error(error)
			}
		},

		/**
		 * Drop an absence status from the store
		 *
		 * @param {string} token The conversation token
		 *
		 */
		removeUserAbsence(token) {
			if (this.absence[token]) {
				Vue.delete(this.absence, token)
			}
		},

		/**
		 * Add a reply message id to the store
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.id The id of message
		 */
		addMessageToBeReplied({ token, id }) {
			Vue.set(this.messagesToBeReplied, token, id)
		},

		/**
		 * Removes a reply message id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param {string} token The conversation token
		 */
		removeMessageToBeReplied(token) {
			Vue.delete(this.messagesToBeReplied, token)
		},

		/**
		 * Add a current input value to the store for a given conversation token
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {string} payload.text The string to store
		 */
		setCurrentMessageInput({ token, text }) {
			// FIXME upstream: https://github.com/nextcloud-libraries/nextcloud-vue/issues/4492
			const temp = document.createElement('textarea')
			temp.innerHTML = text.replace(/&/gmi, '&amp;')
			const parsedText = temp.value.replace(/&amp;/gmi, '&').replace(/&lt;/gmi, '<')
				.replace(/&gt;/gmi, '>').replace(/&sect;/gmi, '§')

			Vue.set(this.currentMessageInput, token, parsedText)
		},

		/**
		 * Remove a current input value from the store for a given conversation token
		 *
		 * @param {string} token The conversation token
		 */
		removeCurrentMessageInput(token) {
			Vue.delete(this.currentMessageInput, token)
		},

		/**
		 * Clears store for a deleted conversation
		 *
		 * @param {string} token the token of the conversation to be deleted
		 */
		purgeChatExtras(token) {
			this.removeMessageToBeReplied(token)
			this.removeUserAbsence(token)
			this.removeCurrentMessageInput(token)
		},
	},
})

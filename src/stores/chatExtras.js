/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import BrowserStorage from '../services/BrowserStorage.js'
import { EventBus } from '../services/EventBus.js'
import { getUserAbsence } from '../services/participantsService.js'
import { parseSpecialSymbols, parseMentions } from '../utils/textParse.ts'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {object} State
 * @property {{[key: Token]: object}} absence - The absence status per conversation.
 * @property {{[key: Token]: number}} parentToReply - The parent message id to reply per conversation.
 * @property {{[key: Token]: string}} chatInput -The input value per conversation.
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
		parentToReply: {},
		chatInput: {},
		messageIdToEdit: {},
		chatEditInput: {},
	}),

	getters: {
		getParentIdToReply: (state) => (token) => {
			if (state.parentToReply[token]) {
				return state.parentToReply[token]
			}
		},

		getChatEditInput: (state) => (token) => {
			return state.chatEditInput[token] ?? ''
		},

		getMessageIdToEdit: (state) => (token) => {
			return state.messageIdToEdit[token]
		},
	},

	actions: {
		/**
		 * Fetch an absence status for user and save to store
		 *
		 * @param {string} token The conversation token
		 * @return {string} The input text
		 */
		getChatInput(token) {
			if (!this.chatInput[token]) {
				this.restoreChatInput(token)
			}
			return this.chatInput[token] ?? ''
		},

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
				this.absence[token] = response.data.ocs.data
				return this.absence[token]
			} catch (error) {
				if (error?.response?.status === 404) {
					this.absence[token] = null
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
				delete this.absence[token]
			}
		},

		/**
		 * Add a reply message id to the store
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.id The id of message
		 */
		setParentIdToReply({ token, id }) {
			this.parentToReply[token] = id
		},

		/**
		 * Removes a reply message id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param {string} token The conversation token
		 */
		removeParentIdToReply(token) {
			delete this.parentToReply[token]
		},

		/**
		 * Restore chat input from the browser storage and save to store
		 *
		 * @param {string} token The conversation token
		 */
		restoreChatInput(token) {
			const chatInput = BrowserStorage.getItem('chatInput_' + token)
			if (chatInput) {
				this.chatInput[token] = chatInput
			}
		},

		/**
		 * Add a current input value to the store for a given conversation token
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {string} payload.text The string to store
		 */
		setChatInput({ token, text }) {
			const parsedText = parseSpecialSymbols(text)
			BrowserStorage.setItem('chatInput_' + token, parsedText)
			this.chatInput[token] = parsedText
		},

		/**
		 * Add a message text that is being edited to the store for a given conversation token
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {string} payload.text The string to store
		 * @param {object} payload.parameters message parameters
		 */
		setChatEditInput({ token, text, parameters = {} }) {
			let parsedText = text

			// Handle mentions and special symbols
			parsedText = parseMentions(parsedText, parameters)
			parsedText = parseSpecialSymbols(parsedText)

			this.chatEditInput[token] = parsedText
		},

		/**
		 * Add a message id that is being edited to the store
		 *
		 * @param {string} token The conversation token
		 * @param {number} id The id of message
		 */
		setMessageIdToEdit(token, id) {
			this.messageIdToEdit[token] = id
		},

		/**
		 * Remove a message id that is being edited to the store
		 *
		 * @param {string} token The conversation token
		 */
		removeMessageIdToEdit(token) {
			delete this.chatEditInput[token]
			delete this.messageIdToEdit[token]
		},

		/**
		 * Remove a current input value from the store for a given conversation token
		 *
		 * @param {string} token The conversation token
		 */
		removeChatInput(token) {
			BrowserStorage.removeItem('chatInput_' + token)
			delete this.chatInput[token]
		},

		initiateEditingMessage({ token, id, message, messageParameters }) {
			this.setMessageIdToEdit(token, id)
			const isFileShareOnly = Object.keys(Object(messageParameters)).some(key => key.startsWith('file'))
				&& message === '{file}'
			if (isFileShareOnly) {
				this.setChatEditInput({ token, text: '' })
			} else {
				this.setChatEditInput({
					token,
					text: message,
					parameters: messageParameters
				})
			}
			EventBus.emit('editing-message')
			EventBus.emit('focus-chat-input')
		},

		/**
		 * Clears store for a deleted conversation
		 *
		 * @param {string} token the token of the conversation to be deleted
		 */
		purgeChatExtras(token) {
			this.removeParentIdToReply(token)
			this.removeUserAbsence(token)
			this.removeChatInput(token)
		},
	},
})

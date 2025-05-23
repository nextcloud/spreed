/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { t } from '@nextcloud/l10n'

import BrowserStorage from '../services/BrowserStorage.js'
import { EventBus } from '../services/EventBus.ts'
import { summarizeChat } from '../services/messagesService.ts'
import { parseSpecialSymbols, parseMentions } from '../utils/textParse.ts'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {object} State
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
		parentToReply: {},
		chatInput: {},
		messageIdToEdit: {},
		chatEditInput: {},
		tasksCount: 0,
		tasksDoneCount: 0,
		chatSummary: {},
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

		getChatSummaryTaskQueue: (state) => (token) => {
			return Object.values(Object(state.chatSummary[token]))
		},

		hasChatSummaryTaskRequested: (state) => (token) => {
			return state.chatSummary[token] !== undefined
		},

		getChatSummary: (state) => (token) => {
			return Object.values(Object(state.chatSummary[token])).map((task) => task.summary).join('\n\n')
				|| t('spreed', 'Error occurred during a summary generation')
		},
	},

	actions: {
		/**
		 * Get chat input for current conversation (from store or BrowserStorage)
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
		 * Add a reply message id to the store
		 *
		 * @param {object} payload action payload
		 * @param {string} payload.token The conversation token
		 * @param {number} payload.id The id of message
		 */
		setParentIdToReply({ token, id }) {
			Vue.set(this.parentToReply, token, id)
		},

		/**
		 * Removes a reply message id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param {string} token The conversation token
		 */
		removeParentIdToReply(token) {
			Vue.delete(this.parentToReply, token)
		},

		/**
		 * Restore chat input from the browser storage and save to store
		 *
		 * @param {string} token The conversation token
		 */
		restoreChatInput(token) {
			const chatInput = BrowserStorage.getItem('chatInput_' + token)
			if (chatInput) {
				Vue.set(this.chatInput, token, chatInput)
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
			Vue.set(this.chatInput, token, parsedText)
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

			Vue.set(this.chatEditInput, token, parsedText)
		},

		/**
		 * Add a message id that is being edited to the store
		 *
		 * @param {string} token The conversation token
		 * @param {number} id The id of message
		 */
		setMessageIdToEdit(token, id) {
			Vue.set(this.messageIdToEdit, token, id)
		},

		/**
		 * Remove a message id that is being edited to the store
		 *
		 * @param {string} token The conversation token
		 */
		removeMessageIdToEdit(token) {
			Vue.delete(this.chatEditInput, token)
			Vue.delete(this.messageIdToEdit, token)
		},

		/**
		 * Remove a current input value from the store for a given conversation token
		 *
		 * @param {string} token The conversation token
		 */
		removeChatInput(token) {
			BrowserStorage.removeItem('chatInput_' + token)
			Vue.delete(this.chatInput, token)
		},

		initiateEditingMessage({ token, id, message, messageParameters }) {
			this.setMessageIdToEdit(token, id)
			const isFileShareOnly = Object.keys(Object(messageParameters)).some((key) => key.startsWith('file'))
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
			this.removeChatInput(token)
		},

		setTasksCounters({ tasksCount, tasksDoneCount }) {
			this.tasksCount = tasksCount
			this.tasksDoneCount = tasksDoneCount
		},

		async requestChatSummary(token, fromMessageId) {
			try {
				const response = await summarizeChat(token, fromMessageId)
				if (!response.data) {
					console.warn('No messages found to summarize:', { token, fromMessageId })
					return
				}
				const task = response.data.ocs.data

				if (!this.chatSummary[token]) {
					Vue.set(this.chatSummary, token, {})
				}
				Vue.set(this.chatSummary[token], fromMessageId, {
					...task,
					fromMessageId,
				})
				if (task.nextOffset && task.nextOffset !== fromMessageId) {
					await this.requestChatSummary(token, task.nextOffset)
				}
			} catch (error) {
				console.error('Error while requesting a summary:', error)
			}
		},

		storeChatSummary(token, fromMessageId, summary) {
			if (this.chatSummary[token][fromMessageId]) {
				Vue.set(this.chatSummary[token][fromMessageId], 'summary', summary)
			}
		},

		dismissChatSummary(token) {
			if (this.hasChatSummaryTaskRequested(token)) {
				Vue.delete(this.chatSummary, token)
			}
		},
	},
})

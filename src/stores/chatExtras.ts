/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	ChatMessage,
	ChatTask,
	ThreadInfo,
} from '../types/index.ts'

import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import BrowserStorage from '../services/BrowserStorage.js'
import { EventBus } from '../services/EventBus.ts'
import {
	getSingleThreadForConversation,
	getThreadsForConversation,
	makeThreadForConversation,
	summarizeChat,
} from '../services/messagesService.ts'
import { parseMentions, parseSpecialSymbols } from '../utils/textParse.ts'

type State = {
	threads: Record<string, Record<number, ThreadInfo>>
	threadsOffset: Record<string, number | undefined>
	threadToReply: Record<string, number>
	parentToReply: Record<string, number>
	chatInput: Record<string, string>
	messageIdToEdit: Record<string, number>
	chatEditInput: Record<string, string>
	tasksCount: number
	tasksDoneCount: number
	chatSummary: Record<string, Record<number, ChatTask>>
}

/**
 * Store for conversation extra chat features apart from messages
 */
export const useChatExtrasStore = defineStore('chatExtras', {
	state: (): State => ({
		threads: {},
		threadsOffset: {},
		threadToReply: {},
		parentToReply: {},
		chatInput: {},
		messageIdToEdit: {},
		chatEditInput: {},
		tasksCount: 0,
		tasksDoneCount: 0,
		chatSummary: {},
	}),

	getters: {
		getThread: (state) => (token: string, threadId: number) => {
			if (state.threads[token]?.[threadId]) {
				return state.threads[token][threadId]
			}
		},

		getThreadIdToReply: (state) => (token: string) => {
			if (state.threadToReply[token]) {
				return state.threadToReply[token]
			}
		},

		getParentIdToReply: (state) => (token: string) => {
			if (state.parentToReply[token]) {
				return state.parentToReply[token]
			}
		},

		getChatEditInput: (state) => (token: string) => {
			return state.chatEditInput[token] ?? ''
		},

		getMessageIdToEdit: (state) => (token: string) => {
			return state.messageIdToEdit[token]
		},

		getChatSummaryTaskQueue: (state) => (token: string) => {
			return Object.values(Object(state.chatSummary[token]) as State['chatSummary'][string])
		},

		hasChatSummaryTaskRequested: (state) => (token: string) => {
			return state.chatSummary[token] !== undefined
		},

		getChatSummary: (state) => (token: string) => {
			return Object.values(Object(state.chatSummary[token]) as State['chatSummary'][string]).map((task) => task.summary).join('\n\n')
				|| t('spreed', 'Error occurred during a summary generation')
		},
	},

	actions: {
		/**
		 * Update known threads offset for given conversation
		 *
		 * @param token - conversation token
		 * @param [offsetList] - known id or list of ids
		 */
		updateThreadsOffset(token: string, offsetList?: number | number[]) {
			const normalizedList: number[] = Array.isArray(offsetList) ? offsetList : [offsetList].filter(Boolean)
			if (normalizedList.length === 0) {
				return
			}

			if (!this.threadsOffset[token]) {
				this.threadsOffset[token] = Math.min(...normalizedList)
			} else {
				this.threadsOffset[token] = Math.min(...normalizedList, this.threadsOffset[token]!)
			}
		},

		/**
		 * Get a thread in given conversation
		 *
		 * @param token - conversation token
		 * @param threadId - thread id to fetch
		 */
		async getSingleThread(token: string, threadId: number) {
			try {
				if (!this.threads[token]) {
					this.threads[token] = {}
				}

				const response = await getSingleThreadForConversation(token, threadId)
				this.threads[token][threadId] = response.data.ocs.data
				this.updateThreadsOffset(token, threadId)
			} catch (error) {
				console.error('Error fetching threads:', error)
			}
		},

		/**
		 * Get list of threads for given conversation
		 *
		 * @param token - conversation token
		 * @param [offset] - optional offset for threads list
		 */
		async getThreadsList(token: string, offset?: number) {
			try {
				if (!this.threads[token]) {
					this.threads[token] = {}
				}

				const response = await getThreadsForConversation({ token, offsetId: offset ?? this.threadsOffset[token] })

				const threadIds: number[] = []
				response.data.ocs.data.forEach((threadInfo) => {
					this.threads[token][threadInfo.thread.id] = threadInfo
					threadIds.push(threadInfo.thread.id)
				})
				this.updateThreadsOffset(token, threadIds)
			} catch (error) {
				console.error('Error fetching threads:', error)
			}
		},

		/**
		 * Make reply chain a thread in given conversation
		 *
		 * @param token - conversation token
		 * @param threadId - thread id to fetch
		 */
		async makeThread(token: string, threadId: number) {
			try {
				if (!this.threads[token]) {
					this.threads[token] = {}
				}

				const response = await makeThreadForConversation(token, threadId)
				this.threads[token][threadId] = response.data.ocs.data
				this.updateThreadsOffset(token, threadId)
			} catch (error) {
				console.error('Error fetching threads:', error)
			}
		},

		/**
		 * Get chat input for current conversation (from store or BrowserStorage)
		 *
		 * @param token - conversation token
		 * @return The input text
		 */
		getChatInput(token: string) {
			if (!this.chatInput[token]) {
				this.restoreChatInput(token)
			}
			return this.chatInput[token] ?? ''
		},

		/**
		 * Add a reply message id to the store
		 *
		 * @param payload action payload
		 * @param payload.token - conversation token
		 * @param payload.id The id of message
		 * @param payload.threadId The thread id of message
		 */
		setParentIdToReply({ token, id, threadId }: { token: string, id: number, threadId?: number }) {
			this.parentToReply[token] = id
			if (threadId) {
				this.threadToReply[token] = threadId
			}
		},

		/**
		 * Removes a reply message id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param token - conversation token
		 */
		removeParentIdToReply(token: string) {
			delete this.parentToReply[token]
			delete this.threadToReply[token]
		},

		/**
		 * Restore chat input from the browser storage and save to store
		 *
		 * @param token - conversation token
		 */
		restoreChatInput(token: string) {
			const chatInput = BrowserStorage.getItem('chatInput_' + token)
			if (chatInput) {
				this.chatInput[token] = chatInput
			}
		},

		/**
		 * Add a current input value to the store for a given conversation token
		 *
		 * @param payload action payload
		 * @param payload.token - conversation token
		 * @param payload.text The string to store
		 */
		setChatInput({ token, text }: { token: string, text: string }) {
			const parsedText = parseSpecialSymbols(text)
			BrowserStorage.setItem('chatInput_' + token, parsedText)
			this.chatInput[token] = parsedText
		},

		/**
		 * Add a message text that is being edited to the store for a given conversation token
		 *
		 * @param payload action payload
		 * @param payload.token - conversation token
		 * @param payload.text The string to store
		 * @param payload.parameters message parameters
		 */
		setChatEditInput({ token, text, parameters = {} }: { token: string, text: string, parameters?: ChatMessage['messageParameters'] }) {
			let parsedText = text

			// Handle mentions and special symbols
			parsedText = parseMentions(parsedText, parameters)
			parsedText = parseSpecialSymbols(parsedText)

			this.chatEditInput[token] = parsedText
		},

		/**
		 * Add a message id that is being edited to the store
		 *
		 * @param token - conversation token
		 * @param id The id of message
		 */
		setMessageIdToEdit(token: string, id: number) {
			this.messageIdToEdit[token] = id
		},

		/**
		 * Remove a message id that is being edited to the store
		 *
		 * @param token - conversation token
		 */
		removeMessageIdToEdit(token: string) {
			delete this.chatEditInput[token]
			delete this.messageIdToEdit[token]
		},

		/**
		 * Remove a current input value from the store for a given conversation token
		 *
		 * @param token - conversation token
		 */
		removeChatInput(token: string) {
			BrowserStorage.removeItem('chatInput_' + token)
			delete this.chatInput[token]
		},

		/**
		 * Initiate editing UI for a given message
		 *
		 * @param payload - action payload
		 * @param payload.token - conversation token
		 * @param payload.id - message id
		 * @param payload.message - message text
		 * @param payload.messageParameters - message parameters
		 */
		initiateEditingMessage({ token, id, message, messageParameters }: { token: string, id: number, message: string, messageParameters: ChatMessage['messageParameters'] }) {
			this.setMessageIdToEdit(token, id)
			const isFileShareOnly = Object.keys(Object(messageParameters)).some((key) => key.startsWith('file'))
				&& message === '{file}'
			if (isFileShareOnly) {
				this.setChatEditInput({ token, text: '' })
			} else {
				this.setChatEditInput({
					token,
					text: message,
					parameters: messageParameters,
				})
			}
			EventBus.emit('editing-message')
			EventBus.emit('focus-chat-input')
		},

		/**
		 * Clears store for a deleted conversation
		 *
		 * @param token the token of the conversation to be deleted
		 */
		purgeChatExtras(token: string) {
			this.removeParentIdToReply(token)
			this.removeChatInput(token)
		},

		setTasksCounters({ tasksCount, tasksDoneCount }: { tasksCount: number, tasksDoneCount: number }) {
			this.tasksCount = tasksCount
			this.tasksDoneCount = tasksDoneCount
		},

		async requestChatSummary(token: string, fromMessageId: number) {
			try {
				const response = await summarizeChat(token, fromMessageId)
				if (!response.data) {
					console.warn('No messages found to summarize:', { token, fromMessageId })
					return
				}
				const task = response.data.ocs.data

				if (!this.chatSummary[token]) {
					this.chatSummary[token] = {}
				}
				this.chatSummary[token][fromMessageId] = {
					...task,
					fromMessageId,
				}
				if (task.nextOffset && task.nextOffset !== fromMessageId) {
					await this.requestChatSummary(token, task.nextOffset)
				}
			} catch (error) {
				console.error('Error while requesting a summary:', error)
			}
		},

		storeChatSummary(token: string, fromMessageId: number, summary: string) {
			if (this.chatSummary[token][fromMessageId]) {
				this.chatSummary[token][fromMessageId].summary = summary
			}
		},

		dismissChatSummary(token: string) {
			if (this.hasChatSummaryTaskRequested(token)) {
				delete this.chatSummary[token]
			}
		},
	},
})

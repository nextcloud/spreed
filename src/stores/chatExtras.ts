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
	getRecentThreadsForConversation,
	getSingleThreadForConversation,
	getSubscribedThreads,
	setThreadNotificationLevel,
	summarizeChat,
} from '../services/messagesService.ts'
import { parseMentions, parseSpecialSymbols } from '../utils/textParse.ts'

type State = {
	threads: Record<string, Record<number, ThreadInfo>>
	subscribedThreads: Set<number>
	threadTitle: Record<string, string>
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
		subscribedThreads: new Set(),
		threadTitle: {},
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

		getThreadsList: (state) => (token: string): ThreadInfo[] => {
			if (state.threads[token]) {
				return Object.values(state.threads[token]).sort((a, b) => b.thread.lastActivity - a.thread.lastActivity)
			} else {
				return []
			}
		},

		getSubscribedThreadsList: (state): ThreadInfo[] => {
			return Object.keys(state.threads)
				.flatMap((token) => Object.values(state.threads[token] ?? {}))
				.filter((threadInfo) => state.subscribedThreads.has(threadInfo.thread.id))
				.sort((a, b) => b.thread.lastActivity - a.thread.lastActivity)
		},

		getThreadTitle: (state) => (token: string) => {
			return state.threadTitle[token]
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
		 * Add a thread to the store for given conversation
		 *
		 * @param token - conversation token
		 * @param thread - thread information
		 */
		async addThread(token: string, thread: ThreadInfo) {
			if (!this.threads[token]) {
				this.threads[token] = {}
			}

			this.threads[token][thread.thread.id] = thread
		},

		/**
		 * Fetch a thread from server in given conversation
		 *
		 * @param token - conversation token
		 * @param threadId - thread id to fetch
		 */
		async fetchSingleThread(token: string, threadId: number) {
			try {
				const response = await getSingleThreadForConversation(token, threadId)
				this.addThread(token, response.data.ocs.data)
			} catch (error) {
				console.error('Error fetching thread:', error)
			}
		},

		/**
		 * Fetch list of recent threads from server in given conversation
		 *
		 * @param token - conversation token
		 */
		async fetchRecentThreadsList(token: string) {
			try {
				const response = await getRecentThreadsForConversation({ token })
				response.data.ocs.data.forEach((threadInfo) => {
					this.addThread(token, threadInfo)
				})
			} catch (error) {
				console.error('Error fetching threads:', error)
			}
		},

		/**
		 * Fetch list of subscribed threads from server
		 * @param offset thread offset to start fetch with
		 */
		async fetchSubscribedThreadsList(offset?: number) {
			try {
				const response = await getSubscribedThreads({ offset })
				response.data.ocs.data.forEach((threadInfo) => {
					this.subscribedThreads.add(threadInfo.thread.id)
					this.addThread(threadInfo.thread.roomToken, threadInfo)
				})
			} catch (error) {
				console.error('Error fetching threads:', error)
			}
		},

		/**
		 * Create a thread from a reply chain in given conversation
		 * If thread already exists, subscribe to it
		 *
		 * @param token - conversation token
		 * @param messageId - message id of any reply in the chain
		 * @param level - new level of notification for thread
		 */
		async setThreadNotificationLevel(token: string, messageId: number, level: number) {
			try {
				const response = await setThreadNotificationLevel(token, messageId, level)
				this.addThread(token, response.data.ocs.data)
			} catch (error) {
				console.error('Error updating thread notification level:', error)
			}
		},

		/**
		 * Update a thread from a known information
		 *
		 * @param token - conversation token
		 * @param threadId - thread id to update
		 * @param payload - updated information
		 */
		async updateThread(token: string, threadId: number, payload: Partial<ThreadInfo>) {
			try {
				if (!this.threads[token] || !this.threads[token][threadId]) {
					// Thread is not known yet, try to fetch actual data from server
					await this.fetchSingleThread(token, threadId)
					return
				}

				this.threads[token][threadId] = {
					thread: payload.thread ?? this.threads[token][threadId].thread,
					attendee: payload.attendee ?? this.threads[token][threadId].attendee,
					first: payload.first ?? this.threads[token][threadId].first,
					last: payload.last ?? this.threads[token][threadId].last,
				}
			} catch (error) {
				console.error('Error updating thread:', error)
			}
		},

		/**
		 * Remove a thread from the store
		 *
		 * @param token - conversation token
		 * @param messageId - message id to remove all preceding threads (remove all, if omitted)
		 */
		clearThreads(token: string, messageId?: number) {
			if (messageId) {
				// Clear threads that are older than the given messageId
				for (const threadId of Object.keys(Object(this.threads[token]))) {
					if (+threadId < messageId) {
						delete this.threads[token][+threadId]
					}
				}
			} else {
				// Clear all threads for the conversation
				delete this.threads[token]
			}
		},

		/**
		 * Remove a message from a thread object
		 *
		 * @param token - conversation token
		 * @param threadId - thread id to remove message from
		 * @param messageId - message id to remove
		 */
		removeMessageFromThread(token: string, threadId: number, messageId: number) {
			if (!this.threads[token]?.[threadId]) {
				return
			}

			const thread = this.threads[token][threadId]
			if (thread.first?.id === messageId) {
				thread.first = null
			} else {
				this.threads[token][threadId].thread.numReplies -= 1
				if (thread.last?.id === messageId) {
					// Last message was removed but there might be older messages in the thread
					// that don't have expiration timestamp
					this.fetchSingleThread(token, threadId)
				}
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
		 * Add a thread title to the store
		 *
		 * @param payload action payload
		 * @param payload.token - conversation token
		 * @param payload.title - title from input
		 */
		setThreadTitle(token: string, title: string) {
			this.threadTitle[token] = title
		},

		/**
		 * Removes a thread title id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param token - conversation token
		 */
		removeThreadTitle(token: string) {
			delete this.threadTitle[token]
		},

		/**
		 * Add a reply message id to the store
		 *
		 * @param payload action payload
		 * @param payload.token - conversation token
		 * @param payload.id The id of message
		 */
		setParentIdToReply({ token, id }: { token: string, id: number }) {
			this.parentToReply[token] = id
		},

		/**
		 * Removes a reply message id from the store
		 * (after posting message or dismissing the operation)
		 *
		 * @param token - conversation token
		 */
		removeParentIdToReply(token: string) {
			delete this.parentToReply[token]
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
			this.clearThreads(token)
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

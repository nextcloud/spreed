/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	BigIntChatMessage,
	ChatMessage,
	ChatTask,
	editScheduledMessageParams,
	ScheduledMessage,
	scheduleMessageParams,
	ThreadInfo,
} from '../types/index.ts'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { useStore } from 'vuex'
import ConfirmDialog from '../components/UIShared/ConfirmDialog.vue'
import { PARTICIPANT } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { EventBus } from '../services/EventBus.ts'
import {
	deleteScheduledMessage as deleteScheduledMessageApi,
	editScheduledMessage as editScheduledMessageApi,
	getRecentThreadsForConversation,
	getScheduledMessages as getScheduledMessagesApi,
	getSingleThreadForConversation,
	getSubscribedThreads,
	renameThread as renameThreadApi,
	scheduleMessage as scheduleMessageApi,
	setThreadNotificationLevel as setThreadNotificationLevelApi,
	summarizeChat,
} from '../services/messagesService.ts'
import { parseMentions, parseSpecialSymbols } from '../utils/textParse.ts'
import { useActorStore } from './actor.ts'

type InitiateEditingMessagePayload = {
	token: string
	id: number | string
	message: string
	messageParameters: ChatMessage['messageParameters']
}

const FOLLOWED_THREADS_FETCH_LIMIT = 100
const pendingFetchSingleThreadRequests = new Set<number>()

/**
 * Store for conversation extra chat features apart from messages
 */
export const useChatExtrasStore = defineStore('chatExtras', () => {
	const threads = ref<Record<string, Record<number, ThreadInfo>>>({})
	const followedThreads = ref<Set<number>>(new Set())
	const followedThreadsInitialised = ref(false)
	const allFollowedThreadsReceived = ref(false)
	const threadTitle = ref<Record<string, string>>({})
	const parentToReply = ref<Record<string, number>>({})
	const chatInput = ref<Record<string, string>>({})
	const messageIdToEdit = ref<Record<string, number | string>>({})
	const chatEditInput = ref<Record<string, string>>({})
	const tasksCount = ref(0)
	const tasksDoneCount = ref(0)
	const chatSummary = ref<Record<string, Record<number, ChatTask>>>({})
	const scheduledMessages = ref<Record<string, Record<string, ScheduledMessage>>>({})
	const scheduleMessageTime = ref<number | null>(null)
	const showScheduledMessages = ref(false)

	const actorStore = useActorStore()
	const vuexStore = useStore()

	/**
	 * Returns known thread information from the store
	 *
	 * @param token - conversation token
	 * @param threadId - thread id
	 */
	function getThread(token: string, threadId: number) {
		if (threads.value[token]?.[threadId]) {
			return threads.value[token][threadId]
		}
	}

	/**
	 * Returns array of all known threads
	 *
	 * @param token - conversation token
	 */
	function getThreadsList(token: string): ThreadInfo[] {
		if (threads.value[token]) {
			return Object.values(threads.value[token]).sort((a, b) => b.thread.lastActivity - a.thread.lastActivity)
		} else {
			return []
		}
	}

	const followedThreadsList = computed<ThreadInfo[]>(() => {
		if (!followedThreadsInitialised.value) {
			return []
		}

		return Object.keys(threads.value)
			.flatMap((token) => Object.values(threads.value[token] ?? {}))
			.filter((threadInfo) => followedThreads.value.has(threadInfo.thread.id))
			.sort((a, b) => b.thread.lastActivity - a.thread.lastActivity)
	})

	/**
	 * Returns a title for thread to be created
	 *
	 * @param token - conversation token
	 */
	function getThreadTitle(token: string) {
		return threadTitle.value[token]
	}

	/**
	 * Returns a message id of parent to be replied to
	 *
	 * @param token - conversation token
	 */
	function getParentIdToReply(token: string) {
		if (parentToReply.value[token]) {
			return parentToReply.value[token]
		}
	}

	/**
	 * Returns edited message text for given conversation
	 *
	 * @param token - conversation token
	 */
	function getChatEditInput(token: string) {
		return chatEditInput.value[token] ?? ''
	}

	/**
	 * Returns edited message id for given conversation
	 *
	 * @param token - conversation token
	 */
	function getMessageIdToEdit(token: string): number | string | undefined {
		return messageIdToEdit.value[token]
	}

	/**
	 * Returns chat summary task queue for given conversation
	 *
	 * @param token - conversation token
	 */
	function getChatSummaryTaskQueue(token: string) {
		return Object.values(chatSummary.value[token] ?? {})
	}

	/**
	 * Returns whether chat summary task has been requested for given conversation
	 *
	 * @param token - conversation token
	 */
	function hasChatSummaryTaskRequested(token: string) {
		return chatSummary.value[token] !== undefined
	}

	/**
	 * Returns generated chat summary for given conversation
	 *
	 * @param token - conversation token
	 */
	function getChatSummary(token: string) {
		return Object.values(chatSummary.value[token] ?? {}).map((task) => task.summary).join('\n\n')
			|| t('spreed', 'Error occurred during a summary generation')
	}

	/**
	 * Returns list of scheduled messages (sorted by sendAt, prepared for chat)
	 *
	 * @param token - conversation token
	 */
	function getScheduledMessagesList(token: string) {
		return Object.values(scheduledMessages.value[token] ?? {})
			.sort((a, b) => a.sendAt - b.sendAt)
			.map((message) => parseScheduledToChatMessage(token, message))
	}

	/**
	 * Returns scheduled message by id (prepared for chat)
	 *
	 * @param token - conversation token
	 * @param messageId
	 */
	function getScheduledMessage(token: string, messageId: string): BigIntChatMessage | undefined {
		if (scheduledMessages.value[token]?.[messageId]) {
			return parseScheduledToChatMessage(token, scheduledMessages.value[token][messageId])
		}
	}

	/**
	 * Sets current timestamp when message will be scheduled to sent
	 *
	 * @param value new value
	 */
	function setScheduleMessageTime(value: number | null) {
		scheduleMessageTime.value = value
	}

	/**
	 * Sets whether scheduled messages should be shown in chat
	 *
	 * @param value new value
	 */
	function setShowScheduledMessages(value: boolean) {
		showScheduledMessages.value = value
	}

	/**
	 * Add a thread to the store for given conversation
	 *
	 * @param token - conversation token
	 * @param thread - thread information
	 */
	function addThread(token: string, thread: ThreadInfo) {
		if (!threads.value[token]) {
			threads.value[token] = {}
		}

		threads.value[token][thread.thread.id] = thread
	}

	/**
	 * Fetch a thread from server in given conversation
	 *
	 * @param token - conversation token
	 * @param threadId - thread id to fetch
	 */
	async function fetchSingleThread(token: string, threadId: number) {
		if (pendingFetchSingleThreadRequests.has(threadId)) {
			// A request for this thread is already pending
			return
		}

		try {
			pendingFetchSingleThreadRequests.add(threadId)
			const response = await getSingleThreadForConversation(token, threadId)
			addThread(token, response.data.ocs.data)
		} catch (error) {
			console.error('Error fetching thread:', error)
		} finally {
			pendingFetchSingleThreadRequests.delete(threadId)
		}
	}

	/**
	 * Fetch list of recent threads from server in given conversation
	 *
	 * @param token - conversation token
	 */
	async function fetchRecentThreadsList(token: string) {
		try {
			const response = await getRecentThreadsForConversation({ token })
			response.data.ocs.data.forEach((threadInfo) => {
				addThread(token, threadInfo)
			})
		} catch (error) {
			console.error('Error fetching threads:', error)
		}
	}

	/**
	 * Fetch list of subscribed threads from server
	 *
	 * @param offset thread offset to start fetch with
	 */
	async function fetchFollowedThreadsList(offset?: number) {
		try {
			const response = await getSubscribedThreads({ limit: FOLLOWED_THREADS_FETCH_LIMIT, offset })

			if (!offset) {
				// Reset the list if no offset is given
				followedThreads.value.clear()
				allFollowedThreadsReceived.value = false
			}

			response.data.ocs.data.forEach((threadInfo) => {
				followedThreads.value.add(threadInfo.thread.id)
				addThread(threadInfo.thread.roomToken, threadInfo)
			})
			followedThreadsInitialised.value = true

			if (response.data.ocs.data.length < FOLLOWED_THREADS_FETCH_LIMIT) {
				allFollowedThreadsReceived.value = true
			}
		} catch (error) {
			console.error('Error fetching threads:', error)
		}
	}

	/**
	 * Create a thread from a reply chain in given conversation
	 * If thread already exists, subscribe to it
	 *
	 * @param token - conversation token
	 * @param messageId - message id of any reply in the chain
	 * @param level - new level of notification for thread
	 */
	async function setThreadNotificationLevel(token: string, messageId: number, level: number) {
		try {
			const response = await setThreadNotificationLevelApi(token, messageId, level)
			// When unsubscribe from the thread, remove it from list of followed, add otherwise
			if (response.data.ocs.data.attendee.notificationLevel === PARTICIPANT.NOTIFY.NEVER) {
				followedThreads.value.delete(response.data.ocs.data.thread.id)
			} else {
				followedThreads.value.add(response.data.ocs.data.thread.id)
			}
			addThread(token, response.data.ocs.data)
		} catch (error) {
			console.error('Error updating thread notification level:', error)
		}
	}

	/**
	 * Update a thread from a known information
	 *
	 * @param token - conversation token
	 * @param threadId - thread id to update
	 * @param payload - updated information
	 */
	async function updateThread(token: string, threadId: number, payload: Partial<ThreadInfo>) {
		try {
			if (!threads.value[token] || !threads.value[token][threadId]) {
				// Thread is not known yet, try to fetch actual data from server
				await fetchSingleThread(token, threadId)
				return
			}

			threads.value[token][threadId] = {
				thread: payload.thread ?? threads.value[token][threadId].thread,
				attendee: payload.attendee ?? threads.value[token][threadId].attendee,
				first: payload.first ?? threads.value[token][threadId].first,
				last: payload.last ?? threads.value[token][threadId].last,
			}
		} catch (error) {
			console.error('Error updating thread:', error)
		}
	}

	/**
	 * Update a thread name from a known information
	 *
	 * @param token - conversation token
	 * @param threadId - thread id to update
	 * @param threadTitle - thread title to set
	 */
	async function updateThreadTitle(token: string, threadId: number, threadTitle: string) {
		if (!threads.value[token] || !threads.value[token][threadId]) {
			return
		}

		threads.value[token][threadId].thread.title = threadTitle
	}

	/**
	 * Rename a thread on a server and update store
	 *
	 * @param token - conversation token
	 * @param threadId - thread id to update
	 */
	async function renameThread(token: string, threadId: number) {
		const newThreadTitle = await spawnDialog(ConfirmDialog, {
			name: t('spreed', 'Edit thread details'),
			isForm: true,
			inputProps: {
				value: threads.value[token][threadId].thread.title,
				label: t('spreed', 'Thread title'),
			},
			buttons: [
				{
					label: t('spreed', 'Dismiss'),
					callback: () => undefined,
				},
				{
					label: t('spreed', 'Save'),
					variant: 'primary',
					callback: () => true,
				},
			],
		})

		if (newThreadTitle && typeof newThreadTitle === 'string') {
			try {
				const response = await renameThreadApi(token, threadId, newThreadTitle)
				addThread(token, response.data.ocs.data)
			} catch (e) {
				showError(t('spreed', 'Failed to rename the thread'))
				console.error(e)
			}
		}
	}

	/**
	 * Remove a thread from the store
	 *
	 * @param token - conversation token
	 * @param messageId - message id to remove all preceding threads (remove all, if omitted)
	 */
	function clearThreads(token: string, messageId?: number) {
		if (messageId) {
			// Clear threads that are older than the given messageId
			for (const threadId of Object.keys(threads.value[token] ?? {})) {
				if (+threadId < messageId) {
					delete threads.value[token][+threadId]
				}
			}
		} else {
			// Clear all threads for the conversation
			delete threads.value[token]
		}
	}

	/**
	 * Remove a message from a thread object
	 *
	 * @param token - conversation token
	 * @param threadId - thread id to remove message from
	 * @param messageId - message id to remove
	 */
	function removeMessageFromThread(token: string, threadId: number, messageId: number) {
		if (!threads.value[token]?.[threadId]) {
			return
		}

		const thread = threads.value[token][threadId]
		if (thread.first?.id === messageId) {
			thread.first = null
		} else {
			threads.value[token][threadId].thread.numReplies -= 1
			if (thread.last?.id === messageId) {
				// Last message was removed but there might be older messages in the thread
				// that don't have expiration timestamp
				fetchSingleThread(token, threadId)
			}
		}
	}

	/**
	 * Get chat input for current conversation (from store or BrowserStorage)
	 *
	 * @param token - conversation token
	 * @return The input text
	 */
	function getChatInput(token: string) {
		if (!chatInput.value[token]) {
			restoreChatInput(token)
		}
		return chatInput.value[token] ?? ''
	}

	/**
	 * Add a thread title to the store
	 *
	 * @param token - conversation token
	 * @param title - title from input
	 */
	function setThreadTitle(token: string, title: string) {
		threadTitle.value[token] = title
	}

	/**
	 * Removes a thread title id from the store
	 * (after posting message or dismissing the operation)
	 *
	 * @param token - conversation token
	 */
	function removeThreadTitle(token: string) {
		delete threadTitle.value[token]
	}

	/**
	 * Add a reply message id to the store
	 *
	 * @param payload action payload
	 * @param payload.token - conversation token
	 * @param payload.id The id of message
	 */
	function setParentIdToReply({ token, id }: { token: string, id: number }) {
		parentToReply.value[token] = id
	}

	/**
	 * Removes a reply message id from the store
	 * (after posting message or dismissing the operation)
	 *
	 * @param token - conversation token
	 */
	function removeParentIdToReply(token: string) {
		delete parentToReply.value[token]
	}

	/**
	 * Restore chat input from the browser storage and save to store
	 *
	 * @param token - conversation token
	 */
	function restoreChatInput(token: string) {
		const storedChatInput = BrowserStorage.getItem('chatInput_' + token)
		if (storedChatInput) {
			chatInput.value[token] = storedChatInput
		}
	}

	/**
	 * Add a current input value to the store for a given conversation token
	 *
	 * @param payload action payload
	 * @param payload.token - conversation token
	 * @param payload.text The string to store
	 */
	function setChatInput({ token, text }: { token: string, text: string }) {
		const parsedText = parseSpecialSymbols(text)
		BrowserStorage.setItem('chatInput_' + token, parsedText)
		chatInput.value[token] = parsedText
	}

	/**
	 * Add a message text that is being edited to the store for a given conversation token
	 *
	 * @param payload action payload
	 * @param payload.token - conversation token
	 * @param payload.text The string to store
	 * @param payload.parameters message parameters
	 */
	function setChatEditInput({ token, text, parameters = {} }: { token: string, text: string, parameters?: ChatMessage['messageParameters'] }) {
		let parsedText = text

		// Handle mentions and special symbols
		parsedText = parseMentions(parsedText, parameters)
		parsedText = parseSpecialSymbols(parsedText)

		chatEditInput.value[token] = parsedText
	}

	/**
	 * Add a message id that is being edited to the store
	 *
	 * @param token - conversation token
	 * @param id The id of message
	 */
	function setMessageIdToEdit(token: string, id: number | string) {
		messageIdToEdit.value[token] = id
	}

	/**
	 * Remove a message id that is being edited to the store
	 *
	 * @param token - conversation token
	 */
	function removeMessageIdToEdit(token: string) {
		delete chatEditInput.value[token]
		delete messageIdToEdit.value[token]
	}

	/**
	 * Remove a current input value from the store for a given conversation token
	 *
	 * @param token - conversation token
	 */
	function removeChatInput(token: string) {
		BrowserStorage.removeItem('chatInput_' + token)
		delete chatInput.value[token]
	}

	/**
	 * Initiate editing UI for a given message
	 *
	 * @param payload - action payload
	 * @param payload.token - conversation token
	 * @param payload.id - message id
	 * @param payload.message - message text
	 * @param payload.messageParameters - message parameters
	 */
	function initiateEditingMessage({ token, id, message, messageParameters }: InitiateEditingMessagePayload) {
		setMessageIdToEdit(token, id)
		const isFileShareOnly = Object.keys(messageParameters ?? {}).some((key) => key.startsWith('file'))
			&& message === '{file}'
		if (isFileShareOnly) {
			setChatEditInput({ token, text: '' })
		} else {
			setChatEditInput({
				token,
				text: message,
				parameters: messageParameters,
			})
		}
		if (scheduledMessages.value[token]?.[id] && scheduledMessages.value[token][id].threadId === -1) {
			setThreadTitle(token, scheduledMessages.value[token][id].threadTitle!)
		}
		EventBus.emit('editing-message')
		EventBus.emit('focus-chat-input')
	}

	/**
	 * Clears store for a deleted conversation
	 *
	 * @param token the token of the conversation to be deleted
	 */
	function purgeChatExtras(token: string) {
		removeParentIdToReply(token)
		removeChatInput(token)
		clearThreads(token)
	}

	/**
	 * Update tasks counters in the store
	 *
	 * @param payload - action payload
	 * @param payload.tasksCount - total tasks count
	 * @param payload.tasksDoneCount - done tasks count
	 */
	function setTasksCounters(payload: { tasksCount: number, tasksDoneCount: number }) {
		tasksCount.value = payload.tasksCount
		tasksDoneCount.value = payload.tasksDoneCount
	}

	/**
	 * Request chat summary from server for given conversation and last read message id
	 *
	 * @param token - conversation token
	 * @param fromMessageId
	 */
	async function requestChatSummary(token: string, fromMessageId: number) {
		try {
			const response = await summarizeChat(token, fromMessageId)
			if (!response.data) {
				console.warn('No messages found to summarize:', { token, fromMessageId })
				return
			}
			const task = response.data.ocs.data

			if (!chatSummary.value[token]) {
				chatSummary.value[token] = {}
			}
			chatSummary.value[token][fromMessageId] = {
				...task,
				fromMessageId,
			}
			if (task.nextOffset && task.nextOffset !== fromMessageId) {
				await requestChatSummary(token, task.nextOffset)
			}
		} catch (error) {
			console.error('Error while requesting a summary:', error)
		}
	}

	/**
	 * Store generated chat summary for given conversation
	 *
	 * @param token - conversation token
	 * @param fromMessageId
	 * @param summary
	 */
	function storeChatSummary(token: string, fromMessageId: number, summary: string) {
		if (chatSummary.value[token]?.[fromMessageId]) {
			chatSummary.value[token][fromMessageId].summary = summary
		}
	}

	/**
	 * Clean up chat summary data for given conversation
	 *
	 * @param token - conversation token
	 */
	function dismissChatSummary(token: string) {
		if (hasChatSummaryTaskRequested(token)) {
			delete chatSummary.value[token]
		}
	}

	/**
	 * Converts ScheduledMessage to BigIntChatMessage format (to render in chat)
	 *
	 * @param token - conversation token
	 * @param message - scheduled message object
	 */
	function parseScheduledToChatMessage(token: string, message: ScheduledMessage): BigIntChatMessage {
		return {
			token,
			id: message.id,
			actorId: message.actorId,
			actorType: message.actorType,
			actorDisplayName: actorStore.displayName,
			message: message.message,
			messageType: message.messageType,
			referenceId: '',
			systemMessage: '',
			isReplyable: false,
			markdown: true,
			messageParameters: {},
			parent: message.parent,
			reactions: {},
			timestamp: message.sendAt,
			expirationTimestamp: 0,
			threadId: message.threadId,
			threadTitle: message.threadTitle,
			isThread: !!message.threadId,
			silent: message.silent,
		}
	}

	/**
	 * Fetch scheduled messages for given conversation
	 *
	 * @param token - conversation token
	 */
	async function fetchScheduledMessages(token: string) {
		try {
			const response = await getScheduledMessagesApi(token)
			if (!scheduledMessages.value[token]) {
				scheduledMessages.value[token] = {}
			}

			response.data.ocs.data.forEach((message) => {
				scheduledMessages.value[token][message.id] = message
			})
		} catch (e) {
			console.error('Error while fetching scheduled messages:', e)
		}
	}

	/**
	 * Schedule a message to be posted with given payload
	 *
	 * @param token - conversation token
	 * @param payload - action payload
	 */
	async function scheduleMessage(token: string, payload: scheduleMessageParams) {
		try {
			const response = await scheduleMessageApi({ token, ...payload })
			if (!scheduledMessages.value[token]) {
				scheduledMessages.value[token] = {}
			}
			scheduledMessages.value[token][response.data.ocs.data.id] = response.data.ocs.data

			await vuexStore.dispatch('setConversationProperties', {
				token,
				properties: {
					hasScheduledMessages: Object.keys(scheduledMessages.value[token]).length,
				},
			})
		} catch (e) {
			console.error('Error while scheduling message:', e)
			throw e
		}
	}

	/**
	 * Edit already scheduled message with given payload
	 *
	 * @param token - conversation token
	 * @param messageId - id of message to edit
	 * @param payload - action payload
	 */
	async function editScheduledMessage(token: string, messageId: string, payload: editScheduledMessageParams) {
		try {
			const response = await editScheduledMessageApi({ token, messageId, ...payload })
			scheduledMessages.value[token][messageId] = response.data.ocs.data
		} catch (error) {
			console.error('Error while editing scheduled message:', error)
			throw error
		}
	}

	/**
	 * Delete already scheduled message
	 *
	 * @param token - conversation token
	 * @param messageId - id of message to delete
	 */
	async function deleteScheduledMessage(token: string, messageId: string) {
		try {
			await deleteScheduledMessageApi(token, messageId)

			delete scheduledMessages.value[token][messageId]

			const hasScheduledMessages = Object.keys(scheduledMessages.value[token] ?? {}).length
			await vuexStore.dispatch('setConversationProperties', {
				token,
				properties: {
					hasScheduledMessages,
				},
			})
			// Check if there are any scheduled messages left
			if (hasScheduledMessages === 0) {
				setShowScheduledMessages(false)
			}
		} catch (e) {
			console.error('Error while deleting scheduled message:', e)
		}
	}

	return {
		threads,
		followedThreads,
		followedThreadsInitialised,
		allFollowedThreadsReceived,
		threadTitle,
		parentToReply,
		chatInput,
		messageIdToEdit,
		chatEditInput,
		tasksCount,
		tasksDoneCount,
		chatSummary,
		scheduledMessages,
		scheduleMessageTime,
		showScheduledMessages,

		followedThreadsList,

		getThread,
		getThreadsList,
		getThreadTitle,
		getParentIdToReply,
		getChatEditInput,
		getMessageIdToEdit,
		getChatSummaryTaskQueue,
		hasChatSummaryTaskRequested,
		getChatSummary,
		getScheduledMessagesList,
		getScheduledMessage,

		addThread,
		fetchSingleThread,
		fetchRecentThreadsList,
		fetchFollowedThreadsList,
		setThreadNotificationLevel,
		updateThread,
		updateThreadTitle,
		renameThread,
		clearThreads,
		removeMessageFromThread,
		getChatInput,
		setThreadTitle,
		removeThreadTitle,
		setParentIdToReply,
		removeParentIdToReply,
		restoreChatInput,
		setChatInput,
		setChatEditInput,
		setMessageIdToEdit,
		removeMessageIdToEdit,
		removeChatInput,
		initiateEditingMessage,
		purgeChatExtras,
		setTasksCounters,
		requestChatSummary,
		storeChatSummary,
		dismissChatSummary,
		fetchScheduledMessages,
		scheduleMessage,
		editScheduledMessage,
		deleteScheduledMessage,
		setScheduleMessageTime,
		setShowScheduledMessages,
	}
})

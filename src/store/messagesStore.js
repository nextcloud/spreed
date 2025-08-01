import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import cloneDeep from 'lodash/cloneDeep.js'
import {
	ATTENDEE,
	CHAT,
	CONVERSATION,
	MESSAGE,
} from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { fetchNoteToSelfConversation } from '../services/conversationsService.ts'
import { EventBus } from '../services/EventBus.ts'
import {
	deleteMessage,
	editMessage,
	fetchMessages,
	getMessageContext,
	pollNewMessages,
	postNewMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
} from '../services/messagesService.ts'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { useChatStore } from '../stores/chat.ts'
import { useChatExtrasStore } from '../stores/chatExtras.ts'
import { useGuestNameStore } from '../stores/guestName.js'
import { usePollsStore } from '../stores/polls.ts'
import { useReactionsStore } from '../stores/reactions.js'
import { useSharedItemsStore } from '../stores/sharedItems.ts'
import CancelableRequest from '../utils/cancelableRequest.js'
import { debugTimer } from '../utils/debugTimer.ts'
import { convertToUnix } from '../utils/formattedTime.ts'

/**
 * Returns whether the given message contains a mention to self, directly
 * or indirectly through a global mention.
 *
 * @param {object} context store context
 * @param {object} message message object
 * @return {boolean} true if the message contains a mention to self or all,
 * false otherwise
 */
function hasMentionToSelf(context, message) {
	const actorStore = useActorStore()
	if (!message.messageParameters) {
		return false
	}

	for (const key in message.messageParameters) {
		const param = message.messageParameters[key]

		if (param.type === 'call') {
			return true
		}
		if (param.type === 'guest'
			&& actorStore.isActorGuest
			&& param.id === ('guest/' + actorStore.actorId)
		) {
			return true
		}
		if (param.type === 'user'
			&& actorStore.isActorUser
			&& param.id === actorStore.userId
		) {
			return true
		}
	}

	return false
}

/**
 * Returns whether the given message is presented in DOM and visible (none of the ancestors has `display: none`)
 *
 * @param {string} messageId store context
 * @return {boolean} whether the message is visible in the UI
 */
function isMessageVisible(messageId) {
	const element = document.getElementById(`message_${messageId}`)
	return element !== null && element.offsetParent !== null
}

const state = () => ({
	/**
	 * Map of conversation token to message list
	 */
	messages: {},

	/**
	 * Cached last read message id for display.
	 */
	visualLastReadMessageId: {},

	/**
	 * Loaded messages history parts of a conversation
	 *
	 * The messages list can still be empty due to message expiration,
	 * but if we ever loaded the history, we need to show an empty content
	 * instead of the loading animation
	 */
	loadedMessages: {},

	/**
	 * Stores the cancel function returned by `cancelableFetchMessages`,
	 * which allows to cancel the previous request for old messages
	 * when quickly switching to a new conversation.
	 */
	cancelFetchMessages: null,
	/**
	 * Stores the cancel function returned by `cancelableGetMessageContext`,
	 * which allows to cancel the previous request for the context messages
	 * when quickly switching to another conversation.
	 */
	cancelGetMessageContext: null,
	/**
	 * Stores the cancel function returned by `cancelablePollNewMessages`,
	 * which allows to cancel the previous long polling request for new
	 * messages before making another one.
	 */
	cancelPollNewMessages: {},
	/**
	 * Array of temporary message id to cancel function for the "postNewMessage" action
	 */
	cancelPostNewMessage: {},
})

const getters = {
	isMessagesListPopulated: (state) => (token) => {
		return !!state.loadedMessages[token]
	},

	/**
	 * Gets the messages array
	 *
	 * @param {object} state the state object.
	 * @return {Array} the messages array (if there are messages in the store)
	 */
	messagesList: (state) => (token) => {
		if (state.messages[token]) {
			return Object.values(state.messages[token])
		}
		return []
	},
	message: (state) => (token, id) => {
		if (state.messages[token]?.[id]) {
			return state.messages[token][id]
		}
		return {}
	},

	getTemporaryReferences: (state) => (token, referenceId) => {
		if (!state.messages[token]) {
			return []
		}

		return Object.values(state.messages[token]).filter((message) => {
			return message.referenceId === referenceId
				&& ('' + message.id).startsWith('temp-')
		})
	},

	getVisualLastReadMessageId: (state) => (token) => {
		if (state.visualLastReadMessageId[token]) {
			return state.visualLastReadMessageId[token]
		}
		return null
	},

	getLastCallStartedMessageId: (state, getters) => (token) => {
		return getters.messagesList(token).findLast((message) => message.systemMessage === 'call_started')?.id
	},

	getFirstDisplayableMessageIdAfterReadMarker: (state, getters) => (token, readMessageId) => {
		if (!state.messages[token]) {
			return null
		}

		return getters.messagesList(token).find((message) => {
			return message.id >= readMessageId
				&& !String(message.id).startsWith('temp-')
				&& message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
				&& message.systemMessage !== 'message_deleted'
				&& message.systemMessage !== 'message_edited'
		})?.id
	},

	getFirstDisplayableMessageIdBeforeReadMarker: (state, getters) => (token, readMessageId) => {
		if (!state.messages[token]) {
			return null
		}

		return getters.messagesList(token).findLast((message) => {
			return message.id < readMessageId
				&& isMessageVisible(message.id)
				&& !String(message.id).startsWith('temp-')
				&& message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
				&& message.systemMessage !== 'message_deleted'
				&& message.systemMessage !== 'message_edited'
		})?.id
	},

	isSendingMessages: (state) => {
		// the cancel handler only exists when a message is being sent
		return Object.keys(state.cancelPostNewMessage).length !== 0
	},
}

const mutations = {
	setCancelFetchMessages(state, cancelFunction) {
		state.cancelFetchMessages = cancelFunction
	},

	setCancelGetMessageContext(state, cancelFunction) {
		state.cancelGetMessageContext = cancelFunction
	},

	setCancelPollNewMessages(state, { requestId, cancelFunction }) {
		if (cancelFunction) {
			state.cancelPollNewMessages[requestId] = cancelFunction
		} else {
			delete state.cancelPollNewMessages[requestId]
		}
	},

	setCancelPostNewMessage(state, { messageId, cancelFunction }) {
		if (cancelFunction) {
			state.cancelPostNewMessage[messageId] = cancelFunction
		} else {
			delete state.cancelPostNewMessage[messageId]
		}
	},

	/**
	 * Adds a message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.message message object;
	 */
	addMessage(state, { token, message }) {
		if (!state.messages[token]) {
			state.messages[token] = {}
		}
		// TODO split adding and updating message in the store to different actions
		// message.parent doesn't contain grand-parent, so we should keep it
		// when updating message in store from new message.parent object
		const storedMessage = state.messages[token][message.id]
		const preparedMessage = !message.parent && storedMessage?.parent
			? { ...message, parent: storedMessage.parent }
			: message

		if (preparedMessage.parent) {
			preparedMessage.parent.isThread = preparedMessage.isThread
		}
		state.messages[token][message.id] = preparedMessage
	},
	/**
	 * Deletes a message from the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.id message id;
	 */
	deleteMessage(state, { token, id }) {
		if (state.messages[token][id]) {
			delete state.messages[token][id]
		}
	},

	/**
	 * Deletes a message from the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {number} payload.id conversation token;
	 * @param {string} payload.placeholder Placeholder message until deleting finished
	 */
	markMessageAsDeleting(state, { token, id, placeholder }) {
		if (!state.messages[token][id]) {
			return
		}
		state.messages[token][id].messageType = MESSAGE.TYPE.COMMENT_DELETED
		state.messages[token][id].message = placeholder
	},
	/**
	 * Adds a temporary message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.message message object;
	 */
	addTemporaryMessage(state, { token, message }) {
		if (!state.messages[token]) {
			state.messages[token] = {}
		}
		state.messages[token][message.id] = message
	},

	/**
	 * Adds a temporary message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.id message id;
	 * @param {string|undefined} payload.uploadId the internal id of the upload;
	 * @param {string} payload.reason the reason the temporary message failed;
	 */
	markTemporaryMessageAsFailed(state, { token, id, uploadId = undefined, reason }) {
		if (state.messages[token][id]) {
			state.messages[token][id].sendingFailure = reason
			if (uploadId) {
				state.messages[token][id].uploadId = uploadId
			}
		}
	},

	/**
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the last read chat message
	 */
	setVisualLastReadMessageId(state, { token, id }) {
		state.visualLastReadMessageId[token] = id
	},

	/**
	 * Deletes the messages entry from the store for the given conversation token.
	 *
	 * @param {object} state current store state
	 * @param {string} token Token of the conversation
	 */
	purgeMessagesStore(state, token) {
		if (state.visualLastReadMessageId[token]) {
			delete state.visualLastReadMessageId[token]
		}
		if (state.messages[token]) {
			delete state.messages[token]
		}
	},

	/**
	 * Clears the messages entry from the store for the given conversation token
	 * starting from defined id.
	 *
	 * @param {object} state current store state
	 * @param {object} payload payload;
	 * @param {string} payload.token the token of the conversation to be cleared;
	 * @param {number} payload.id the id of the message to be the first one after clear;
	 */
	clearMessagesHistory(state, { token, id }) {
		if (state.visualLastReadMessageId[token] && state.visualLastReadMessageId[token] < id) {
			state.visualLastReadMessageId[token] = id
		}

		if (state.messages[token]) {
			for (const messageId of Object.keys(state.messages[token])) {
				if (+messageId < id) {
					delete state.messages[token][messageId]
				}
			}
		}
	},

	// Increases reaction count for a particular reaction on a message
	addReactionToMessage(state, { token, messageId, reaction }) {
		const message = state.messages[token][messageId]
		if (!message.reactions[reaction]) {
			message.reactions[reaction] = 0
		}
		const reactionCount = message.reactions[reaction] + 1
		message.reactions[reaction] = reactionCount

		if (!message.reactionsSelf) {
			message.reactionsSelf = [reaction]
		} else {
			message.reactionsSelf = message.reactionsSelf.concat(reaction)
		}
	},

	loadedMessagesOfConversation(state, { token }) {
		state.loadedMessages[token] = true
	},

	// Decreases reaction count for a particular reaction on a message
	removeReactionFromMessage(state, { token, messageId, reaction }) {
		const message = state.messages[token][messageId]
		const reactionCount = message.reactions[reaction] - 1
		if (reactionCount <= 0) {
			delete message.reactions[reaction]
		} else {
			message.reactions[reaction] = reactionCount
		}

		if (message.reactionsSelf?.includes(reaction)) {
			message.reactionsSelf = message.reactionsSelf.filter((item) => item !== reaction)
		}
	},

	easeMessageList(state, { token, lastReadMessage }) {
		if (!state.messages[token]) {
			return
		}

		const messageIds = Object.keys(state.messages[token]).sort((a, b) => b - a)
		if (messageIds.length < 300) {
			return
		}

		// If lastReadMessage is rendered, keep it and +- 100 messages, otherwise only newest 200 messages
		const lastReadMessageIndex = messageIds.findIndex((id) => +id === lastReadMessage)

		const messagesToRemove = lastReadMessageIndex !== -1
			? messageIds.slice(lastReadMessageIndex + 99)
			: messageIds.slice(199)
		const newFirstKnown = messagesToRemove.shift()

		const newMessagesToRemove = (lastReadMessageIndex !== -1 && lastReadMessageIndex > 100)
			? messageIds.slice(0, lastReadMessageIndex - 99)
			: []
		const newLastKnown = newMessagesToRemove.pop()

		messagesToRemove.forEach((messageId) => {
			delete state.messages[token][messageId]
		})
		newMessagesToRemove.forEach((messageId) => {
			delete state.messages[token][messageId]
		})
		const chatStore = useChatStore()
		chatStore.removeMessagesFromChatBlocks(token, [...messagesToRemove, ...newMessagesToRemove].map((id) => +id))
	},
}

const actions = {

	/**
	 * Adds message to the store.
	 *
	 * If the message has a parent message presented in the store, updates it as well.
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.message message object;
	 */
	processMessage(context, { token, message }) {
		const sharedItemsStore = useSharedItemsStore()
		const actorStore = useActorStore()
		const chatExtrasStore = useChatExtrasStore()

		if (message.systemMessage === 'message_deleted'
			|| message.systemMessage === 'reaction'
			|| message.systemMessage === 'reaction_deleted'
			|| message.systemMessage === 'reaction_revoked'
			|| message.systemMessage === 'thread_created'
			|| message.systemMessage === 'message_edited') {
			if (!message.parent) {
				return
			}

			// If parent message is presented in store and is different, we update it
			const parentInStore = context.getters.message(token, message.parent.id)
			if (Object.keys(parentInStore).length !== 0 && JSON.stringify(parentInStore) !== JSON.stringify(message.parent)) {
				context.commit('addMessage', { token, message: message.parent })
			}

			const reactionsStore = useReactionsStore()
			if (message.systemMessage === 'message_deleted') {
				reactionsStore.resetReactions(token, message.parent.id)
				sharedItemsStore.deleteSharedItemFromMessage(token, message.parent.id)
			} else {
				reactionsStore.processReaction(token, message)
			}

			if (message.systemMessage === 'message_edited' || message.systemMessage === 'message_deleted') {
				// update conversation lastMessage, if it was edited or deleted
				if (message.parent.id === context.getters.conversation(token).lastMessage?.id) {
					context.dispatch('updateConversationLastMessage', { token, lastMessage: message.parent })
				}

				const thread = chatExtrasStore.getThread(token, message.parent.threadId)
				// update threads, if it is the first or the last message in the thread
				if (thread && (thread.last?.id === message.parent.id || thread.first?.id === message.parent.id)) {
					const updatedData = {
						thread: {
							...thread.thread,
							lastActivity: message.parent.timestamp,
						},
						first: (thread.first?.id === message.parent.id) ? message.parent : undefined,
						last: (thread.last?.id === message.parent.id) ? message.parent : undefined,
					}
					chatExtrasStore.updateThread(token, message.parent.threadId, updatedData)
				}

				// Check existing messages for having a deleted / edited message as parent, and update them
				context.getters.messagesList(token)
					.filter((storedMessage) => storedMessage.parent?.id === message.parent.id && JSON.stringify(storedMessage.parent) !== JSON.stringify(message.parent))
					.forEach((storedMessage) => {
						context.commit('addMessage', { token, message: Object.assign({}, storedMessage, { parent: message.parent }) })
					})
			}

			if (message.systemMessage === 'thread_created') {
				// Check existing messages for having a threadId flag, and update them
				context.getters.messagesList(token)
					.filter((storedMessage) => storedMessage.threadId === message.threadId)
					.forEach((storedMessage) => {
						context.commit('addMessage', { token, message: Object.assign({}, storedMessage, { isThread: true }) })
					})
				// Fetch thread data in case it doesn't exist in the store yet
				if (!chatExtrasStore.getThread(token, message.threadId)) {
					chatExtrasStore.addThread(token, {
						thread: {
							id: message.threadId,
							roomToken: token,
							title: message.messageParameters.title.name,
							lastMessageId: message.threadId,
							lastActivity: message.timestamp,
							numReplies: 0,
						},
						attendee: { notificationLevel: 0 },
						first: message.parent,
						last: null,
					})
				}
			}

			// Quit processing
			return
		}

		if (message.referenceId) {
			const tempMessages = context.getters.getTemporaryReferences(token, message.referenceId)
			if (tempMessages.length > 0) {
				// Replacing temporary placeholder message with server response (text message / file share)
				const conversation = context.getters.conversation(token)
				const isOwnMessage = actorStore.checkIfSelfIsActor(message)

				// update lastMessage and lastReadMessage (no await to make it async)
				// do it conditionally because there could have been more messages appearing concurrently
				if (conversation?.lastMessage && isOwnMessage && message.id > conversation.lastMessage.id) {
					context.dispatch('updateConversationLastMessage', { token, lastMessage: message })
				}

				if (conversation?.lastReadMessage && isOwnMessage && message.id > conversation.lastReadMessage) {
					context.dispatch('updateLastReadMessage', { token, id: message.id, updateVisually: true })
				}

				// If successful, deletes the temporary message from the store
				tempMessages.forEach((tempMessage) => {
					context.dispatch('removeTemporaryMessageFromStore', { token, id: tempMessage.id })
				})
			}
		}

		if (message.systemMessage === 'poll_voted') {
			const pollsStore = usePollsStore()
			pollsStore.debounceGetPollData({
				token,
				pollId: message.messageParameters.poll.id,
			})
			// Quit processing
			return
		}

		if (message.systemMessage === 'poll_closed') {
			const pollsStore = usePollsStore()
			pollsStore.getPollData({
				token,
				pollId: message.messageParameters.poll.id,
			})
		}

		if (message.systemMessage === 'history_cleared') {
			sharedItemsStore.purgeSharedItemsStore(token, message.id)
			chatExtrasStore.clearThreads(token, message.id)
			context.commit('clearMessagesHistory', {
				token,
				id: message.id,
			})
		}

		context.commit('addMessage', { token, message })

		// Update threads
		if (message.isThread) {
			const thread = chatExtrasStore.getThread(token, message.threadId)
			if (thread && thread.thread.lastMessageId < message.id) {
				chatExtrasStore.updateThread(message.token, message.threadId, {
					thread: {
						...thread.thread,
						lastMessageId: message.id,
						lastActivity: message.timestamp,
						numReplies: thread.thread.numReplies + 1,
					},
					last: message,
				})
			}
		}

		if (message.messageParameters && [MESSAGE.TYPE.COMMENT, MESSAGE.TYPE.VOICE_MESSAGE, MESSAGE.TYPE.RECORD_AUDIO, MESSAGE.TYPE.RECORD_VIDEO].includes(message.messageType)) {
			if (message.messageParameters?.object || message.messageParameters?.file) {
				// Handle voice messages, shares with single file, polls, deck cards, e.t.c
				sharedItemsStore.addSharedItemFromMessage(token, message)
				if (message.messageParameters?.object?.type === 'talk-poll') {
					EventBus.emit('talk:poll-added', { token, message })
				}
			} else if (Object.keys(message.messageParameters).some((key) => key.startsWith('file'))) {
				// Handle shares with multiple files
			}
		}
	},

	/**
	 * Delete a message
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.id message id;
	 * @param {string} payload.placeholder Placeholder message until deleting finished
	 */
	async deleteMessage(context, { token, id, placeholder }) {
		const message = Object.assign({}, context.getters.message(token, id))
		context.commit('markMessageAsDeleting', { token, id, placeholder })

		try {
			const response = await deleteMessage({ token, id })
			context.dispatch('processMessage', { token, message: response.data.ocs.data })
			return response.status
		} catch (error) {
			// Restore the previous message state
			context.commit('addMessage', { token, message })
			throw error
		}
	},

	/**
	 * Edit a message text
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token The conversation token
	 * @param {string} payload.messageId The message id
	 * @param {string} payload.updatedMessage The modified text of the message / file share caption
	 */
	async editMessage(context, { token, messageId, updatedMessage }) {
		EventBus.emit('editing-message-processing', { messageId, value: true })
		const message = Object.assign({}, context.getters.message(token, messageId))
		context.commit('addMessage', {
			token,
			message: { ...message, message: updatedMessage },
		})

		try {
			const response = await editMessage({
				token,
				messageId,
				updatedMessage,
			})
			context.dispatch('processMessage', { token, message: response.data.ocs.data })
			EventBus.emit('editing-message-processing', { messageId, value: false })
		} catch (error) {
			console.error(error)
			// Restore the previous message state
			context.commit('addMessage', { token, message })
			EventBus.emit('editing-message-processing', { messageId, value: false })
			throw error
		}
	},

	/**
	 * Add a temporary message generated in the client to
	 * the store, these messages are deleted once the full
	 * message object is received from the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.message message object;
	 */
	addTemporaryMessage(context, { token, message }) {
		context.commit('addTemporaryMessage', { token, message })
		const chatStore = useChatStore()
		chatStore.addMessageToChatBlocks(token, message)
		// Update conversations list order
		context.dispatch('updateConversationLastActive', token)
	},

	/**
	 * Mark a temporary message as failed to allow retrying it again
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.id message id;
	 * @param {string} payload.uploadId the internal id of the upload;
	 * @param {string} payload.reason the reason the temporary message failed;
	 */
	markTemporaryMessageAsFailed(context, { token, id, uploadId, reason }) {
		context.commit('markTemporaryMessageAsFailed', { token, id, uploadId, reason })
	},

	/**
	 * Remove temporary message from store after receiving the parsed one from server
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token conversation token;
	 * @param {object} payload.id message id;
	 */
	removeTemporaryMessageFromStore(context, { token, id }) {
		context.commit('deleteMessage', { token, id })

		const chatStore = useChatStore()
		chatStore.removeMessagesFromChatBlocks(token, id)
	},

	/**
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the last read chat message
	 */
	setVisualLastReadMessageId(context, { token, id }) {
		context.commit('setVisualLastReadMessageId', { token, id })
	},

	/**
	 * Deletes all messages of a conversation from the store only.
	 *
	 * @param {object} context default store context;
	 * @param {string} token the token of the conversation to be deleted;
	 */
	purgeMessagesStore(context, token) {
		context.commit('purgeMessagesStore', token)
		const chatStore = useChatStore()
		chatStore.purgeChatStore(token)
	},

	/**
	 * Clear all messages before defined id from the store only.
	 *
	 * @param {object} context default store context;
	 * @param {object} payload payload;
	 * @param {string} payload.token the token of the conversation to be cleared;
	 * @param {number} payload.id the id of the message to be the first one after clear;
	 */
	clearMessagesHistory(context, { token, id }) {
		context.commit('clearMessagesHistory', { token, id })
		const chatStore = useChatStore()
		chatStore.clearMessagesHistory(token, id)
	},

	/**
	 * Clears the last read message marker by moving it to the last message
	 * in the conversation.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation to be updated;
	 * @param {boolean} data.updateVisually whether to also clear the marker visually in the UI;
	 */
	async clearLastReadMessage(context, { token, updateVisually = false }) {
		const conversation = context.getters.conversation(token)
		if (hasTalkFeature(token, 'chat-read-last')) {
			context.dispatch('updateLastReadMessage', { token, id: null, updateVisually })
			return
		}
		// federated conversations don't proxy lastMessage id
		if (!conversation?.lastMessage?.id) {
			return
		}
		// set the id to the last message
		context.dispatch('updateLastReadMessage', { token, id: conversation.lastMessage.id, updateVisually })
	},

	/**
	 * Updates the last read message in the store and also in the backend.
	 * Optionally also updated the marker visually in the UI if specified.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation to be updated;
	 * @param {number|null} data.id the id of the message on which to set the read marker;
	 * @param {boolean} data.updateVisually whether to also update the marker visually in the UI;
	 */
	async updateLastReadMessage(context, { token, id = 0, updateVisually = false }) {
		const conversation = context.getters.conversation(token)
		if (!conversation || conversation.lastReadMessage === id) {
			return
		}

		if (id === 0) {
			console.warn('updateLastReadMessage: should not set read marker with id=0')
			return
		}

		// optimistic early commit to avoid indicator flickering
		// skip for federated conversations
		const idToUpdate = (id === null) ? conversation.lastMessage?.id : id
		if (idToUpdate) {
			context.dispatch('updateConversationLastReadMessage', { token, lastReadMessage: idToUpdate })
		}
		const visualIdToUpdate = idToUpdate ?? context.getters.messagesList(token).at(-1)?.id
		if (updateVisually && visualIdToUpdate) {
			context.commit('setVisualLastReadMessageId', { token, id: visualIdToUpdate })
		}

		const actorStore = useActorStore()
		if (actorStore.userId) {
			// only update on server side if there's an actual user, not guest
			const response = await updateLastReadMessage(token, id)
			context.dispatch('addConversation', response.data.ocs.data)
		}
	},

	/**
	 * Fetches messages that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the conversation token;
	 * @param {object} data.requestOptions request options;
	 * @param {string} data.lastKnownMessageId last known message id;
	 * @param {number} data.threadId Thread id to fetch messages for;
	 * @param {number} data.minimumVisible Minimum number of chat messages we want to load
	 * @param {boolean} data.includeLastKnown whether to include the last known message in the response;
	 * @param {number} [data.lookIntoFuture=0] direction of message fetch
	 */
	async fetchMessages(context, {
		token,
		lastKnownMessageId,
		includeLastKnown,
		threadId,
		requestOptions,
		minimumVisible,
		lookIntoFuture = CHAT.FETCH_OLD,
	}) {
		minimumVisible = (typeof minimumVisible === 'undefined') ? CHAT.MINIMUM_VISIBLE : minimumVisible

		context.dispatch('cancelFetchMessages')

		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(fetchMessages)
		// Assign the new cancel function to our data value
		context.commit('setCancelFetchMessages', cancel)

		const response = await request({
			token,
			lastKnownMessageId,
			includeLastKnown,
			threadId,
			lookIntoFuture,
			limit: CHAT.FETCH_LIMIT,
		}, requestOptions)

		const haveLastGiven = 'x-chat-last-given' in response.headers
		let lastGivenMessageId = haveLastGiven
			? parseInt(response.headers['x-chat-last-given'], 10)
			: lastKnownMessageId

		if ('x-chat-last-common-read' in response.headers) {
			const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
			context.dispatch('updateLastCommonReadMessage', {
				token,
				lastCommonReadMessage,
			})
		}

		// Process each messages and adds it to the store
		response.data.ocs.data.forEach((message) => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: true })
			}
			context.dispatch('processMessage', { token, message })

			if (!haveLastGiven) {
				lastGivenMessageId = lookIntoFuture === CHAT.FETCH_NEW
					? Math.max(lastGivenMessageId, message.id)
					: Math.min(lastGivenMessageId, message.id)
			}

			if (message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
			) {
				minimumVisible--
			}
		})

		context.commit('loadedMessagesOfConversation', { token })
		const chatStore = useChatStore()
		chatStore.processChatBlocks(token, response.data.ocs.data, {
			mergeBy: +lastKnownMessageId,
			threadId,
		})

		if (minimumVisible > 0) {
			debugTimer.tick(`${token} | fetch history`, 'first chunk')
			// There are not yet enough visible messages loaded, so fetch another chunk.
			// This can happen when a lot of reactions or poll votings happen
			return await context.dispatch('fetchMessages', {
				token,
				lastKnownMessageId: lastGivenMessageId,
				includeLastKnown,
				threadId,
				lookIntoFuture,
				minimumVisible,
			})
		}

		return response
	},

	/**
	 * Fetches messages that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the conversation token;
	 * @param {number} data.messageId Message id to get the context for;
	 * @param {number} data.threadId Thread id to get the context for;
	 * @param {object} data.requestOptions request options;
	 * @param {number} data.minimumVisible Minimum number of chat messages we want to load
	 */
	async getMessageContext(context, {
		token,
		messageId,
		threadId,
		requestOptions,
		minimumVisible,
	}) {
		minimumVisible = (typeof minimumVisible === 'undefined') ? Math.floor(CHAT.MINIMUM_VISIBLE / 2) : minimumVisible

		context.dispatch('cancelGetMessageContext')

		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(getMessageContext)
		// Assign the new cancel function to our data value
		context.commit('setCancelGetMessageContext', cancel)

		const response = await request({
			token,
			messageId,
			threadId,
			limit: CHAT.FETCH_LIMIT / 2,
		}, requestOptions)

		let oldestKnownMessageId = messageId
		let newestKnownMessageId = messageId

		if ('x-chat-last-common-read' in response.headers) {
			const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
			context.dispatch('updateLastCommonReadMessage', {
				token,
				lastCommonReadMessage,
			})
		}

		// Process each messages and adds it to the store
		response.data.ocs.data.forEach((message) => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: true })
			}
			context.dispatch('processMessage', { token, message })
			newestKnownMessageId = Math.max(newestKnownMessageId, message.id)
			oldestKnownMessageId = oldestKnownMessageId === 0 ? message.id : Math.min(oldestKnownMessageId, message.id)

			if (message.id <= messageId
				&& message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
			) {
				minimumVisible--
			}
		})

		context.commit('loadedMessagesOfConversation', { token })

		const chatStore = useChatStore()
		chatStore.processChatBlocks(token, response.data.ocs.data, { threadId })

		if (minimumVisible > 0) {
			debugTimer.tick(`${token} | get context`, 'first chunk')
			// There are not yet enough visible messages loaded, so fetch another chunk.
			// This can happen when a lot of reactions or poll votings happen
			return await context.dispatch('fetchMessages', {
				token,
				lastKnownMessageId: oldestKnownMessageId,
				includeLastKnown: false,
				threadId,
				lookIntoFuture: CHAT.FETCH_OLD,
				minimumVisible: minimumVisible * 2,
			})
		}

		return response
	},

	/**
	 * Cancels a previously running "fetchMessages" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelFetchMessages(context) {
		if (context.state.cancelFetchMessages) {
			context.state.cancelFetchMessages('canceled')
			context.commit('setCancelFetchMessages', null)
			return true
		}
		return false
	},

	/**
	 * Cancels a previously running "getMessageContext" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelGetMessageContext(context) {
		if (context.state.cancelGetMessageContext) {
			context.state.cancelGetMessageContext('canceled')
			context.commit('setCancelGetMessageContext', null)
			return true
		}
		return false
	},

	/**
	 * Fetches newly created messages that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token The conversation token;
	 * @param {string} data.requestId id to identify request uniquely
	 * @param {object} data.requestOptions request options;
	 * @param {number} data.lastKnownMessageId The id of the last message in the store.
	 */
	async pollNewMessages(context, { token, lastKnownMessageId, requestId, requestOptions }) {
		const actorStore = useActorStore()
		context.dispatch('cancelPollNewMessages', { requestId })

		if (!lastKnownMessageId) {
			// if param is null | undefined, it won't be included in the request query
			console.warn('Trying to load messages without the required parameter')
			return
		}

		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(pollNewMessages)

		// Assign the new cancel function to our data value
		context.commit('setCancelPollNewMessages', { cancelFunction: cancel, requestId })

		const response = await request({
			token,
			lastKnownMessageId,
			limit: CHAT.FETCH_LIMIT,
		}, requestOptions)
		context.commit('setCancelPollNewMessages', { requestId })

		if ('x-chat-last-common-read' in response.headers) {
			const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
			context.dispatch('updateLastCommonReadMessage', {
				token,
				lastCommonReadMessage,
			})
		}

		const conversation = context.getters.conversation(token)
		const actorId = actorStore.actorId
		const actorType = actorStore.actorType
		let countNewMessages = 0
		let hasNewMention = conversation.unreadMention
		let lastMessage = null
		// Process each messages and adds it to the store
		response.data.ocs.data.forEach((message) => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache,
				// force in case the display name has changed since
				// the last fetch
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: false })
			}
			context.dispatch('processMessage', { token, message })
			if (!lastMessage || message.id > lastMessage.id) {
				if (!message.systemMessage) {
					if (actorId !== message.actorId || actorType !== message.actorType) {
						countNewMessages++
					}

					// parse mentions data to update "conversation.unreadMention",
					// if needed
					if (!hasNewMention && hasMentionToSelf(context, message)) {
						hasNewMention = true
					}
				}
				lastMessage = message
			}

			// Overwrite the conversation.hasCall property so people can join
			// after seeing the message in the chat.
			if (conversation?.lastMessage && message.id > conversation.lastMessage.id) {
				if (['call_started', 'call_ended', 'call_ended_everyone', 'call_missed'].includes(message.systemMessage)) {
					context.dispatch('overwriteHasCallByChat', {
						token,
						hasCall: message.systemMessage === 'call_started',
						lastActivity: message.timestamp,
					})
				}
				if (message.systemMessage === 'call_ended_everyone'
					&& conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE
					&& !actorStore.checkIfSelfIsActor(message)) {
					const callViewStore = useCallViewStore()
					callViewStore.setCallHasJustEnded(message.timestamp)

					context.dispatch('leaveCall', {
						token,
						participantIdentifier: actorStore.participantIdentifier,
					})
				}
			}

			// in case we encounter an already read message, reset the counter
			// this is probably unlikely to happen unless one starts browsing from
			// an earlier page and scrolls down
			if (conversation.lastReadMessage === message.id) {
				// discard counters
				countNewMessages = 0
				hasNewMention = conversation.unreadMention
			}
		})

		if (conversation?.lastMessage && lastMessage.id > conversation.lastMessage.id) {
			context.dispatch('updateConversationLastMessage', {
				token,
				lastMessage,
			})

			// only increase the counter if the conversation store was out of sync with the message list
			if (countNewMessages > 0) {
				context.commit('updateUnreadMessages', {
					token,
					unreadMessages: conversation.unreadMessages + countNewMessages,
					// only update the value if it's been changed to true
					unreadMention: conversation.unreadMention !== hasNewMention ? hasNewMention : undefined,
				})
			}
		}

		context.commit('loadedMessagesOfConversation', { token })

		const chatStore = useChatStore()
		chatStore.processChatBlocks(token, response.data.ocs.data, {
			mergeBy: +lastKnownMessageId,
		})

		return response
	},

	/**
	 * Cancels a previously running "pollNewMessages" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @param {string} requestId request id
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelPollNewMessages(context, { requestId }) {
		if (context.state.cancelPollNewMessages[requestId]) {
			context.state.cancelPollNewMessages[requestId]('canceled')
			context.commit('setCancelPollNewMessages', { requestId })
			return true
		}
		return false
	},

	/**
	 * Sends the given temporary message to the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} data Passed in parameters
	 * @param {string} data.token token of the conversation
	 * @param {object} data.temporaryMessage temporary message, must already have been added to messages list.
	 * @param {object} data.threadTitle if given, creates a thread with that title
	 * @param {object} data.options post request options.
	 */
	async postNewMessage(context, { token, temporaryMessage, threadTitle, options }) {
		context.dispatch('addTemporaryMessage', { token, message: temporaryMessage })

		const { request, cancel } = CancelableRequest(postNewMessage)
		context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: cancel })

		const timeout = setTimeout(() => {
			context.dispatch('cancelPostNewMessage', { messageId: temporaryMessage.id })
			context.dispatch('markTemporaryMessageAsFailed', {
				token,
				id: temporaryMessage.id,
				reason: 'timeout',
			})
		}, 30000)

		try {
			// New message should be appended to the most recent block without gaps
			const chatStore = useChatStore()
			const conversation = context.rootGetters.conversation(token)
			const conversationLastMessageId = (conversation && 'id' in conversation.lastMessage)
				? conversation.lastMessage.id
				: chatStore.getLastKnownId(token, { threadId: temporaryMessage.threadId })

			const response = await request({
				token,
				message: temporaryMessage.message,
				actorDisplayName: temporaryMessage.actorDisplayName,
				referenceId: temporaryMessage.referenceId,
				replyTo: temporaryMessage.parent?.id,
				silent: temporaryMessage.silent,
				threadTitle,
			}, options)
			clearTimeout(timeout)
			context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: null })

			if ('x-chat-last-common-read' in response.headers) {
				const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
				context.dispatch('updateLastCommonReadMessage', {
					token,
					lastCommonReadMessage,
				})
			}

			// Own message might have been added already by polling, which is more up-to-date (e.g. reactions)
			if (!context.state.messages[token]?.[response.data.ocs.data.id]) {
				context.dispatch('processMessage', { token, message: response.data.ocs.data })
				chatStore.processChatBlocks(token, [response.data.ocs.data], {
					mergeBy: conversationLastMessageId,
					threadId: response.data.ocs.data.threadId,
				})
			}

			return response
		} catch (error) {
			if (timeout) {
				clearTimeout(timeout)
			}
			context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: null })

			let statusCode = null
			console.error('error while submitting message %s', error)
			if (error.isAxiosError) {
				statusCode = error?.response?.status
			}

			// FIXME: don't use showError here but set a flag
			// somewhere that makes Vue trigger the error message

			// 403 when room is read-only, 412 when switched to lobby mode
			if (statusCode === 403) {
				showError(t('spreed', 'No permission to post messages in this conversation'))
				context.dispatch('markTemporaryMessageAsFailed', {
					token,
					id: temporaryMessage.id,
					reason: 'read-only',
				})
			} else if (statusCode === 412) {
				showError(t('spreed', 'No permission to post messages in this conversation'))
				context.dispatch('markTemporaryMessageAsFailed', {
					token,
					id: temporaryMessage.id,
					reason: 'lobby',
				})
			} else {
				showError(t('spreed', 'Could not post message: {errorMessage}', { errorMessage: error.message || error }))
				context.dispatch('markTemporaryMessageAsFailed', {
					token,
					id: temporaryMessage.id,
					reason: 'other',
				})
			}
			throw error
		}
	},

	/**
	 * Cancels a previously running "postNewMessage" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @param {string} messageId the message id for which to cancel;
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelPostNewMessage(context, { messageId }) {
		if (context.state.cancelPostNewMessage[messageId]) {
			context.state.cancelPostNewMessage[messageId]('canceled')
			context.commit('setCancelPostNewMessage', { messageId, cancelFunction: null })
			return true
		}
		return false
	},

	/**
	 * Forwards message to a conversation. By default , the message is forwarded to Note to self.
	 *
	 * @param {object} context default store context;
	 * will be forwarded;
	 * @param {object} data the wrapping object;
	 * @param {string} [data.targetToken] the conversation token to where the message will be forwarded;
	 * @param {object} data.messageToBeForwarded the message object;
	 */
	async forwardMessage(context, { targetToken, messageToBeForwarded }) {
		const message = cloneDeep(messageToBeForwarded)

		// when there is no token provided, the message will be forwarded to the Note to self conversation
		if (!targetToken) {
			let noteToSelf = context.getters.conversationsList.find((conversation) => conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF)
			// If Note to self doesn't exist, it will be regenerated
			if (!noteToSelf) {
				const response = await fetchNoteToSelfConversation()
				noteToSelf = response.data.ocs.data
				context.dispatch('addConversation', noteToSelf)
			}
			targetToken = noteToSelf.token
		}
		// Overwrite with the target conversation token
		message.token = targetToken
		// Do not forward the message silently
		message.silent = false
		if (message.parent) {
			delete message.parent
		}

		if (message.messageParameters?.object) {
			const richObject = message.messageParameters.object
			const response = await postRichObjectToConversation(
				targetToken,
				{
					objectId: richObject.id,
					objectType: richObject.type,
					metaData: JSON.stringify(richObject),
					referenceId: '',
				},
			)
			return response
		}

		// If there are mentions in the message to be forwarded, replace them in the message
		// text.
		for (const key in message.messageParameters) {
			if (key.startsWith('mention')) {
				const mention = message.messageParameters[key]
				const mentionString = key.includes('mention-call') ? `**${mention.name}**` : `@"${mention.id}"`
				message.message = message.message.replace(`{${key}}`, mentionString)
			}
		}

		return await postNewMessage(message)
	},

	async removeExpiredMessages(context, { token }) {
		if (!context.state.messages[token]) {
			return
		}
		const chatExtrasStore = useChatExtrasStore()
		const chatStore = useChatStore()
		const timestamp = convertToUnix(Date.now())

		context.getters.messagesList(token).forEach((message) => {
			if (message.expirationTimestamp && timestamp > message.expirationTimestamp) {
				if (message.isThread) {
					chatExtrasStore.removeMessageFromThread(token, message.threadId, message.id)
				}
				context.commit('deleteMessage', { token, id: message.id })
				chatStore.removeMessagesFromChatBlocks(token, message.id)
			}
		})
	},

	async easeMessageList(context, { token }) {
		const lastReadMessage = context.getters.conversation(token)?.lastReadMessage
		context.commit('easeMessageList', { token, lastReadMessage })
	},

	loadedMessagesOfConversation(context, { token }) {
		context.commit('loadedMessagesOfConversation', { token })
	},
}

export default { state, mutations, getters, actions }

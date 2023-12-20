/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
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
import Hex from 'crypto-js/enc-hex.js'
import SHA256 from 'crypto-js/sha256.js'
import cloneDeep from 'lodash/cloneDeep.js'
import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'

import {
	ATTENDEE,
	CHAT,
	CONVERSATION,
} from '../constants.js'
import { fetchNoteToSelfConversation } from '../services/conversationsService.js'
import {
	deleteMessage,
	updateLastReadMessage,
	fetchMessages,
	lookForNewMessages,
	getMessageContext,
	postNewMessage,
	postRichObjectToConversation,
	addReactionToMessage,
	removeReactionFromMessage,
} from '../services/messagesService.js'
import { useChatExtrasStore } from '../stores/chatExtras.js'
import { useGuestNameStore } from '../stores/guestName.js'
import { useReactionsStore } from '../stores/reactions.js'
import { useSharedItemsStore } from '../stores/sharedItems.js'
import CancelableRequest from '../utils/cancelableRequest.js'

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
	if (!message.messageParameters) {
		return false
	}

	for (const key in message.messageParameters) {
		const param = message.messageParameters[key]

		if (param.type === 'call') {
			return true
		}
		if (param.type === 'guest'
			&& context.getters.getActorType() === ATTENDEE.ACTOR_TYPE.GUESTS
			&& param.id === ('guest/' + context.getters.getActorId())
		) {
			return true
		}
		if (param.type === 'user'
			&& context.getters.getActorType() === ATTENDEE.ACTOR_TYPE.USERS
			&& param.id === context.getters.getUserId()
		) {
			return true
		}
	}

	return false
}

const state = {
	/**
	 * Map of conversation token to message list
	 */
	messages: {},
	/**
	 * Map of conversation token to first known message id
	 */
	firstKnown: {},
	/**
	 * Map of conversation token to last known message id
	 */
	lastKnown: {},

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
	 * Stores the cancel function returned by `cancelableLookForNewMessages`,
	 * which allows to cancel the previous long polling request for new
	 * messages before making another one.
	 */
	cancelLookForNewMessages: {},
	/**
	 * Array of temporary message id to cancel function for the "postNewMessage" action
	 */
	cancelPostNewMessage: {},
}

const getters = {
	/**
	 * Returns whether more messages can be loaded, which means that the current
	 * message list doesn't yet contain all future messages.
	 * If false, the next call to "lookForNewMessages" will be blocking/long-polling.
	 *
	 * @param {object} state the state object.
	 * @param {object} getters the getters object.
	 * @return {boolean} true if more messages exist that needs loading, false otherwise
	 */
	hasMoreMessagesToLoad: (state, getters) => (token) => {
		const conversation = getters.conversation(token)
		if (!conversation) {
			return false
		}

		return getters.getLastKnownMessageId(token) < conversation.lastMessage.id
	},

	isMessageListPopulated: (state) => (token) => {
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

		return Object.values(state.messages[token]).filter(message => {
			return message.referenceId === referenceId
				&& ('' + message.id).startsWith('temp-')
		})
	},

	getFirstKnownMessageId: (state) => (token) => {
		if (state.firstKnown[token]) {
			return state.firstKnown[token]
		}
		return null
	},

	getLastKnownMessageId: (state) => (token) => {
		if (state.lastKnown[token]) {
			return state.lastKnown[token]
		}
		return null
	},

	getVisualLastReadMessageId: (state) => (token) => {
		if (state.visualLastReadMessageId[token]) {
			return state.visualLastReadMessageId[token]
		}
		return null
	},

	getFirstDisplayableMessageIdAfterReadMarker: (state, getters) => (token, readMessageId) => {
		if (!state.messages[token]) {
			return null
		}

		const displayableMessages = getters.messagesList(token).filter(message => {
			return message.id >= readMessageId
				&& !('' + message.id).startsWith('temp-')
		})

		if (displayableMessages.length) {
			return displayableMessages.shift().id
		}

		return null
	},

	getFirstDisplayableMessageIdBeforeReadMarker: (state, getters) => (token, readMessageId) => {
		if (!state.messages[token]) {
			return null
		}

		const displayableMessages = getters.messagesList(token).filter(message => {
			return message.id < readMessageId
				&& !('' + message.id).startsWith('temp-')
		})

		if (displayableMessages.length) {
			return displayableMessages.pop().id
		}

		return null
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

	setCancelLookForNewMessages(state, { requestId, cancelFunction }) {
		if (cancelFunction) {
			Vue.set(state.cancelLookForNewMessages, requestId, cancelFunction)
		} else {
			Vue.delete(state.cancelLookForNewMessages, requestId)
		}
	},

	setCancelPostNewMessage(state, { messageId, cancelFunction }) {
		if (cancelFunction) {
			Vue.set(state.cancelPostNewMessage, messageId, cancelFunction)
		} else {
			Vue.delete(state.cancelPostNewMessage, messageId)
		}
	},

	/**
	 * Adds a message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} message the message;
	 */
	addMessage(state, message) {
		if (!state.messages[message.token]) {
			Vue.set(state.messages, message.token, {})
		}
		if (state.messages[message.token][message.id]) {
			Vue.set(state.messages[message.token], message.id,
				Object.assign(state.messages[message.token][message.id], message)
			)
		} else {
			Vue.set(state.messages[message.token], message.id, message)
		}
	},
	/**
	 * Deletes a message from the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} message the message;
	 */
	deleteMessage(state, message) {
		if (state.messages[message.token][message.id]) {
			Vue.delete(state.messages[message.token], message.id)
		}
	},

	/**
	 * Deletes a message from the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {object} data.message the message;
	 * @param {string} data.placeholder Placeholder message until deleting finished
	 */
	markMessageAsDeleting(state, { message, placeholder }) {
		Vue.set(state.messages[message.token][message.id], 'messageType', 'comment_deleted')
		Vue.set(state.messages[message.token][message.id], 'message', placeholder)
	},
	/**
	 * Adds a temporary message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} message the temporary message;
	 */
	addTemporaryMessage(state, message) {
		if (!state.messages[message.token]) {
			Vue.set(state.messages, message.token, {})
		}
		Vue.set(state.messages[message.token], message.id, message)
	},

	/**
	 * Adds a temporary message to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {object} data.message the temporary message;
	 * @param {string|undefined} data.uploadId the internal id of the upload;
	 * @param {string} data.reason the reason the temporary message failed;
	 */
	markTemporaryMessageAsFailed(state, { message, uploadId = undefined, reason }) {
		if (state.messages[message.token][message.id]) {
			Vue.set(state.messages[message.token][message.id], 'sendingFailure', reason)
			if (uploadId) {
				Vue.set(state.messages[message.token][message.id], 'uploadId', uploadId)
			}
		}
	},

	/**
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the first known chat message
	 */
	setFirstKnownMessageId(state, { token, id }) {
		Vue.set(state.firstKnown, token, id)
	},

	/**
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the last known chat message
	 */
	setLastKnownMessageId(state, { token, id }) {
		Vue.set(state.lastKnown, token, id)
	},

	/**
	 * @param {object} state current store state;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the last read chat message
	 */
	setVisualLastReadMessageId(state, { token, id }) {
		Vue.set(state.visualLastReadMessageId, token, id)
	},

	/**
	 * Deletes the messages entry from the store for the given conversation token.
	 *
	 * @param {object} state current store state
	 * @param {string} token Token of the conversation
	 */
	deleteMessages(state, token) {
		if (state.firstKnown[token]) {
			Vue.delete(state.firstKnown, token)
		}
		if (state.lastKnown[token]) {
			Vue.delete(state.lastKnown, token)
		}
		if (state.visualLastReadMessageId[token]) {
			Vue.delete(state.visualLastReadMessageId, token)
		}
		if (state.messages[token]) {
			Vue.delete(state.messages, token)
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
		Vue.set(state.firstKnown, token, id)

		if (state.visualLastReadMessageId[token] && state.visualLastReadMessageId[token] < id) {
			Vue.set(state.visualLastReadMessageId, token, id)
		}

		if (state.messages[token]) {
			for (const messageId of Object.keys(state.messages[token])) {
				if (messageId < id) {
					Vue.delete(state.messages[token], messageId)
				}
			}
		}
	},

	// Increases reaction count for a particular reaction on a message
	addReactionToMessage(state, { token, messageId, reaction }) {
		const message = state.messages[token][messageId]
		if (!message.reactions[reaction]) {
			Vue.set(message.reactions, reaction, 0)
		}
		const reactionCount = message.reactions[reaction] + 1
		Vue.set(message.reactions, reaction, reactionCount)

		if (!message.reactionsSelf) {
			Vue.set(message, 'reactionsSelf', [reaction])
		} else {
			Vue.set(message, 'reactionsSelf', message.reactionsSelf.concat(reaction))
		}
	},

	loadedMessagesOfConversation(state, { token }) {
		Vue.set(state.loadedMessages, token, true)
	},

	// Decreases reaction count for a particular reaction on a message
	removeReactionFromMessage(state, { token, messageId, reaction }) {
		const message = state.messages[token][messageId]
		const reactionCount = message.reactions[reaction] - 1
		if (reactionCount <= 0) {
			Vue.delete(message.reactions, reaction)
		} else {
			Vue.set(message.reactions, reaction, reactionCount)
		}

		if (message.reactionsSelf?.includes(reaction)) {
			Vue.set(message, 'reactionsSelf', message.reactionsSelf.filter(item => item !== reaction))
		}
	},

	removeExpiredMessages(state, { token }) {
		if (!state.messages[token]) {
			return
		}

		const timestamp = (new Date()) / 1000
		const messageIds = Object.keys(state.messages[token])
		messageIds.forEach((messageId) => {
			if (state.messages[token][messageId].expirationTimestamp
				&& timestamp > state.messages[token][messageId].expirationTimestamp) {
				Vue.delete(state.messages[token], messageId)
			}
		})
	},

	easeMessageList(state, { token }) {
		if (!state.messages[token]) {
			return
		}

		const messageIds = Object.keys(state.messages[token])
		if (messageIds.length < 300) {
			return
		}

		const messagesToRemove = messageIds.sort().reverse().slice(199)
		const newFirstKnown = messagesToRemove.shift()

		messagesToRemove.forEach((messageId) => {
			Vue.delete(state.messages[token], messageId)
		})

		if (state.firstKnown[token] && messagesToRemove.includes(state.firstKnown[token])) {
			Vue.set(state.firstKnown, token, newFirstKnown)
		}
	},
}

const actions = {

	/**
	 * Adds message to the store.
	 *
	 * If the message has a parent message object,
	 * first it adds the parent to the store.
	 *
	 * @param {object} context default store context;
	 * @param {object} message the message;
	 */
	processMessage(context, message) {
		const sharedItemsStore = useSharedItemsStore()

		if (message.parent && message.systemMessage
			&& (message.systemMessage === 'message_deleted'
				|| message.systemMessage === 'reaction'
				|| message.systemMessage === 'reaction_deleted'
				|| message.systemMessage === 'reaction_revoked')) {
			// If parent message is presented in store already, we update it
			const parentInStore = context.getters.message(message.token, message.parent.id)
			if (Object.keys(parentInStore).length !== 0) {
				context.commit('addMessage', message.parent)
				const reactionsStore = useReactionsStore()
				if (message.systemMessage.startsWith('reaction')) {
					reactionsStore.fetchReactions(message.token, message.parent.id)
				} else {
					reactionsStore.resetReactions(message.token, message.parent.id)
				}
			}

			// Check existing messages for having a deleted message as parent, and update them
			if (message.systemMessage === 'message_deleted') {
				context.getters.messagesList(message.token)
					.filter(storedMessage => storedMessage.parent?.id === message.parent.id)
					.forEach(storedMessage => {
						context.commit('addMessage', Object.assign({}, storedMessage, { parent: message.parent }))
					})
			}

			// Quit processing
			return
		}

		if (message.referenceId) {
			const tempMessages = context.getters.getTemporaryReferences(message.token, message.referenceId)
			tempMessages.forEach(tempMessage => {
				context.commit('deleteMessage', tempMessage)
			})
		}

		if (message.systemMessage === 'poll_voted') {
			context.dispatch('debounceGetPollData', {
				token: message.token,
				pollId: message.messageParameters.poll.id,
			})
			// Quit processing
			return
		}

		if (message.systemMessage === 'poll_closed') {
			context.dispatch('getPollData', {
				token: message.token,
				pollId: message.messageParameters.poll.id,
			})
		}

		if (message.systemMessage === 'history_cleared') {
			context.commit('clearMessagesHistory', {
				token: message.token,
				id: message.id,
			})
		}

		context.commit('addMessage', message)

		if (message.messageParameters && (message.messageType === 'comment' || message.messageType === 'voice-message')) {
			if (message.messageParameters?.object || message.messageParameters?.file) {
				// Handle voice messages, shares with single file, polls, deck cards, e.t.c
				sharedItemsStore.addSharedItemFromMessage(message)
			} else if (Object.keys(message.messageParameters).some(key => key.startsWith('file'))) {
				// Handle shares with multiple files
			}
		}
	},

	/**
	 * Delete a message
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {object} data.message the message to be deleted;
	 * @param {string} data.placeholder Placeholder message until deleting finished
	 */
	async deleteMessage(context, { message, placeholder }) {
		const messageObject = Object.assign({}, context.getters.message(message.token, message.id))
		context.commit('markMessageAsDeleting', { message, placeholder })

		let response
		try {
			response = await deleteMessage(message)
			context.dispatch('processMessage', response.data.ocs.data)
			return response.status
		} catch (error) {
			// Restore the previous message state
			context.commit('addMessage', messageObject)
			throw error
		}
	},

	/**
	 * Creates a temporary message ready to be posted, based
	 * on the message to be replied and the current actor
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.text message string;
	 * @param {string} data.token conversation token;
	 * @param {string} data.uploadId upload id;
	 * @param {number} data.index index;
	 * @param {object} data.file file to upload;
	 * @param {string} data.localUrl local URL of file to upload;
	 * @param {boolean} data.isVoiceMessage whether the temporary file is a voice message
	 * @return {object} temporary message
	 */
	createTemporaryMessage(context, { text, token, uploadId, index, file, localUrl, isVoiceMessage }) {
		const chatExtrasStore = useChatExtrasStore()
		const parentId = chatExtrasStore.getParentIdToReply(token)
		const parent = parentId && context.getters.message(token, parentId)
		const date = new Date()
		let tempId = 'temp-' + date.getTime()
		const messageParameters = {}
		if (file) {
			tempId += '-' + uploadId + '-' + Math.random()
			messageParameters.file = {
				type: 'file',
				file,
				mimetype: file.type,
				id: tempId,
				name: file.newName || file.name,
				// index, will be the id from now on
				uploadId,
				localUrl,
				index,
			}
		}

		return Object.assign({}, {
			id: tempId,
			actorId: context.getters.getActorId(),
			actorType: context.getters.getActorType(),
			actorDisplayName: context.getters.getDisplayName(),
			timestamp: 0,
			systemMessage: '',
			messageType: isVoiceMessage ? 'voice-message' : '',
			message: text,
			messageParameters,
			token,
			parent,
			isReplyable: false,
			sendingFailure: '',
			reactions: {},
			referenceId: Hex.stringify(SHA256(tempId)),
		})
	},

	/**
	 * Add a temporary message generated in the client to
	 * the store, these messages are deleted once the full
	 * message object is received from the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} message the temporary message;
	 */
	addTemporaryMessage(context, message) {
		context.commit('addTemporaryMessage', message)
		// Update conversations list order
		context.dispatch('updateConversationLastActive', message.token)
	},

	/**
	 * Mark a temporary message as failed to allow retrying it again
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {object} data.message the temporary message;
	 * @param {string} data.uploadId the internal id of the upload;
	 * @param {string} data.reason the reason the temporary message failed;
	 */
	markTemporaryMessageAsFailed(context, { message, uploadId, reason }) {
		context.commit('markTemporaryMessageAsFailed', { message, uploadId, reason })
	},

	/**
	 * Remove temporary message from store after receiving the parsed one from server
	 *
	 * @param {object} context default store context;
	 * @param {object} message the temporary message;
	 */
	removeTemporaryMessageFromStore(context, message) {
		context.commit('deleteMessage', message)
	},

	/**
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the first known chat message
	 */
	setFirstKnownMessageId(context, { token, id }) {
		context.commit('setFirstKnownMessageId', { token, id })
	},

	/**
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token Token of the conversation
	 * @param {string} data.id Id of the last known chat message
	 */
	setLastKnownMessageId(context, { token, id }) {
		context.commit('setLastKnownMessageId', { token, id })
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
	deleteMessages(context, token) {
		context.commit('deleteMessages', token)
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
	},

	/**
	 * Clears the last read message marker by moving it to the last message
	 * in the conversation.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {object} data.token the token of the conversation to be updated;
	 * @param {boolean} data.updateVisually whether to also clear the marker visually in the UI;
	 */
	async clearLastReadMessage(context, { token, updateVisually = false }) {
		const conversation = context.getters.conversations[token]
		if (!conversation || !conversation.lastMessage) {
			return
		}
		// set the id to the last message
		context.dispatch('updateLastReadMessage', { token, id: conversation.lastMessage.id, updateVisually })
		context.dispatch('markConversationRead', token)
	},

	/**
	 * Updates the last read message in the store and also in the backend.
	 * Optionally also updated the marker visually in the UI if specified.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {object} data.token the token of the conversation to be updated;
	 * @param {number} data.id the id of the message on which to set the read marker;
	 * @param {boolean} data.updateVisually whether to also update the marker visually in the UI;
	 */
	async updateLastReadMessage(context, { token, id = 0, updateVisually = false }) {
		const conversation = context.getters.conversations[token]
		if (!conversation || conversation.lastReadMessage === id) {
			return
		}

		if (id === 0) {
			console.warn('updateLastReadMessage: should not set read marker with id=0')
		}

		// optimistic early commit to avoid indicator flickering
		context.dispatch('updateConversationLastReadMessage', { token, lastReadMessage: id })
		if (updateVisually) {
			context.commit('setVisualLastReadMessageId', { token, id })
		}

		if (context.getters.getUserId()) {
			// only update on server side if there's an actual user, not guest
			await updateLastReadMessage(token, id)
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
	 * @param {number} data.minimumVisible Minimum number of chat messages we want to load
	 * @param {boolean} data.includeLastKnown whether to include the last known message in the response;
	 */
	async fetchMessages(context, { token, lastKnownMessageId, includeLastKnown, requestOptions, minimumVisible }) {
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
			limit: CHAT.FETCH_LIMIT,
		}, requestOptions)

		let newestKnownMessageId = 0

		if ('x-chat-last-common-read' in response.headers) {
			const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
			context.dispatch('updateLastCommonReadMessage', {
				token,
				lastCommonReadMessage,
			})
		}

		// Process each messages and adds it to the store
		response.data.ocs.data.forEach(message => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: true })
			}
			context.dispatch('processMessage', message)
			newestKnownMessageId = Math.max(newestKnownMessageId, message.id)

			if (message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
			) {
				minimumVisible--
			}
		})

		if (response.headers['x-chat-last-given']) {
			context.dispatch('setFirstKnownMessageId', {
				token,
				id: parseInt(response.headers['x-chat-last-given'], 10),
			})
		}

		// For guests we also need to set the last known message id
		// after the first grab of the history, otherwise they start loading
		// the full history with fetchMessages().
		if (includeLastKnown && newestKnownMessageId
			&& !context.getters.getLastKnownMessageId(token)) {
			context.dispatch('setLastKnownMessageId', {
				token,
				id: newestKnownMessageId,
			})
		}

		context.commit('loadedMessagesOfConversation', { token })

		if (minimumVisible > 0) {
			// There are not yet enough visible messages loaded, so fetch another chunk.
			// This can happen when a lot of reactions or poll votings happen
			return await context.dispatch('fetchMessages', {
				token,
				lastKnownMessageId: context.getters.getFirstKnownMessageId(token),
				includeLastKnown,
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
	 * @param {object} data.requestOptions request options;
	 * @param {number} data.minimumVisible Minimum number of chat messages we want to load
	 */
	async getMessageContext(context, { token, messageId, requestOptions, minimumVisible }) {
		minimumVisible = (typeof minimumVisible === 'undefined') ? Math.floor(CHAT.MINIMUM_VISIBLE / 2) : minimumVisible

		context.dispatch('cancelGetMessageContext')

		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(getMessageContext)
		// Assign the new cancel function to our data value
		context.commit('setCancelGetMessageContext', cancel)

		const response = await request({
			token,
			messageId,
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
		response.data.ocs.data.forEach(message => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: true })
			}
			context.dispatch('processMessage', message)
			newestKnownMessageId = Math.max(newestKnownMessageId, message.id)
			oldestKnownMessageId = Math.min(oldestKnownMessageId, message.id)

			if (message.id <= messageId
				&& message.systemMessage !== 'reaction'
				&& message.systemMessage !== 'reaction_deleted'
				&& message.systemMessage !== 'reaction_revoked'
				&& message.systemMessage !== 'poll_voted'
			) {
				minimumVisible--
			}
		})

		if (!context.getters.getFirstKnownMessageId(token) || oldestKnownMessageId < context.getters.getFirstKnownMessageId(token)) {
			context.dispatch('setFirstKnownMessageId', {
				token,
				id: oldestKnownMessageId,
			})
		}

		if (!context.getters.getLastKnownMessageId(token) || newestKnownMessageId > context.getters.getLastKnownMessageId(token)) {
			context.dispatch('setLastKnownMessageId', {
				token,
				id: newestKnownMessageId,
			})
		}

		context.commit('loadedMessagesOfConversation', { token })

		if (minimumVisible > 0) {
			// There are not yet enough visible messages loaded, so fetch another chunk.
			// This can happen when a lot of reactions or poll votings happen
			return await context.dispatch('fetchMessages', {
				token,
				lastKnownMessageId: context.getters.getFirstKnownMessageId(token),
				includeLastKnown: false,
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
	 * This call will long-poll when hasMoreMessagesToLoad() returns false.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token The conversation token;
	 * @param {string} data.requestId id to identify request uniquely
	 * @param {object} data.requestOptions request options;
	 * @param {number} data.lastKnownMessageId The id of the last message in the store.
	 */
	async lookForNewMessages(context, { token, lastKnownMessageId, requestId, requestOptions }) {
		context.dispatch('cancelLookForNewMessages', { requestId })

		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(lookForNewMessages)

		// Assign the new cancel function to our data value
		context.commit('setCancelLookForNewMessages', { cancelFunction: cancel, requestId })

		const response = await request({
			token,
			lastKnownMessageId,
			limit: CHAT.FETCH_LIMIT,
		}, requestOptions)
		context.commit('setCancelLookForNewMessages', { requestId })

		if ('x-chat-last-common-read' in response.headers) {
			const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
			context.dispatch('updateLastCommonReadMessage', {
				token,
				lastCommonReadMessage,
			})
		}

		const conversation = context.getters.conversation(token)
		const actorId = context.getters.getActorId()
		const actorType = context.getters.getActorType()
		let countNewMessages = 0
		let hasNewMention = conversation.unreadMention
		let lastMessage = null
		// Process each messages and adds it to the store
		response.data.ocs.data.forEach(message => {
			if (message.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				// update guest display names cache,
				// force in case the display name has changed since
				// the last fetch
				const guestNameStore = useGuestNameStore()
				guestNameStore.addGuestName(message, { noUpdate: false })
			}
			context.dispatch('processMessage', message)
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
			if (conversation && conversation.lastMessage && message.id > conversation.lastMessage.id) {
				if (message.systemMessage === 'call_started') {
					context.dispatch('overwriteHasCallByChat', {
						token,
						hasCall: true,
					})
					context.dispatch('setConversationProperties', {
						token: message.token,
						properties: { callStartTime: message.timestamp },
					})
				} else if (message.systemMessage === 'call_ended'
					|| message.systemMessage === 'call_ended_everyone'
					|| message.systemMessage === 'call_missed') {
					context.dispatch('overwriteHasCallByChat', {
						token,
						hasCall: false,
					})
					context.dispatch('setConversationProperties', {
						token: message.token,
						properties: { callStartTime: 0 },
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

		context.dispatch('setLastKnownMessageId', {
			token,
			id: parseInt(response.headers['x-chat-last-given'], 10),
		})

		if (conversation && conversation.lastMessage && lastMessage.id > conversation.lastMessage.id) {
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

		return response
	},

	/**
	 * Cancels a previously running "lookForNewMessages" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @param {string} requestId request id
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelLookForNewMessages(context, { requestId }) {
		if (context.state.cancelLookForNewMessages[requestId]) {
			context.state.cancelLookForNewMessages[requestId]('canceled')
			context.commit('setCancelLookForNewMessages', { requestId })
			return true
		}
		return false
	},

	/**
	 * Sends the given temporary message to the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} data Passed in parameters
	 * @param {object} data.temporaryMessage temporary message, must already have been added to messages list.
	 * @param {object} data.options post request options.
	 */
	async postNewMessage(context, { temporaryMessage, options }) {
		context.dispatch('addTemporaryMessage', temporaryMessage)

		const { request, cancel } = CancelableRequest(postNewMessage)
		context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: cancel })

		const timeout = setTimeout(() => {
			context.dispatch('cancelPostNewMessage', { messageId: temporaryMessage.id })
			context.dispatch('markTemporaryMessageAsFailed', {
				message: temporaryMessage,
				reason: 'timeout',
			})
		}, 30000)

		try {
			const response = await request(temporaryMessage, options)
			clearTimeout(timeout)
			context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: null })

			if ('x-chat-last-common-read' in response.headers) {
				const lastCommonReadMessage = parseInt(response.headers['x-chat-last-common-read'], 10)
				context.dispatch('updateLastCommonReadMessage', {
					token: temporaryMessage.token,
					lastCommonReadMessage,
				})
			}

			// If successful, deletes the temporary message from the store
			context.dispatch('removeTemporaryMessageFromStore', temporaryMessage)

			const message = response.data.ocs.data
			// And adds the complete version of the message received
			// by the server
			context.dispatch('processMessage', message)

			const conversation = context.getters.conversation(temporaryMessage.token)

			// update lastMessage and lastReadMessage
			// do it conditionally because there could have been more messages appearing concurrently
			if (conversation && conversation.lastMessage && message.id > conversation.lastMessage.id) {
				context.dispatch('updateConversationLastMessage', {
					token: conversation.token,
					lastMessage: message,
				})
			}
			if (conversation && message.id > conversation.lastReadMessage) {
				// no await to make it async
				context.dispatch('updateLastReadMessage', {
					token: conversation.token,
					id: message.id,
					updateVisually: true,
				})
			}

			return response
		} catch (error) {
			if (timeout) {
				clearTimeout(timeout)
			}
			context.commit('setCancelPostNewMessage', { messageId: temporaryMessage.id, cancelFunction: null })

			let statusCode = null
			console.error(`error while submitting message ${error}`, error)
			if (error.isAxiosError) {
				statusCode = error?.response?.status
			}

			// FIXME: don't use showError here but set a flag
			// somewhere that makes Vue trigger the error message

			// 403 when room is read-only, 412 when switched to lobby mode
			if (statusCode === 403) {
				showError(t('spreed', 'No permission to post messages in this conversation'))
				context.dispatch('markTemporaryMessageAsFailed', {
					message: temporaryMessage,
					reason: 'read-only',
				})
			} else if (statusCode === 412) {
				showError(t('spreed', 'No permission to post messages in this conversation'))
				context.dispatch('markTemporaryMessageAsFailed', {
					message: temporaryMessage,
					reason: 'lobby',
				})
			} else {
				showError(t('spreed', 'Could not post message: {errorMessage}', { errorMessage: error.message || error }))
				context.dispatch('markTemporaryMessageAsFailed', {
					message: temporaryMessage,
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
			let noteToSelf = context.getters.conversationsList.find(conversation => conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF)
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

		const response = await postNewMessage(message, { silent: false })
		return response

	},

	/**
	 * Adds a single reaction to a message for the current user.
	 *
	 * @param {*} context the context object
	 * @param {*} param1 conversation token, message id and selected emoji (string)
	 */
	async addReactionToMessage(context, { token, messageId, selectedEmoji }) {
		try {
			context.commit('addReactionToMessage', {
				token,
				messageId,
				reaction: selectedEmoji,
			})
			// The response return an array with the reaction details for this message
			const response = await addReactionToMessage(token, messageId, selectedEmoji)
			// We replace the reaction details in the reactions store and wipe the old
			// values
			const reactionsStore = useReactionsStore()
			reactionsStore.updateReactions({
				token,
				messageId,
				reactionsDetails: response.data.ocs.data,
			})
		} catch (error) {
			// Restore the previous state if the request fails
			context.commit('removeReactionFromMessage', {
				token,
				messageId,
				reaction: selectedEmoji,
			})
			console.error(error)
			showError(t('spreed', 'Failed to add reaction'))
		}
	},

	/**
	 * Removes a single reaction from a message for the current user.
	 *
	 * @param {*} context the context object
	 * @param {*} param1 conversation token, message id and selected emoji (string)
	 */
	async removeReactionFromMessage(context, { token, messageId, selectedEmoji }) {
		try {
			context.commit('removeReactionFromMessage', {
				token,
				messageId,
				reaction: selectedEmoji,
			})
			// The response return an array with the reaction details for this message
			const response = await removeReactionFromMessage(token, messageId, selectedEmoji)
			// We replace the reaction details in the reactions store and wipe the old
			// values
			const reactionsStore = useReactionsStore()
			reactionsStore.updateReactions({
				token,
				messageId,
				reactionsDetails: response.data.ocs.data,
			})
		} catch (error) {
			// Restore the previous state if the request fails
			context.commit('addReactionToMessage', {
				token,
				messageId,
				reaction: selectedEmoji,
			})
			console.error(error)
			showError(t('spreed', 'Failed to remove reaction'))
		}
	},

	async removeExpiredMessages(context, { token }) {
		context.commit('removeExpiredMessages', { token })
	},

	async easeMessageList(context, { token }) {
		context.commit('easeMessageList', { token })
	},
}

export default { state, mutations, getters, actions }

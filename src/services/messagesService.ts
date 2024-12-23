/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Hex from 'crypto-js/enc-hex.js'
import SHA256 from 'crypto-js/sha256.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	ChatMessage,
	clearHistoryResponse,
	deleteMessageResponse,
	editMessageParams,
	editMessageResponse,
	getMessageContextParams,
	getMessageContextResponse,
	markUnreadResponse,
	postNewMessageParams,
	postNewMessageResponse,
	postRichObjectParams,
	postRichObjectResponse,
	receiveMessagesParams,
	receiveMessagesResponse,
	setReadMarkerParams,
	setReadMarkerResponse,
	summarizeChatParams,
	summarizeChatResponse,
} from '../types/index.ts'

type ReceiveMessagesPayload = Partial<receiveMessagesParams> & { token: string }
type GetMessageContextPayload = getMessageContextParams & { token: string, messageId: number }
type PostNewMessagePayload = Omit<postNewMessageParams, 'replyTo'> & { token: string, parent: ChatMessage }
type PostNewMessageOptions = Pick<postNewMessageParams, 'silent'> & object
type DeleteMessagePayload = { token: string, id: number }
type EditMessagePayload = { token: string, messageId: number, updatedMessage: editMessageParams['message'] }
type SearchMessagePayload = { term: string, person?: string, since?: string, until?: string, cursor?: number, limit?: number, conversation?: string }

/**
 * Fetches messages that belong to a particular conversation
 * specified with its token.
 *
 * @param data the wrapping object;
 * @param data.token the conversation token;
 * @param data.lastKnownMessageId last known message id;
 * @param data.includeLastKnown whether to include the last known message in the response;
 * @param [data.limit=100] Number of messages to load
 * @param options options;
 */
const fetchMessages = async function({ token, lastKnownMessageId, includeLastKnown, limit = 100 }: ReceiveMessagesPayload, options?: object): receiveMessagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }, options), {
		...options,
		params: {
			setReadMarker: 0,
			lookIntoFuture: 0,
			lastKnownMessageId,
			limit,
			includeLastKnown: includeLastKnown ? 1 : 0,
		} as receiveMessagesParams,
	})
}

/**
 * Fetches newly created messages that belong to a particular conversation
 * specified with its token.
 *
 * @param data the wrapping object;
 * @param data.lastKnownMessageId The id of the last message in the store.
 * @param data.token The conversation token;
 * @param [data.limit=100] Number of messages to load
 * @param options options
 */
const lookForNewMessages = async ({ token, lastKnownMessageId, limit = 100 }: ReceiveMessagesPayload, options?: object): receiveMessagesResponse => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }, options), {
		...options,
		params: {
			setReadMarker: 0,
			lookIntoFuture: 1,
			lastKnownMessageId,
			limit,
			includeLastKnown: 0,
			markNotificationsAsRead: 0,
		} as receiveMessagesParams,
	})
}

/**
 * Get the context of a message
 *
 * Loads some messages from before and after the given one.
 *
 * @param data the wrapping object;
 * @param data.token the conversation token;
 * @param data.messageId last known message id;
 * @param [data.limit=50] Number of messages to load
 * @param options options;
 */
const getMessageContext = async function({ token, messageId, limit = 50 }: GetMessageContextPayload, options?: object): getMessageContextResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/context', { token, messageId }, options), {
		...options,
		params: {
			limit,
		} as getMessageContextParams,
	})
}

/**
 * Posts a new message to the server.
 *
 * @param param0 The message object that is destructured
 * @param param0.token The conversation token
 * @param param0.message The message text
 * @param param0.actorDisplayName The display name of the actor
 * @param param0.referenceId A reference id to identify the message later again
 * @param param0.parent The message to be replied to
 * @param param1 options object destructured
 * @param param1.silent whether the message should trigger a notifications
 */
const postNewMessage = async function({ token, message, actorDisplayName, referenceId, parent }: PostNewMessagePayload, { silent, ...options }: PostNewMessageOptions): postNewMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }, options), {
		message,
		actorDisplayName,
		referenceId,
		replyTo: parent?.id,
		silent,
	} as postNewMessageParams, options)
}

/**
 * Clears the conversation history
 *
 * @param token The token of the conversation to be deleted.
 * @param options request options
 */
const clearConversationHistory = async function(token: string, options?: object): clearHistoryResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }, options), options)
}

/**
 * Deletes a message from the server.
 *
 * @param param0 The message object that is destructured
 * @param param0.token The conversation token
 * @param param0.id The id of the message to be deleted
 * @param options request options
 */
const deleteMessage = async function({ token, id }: DeleteMessagePayload, options?: object): deleteMessageResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{id}', { token, id }, options), options)
}

/**
 * Edit a message text / file share caption.
 *
 * @param param0 The destructured payload
 * @param param0.token The conversation token
 * @param param0.messageId The message id
 * @param param0.updatedMessage The modified text of the message / file share caption
 * @param options request options
 */
const editMessage = async function({ token, messageId, updatedMessage }: EditMessagePayload, options?: object): editMessageResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}', { token, messageId }, options), {
		message: updatedMessage,
	} as editMessageParams, options)
}

/**
 * Post a rich object to a conversation
 *
 * @param token conversation token
 * @param data the wrapping object;
 * @param data.objectType object type
 * @param data.objectId object id
 * @param data.metaData JSON metadata of the rich object encoded as string
 * @param data.referenceId generated reference id, leave empty to generate it based on the other args
 * @param options request options
 */
const postRichObjectToConversation = async function(token: string, { objectType, objectId, metaData, referenceId }: postRichObjectParams, options?: object): postRichObjectResponse {
	if (!referenceId) {
		const tempId = 'richobject-' + objectType + '-' + objectId + '-' + token + '-' + (new Date().getTime())
		referenceId = Hex.stringify(SHA256(tempId))
	}
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share', { token }, options), {
		objectType,
		objectId,
		metaData,
		referenceId,
	} as postRichObjectParams, options)
}

/**
 * Updates the last read message id
 *
 * @param token The token of the conversation to be removed from favorites
 * @param lastReadMessage id of the last read message to set
 * @param options request options
 */
const updateLastReadMessage = async function(token: string, lastReadMessage?: number|null, options?: object): setReadMarkerResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }, options), {
		lastReadMessage,
	} as setReadMarkerParams, options)
}

/**
 * Set conversation as unread
 *
 * @param token The token of the conversation to be set as unread
 * @param options request options
 */
const setConversationUnread = async function(token: string, options?: object): markUnreadResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }, options), options)
}

/**
 * Request chat summary from a given message
 *
 * @param token The conversation token
 * @param fromMessageId The last read message to start from
 * @param options object destructured
 */
const summarizeChat = async function(token: string, fromMessageId: summarizeChatParams['fromMessageId'], options?: object): summarizeChatResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/summarize', { token }, options), {
		fromMessageId,
	} as summarizeChatParams, options)
}

const searchMessages = async function(params: SearchMessagePayload, options: object) {
	return axios.get(generateOcsUrl('search/providers/talk-message/search'), {
		...options,
		params,
	}).catch(() => {})
}

export {
	fetchMessages,
	lookForNewMessages,
	getMessageContext,
	postNewMessage,
	clearConversationHistory,
	deleteMessage,
	editMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
	setConversationUnread,
	summarizeChat,
	searchMessages,
}

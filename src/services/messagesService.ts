/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	ChatMessage,
	clearHistoryResponse,
	deleteMessageResponse,
	editMessageParams,
	editMessageResponse,
	getMessageContextParams,
	getMessageContextResponse,
	getRecentThreadsParams,
	getRecentThreadsResponse,
	getThreadResponse,
	markUnreadResponse,
	postNewMessageParams,
	postNewMessageResponse,
	postRichObjectParams,
	postRichObjectResponse,
	receiveMessagesParams,
	receiveMessagesResponse,
	setReadMarkerParams,
	setReadMarkerResponse,
	setThreadNotificationLevelParams,
	setThreadNotificationLevelResponse,
	summarizeChatParams,
	summarizeChatResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import Hex from 'crypto-js/enc-hex.js'
import SHA256 from 'crypto-js/sha256.js'
import { CHAT } from '../constants.ts'

type ReceiveMessagesPayload = Partial<receiveMessagesParams> & { token: string }
type GetMessageContextPayload = getMessageContextParams & { token: string, messageId: number }
type DeleteMessagePayload = { token: string, id: number }
type EditMessagePayload = { token: string, messageId: number, updatedMessage: editMessageParams['message'] }

/**
 * Fetches messages that belong to a particular conversation
 * specified with its token.
 *
 * @param data the wrapping object;
 * @param data.token the conversation token;
 * @param data.lastKnownMessageId last known message id;
 * @param data.includeLastKnown whether to include the last known message in the response;
 * @param data.threadId The thread id to retrieve data
 * @param [data.lookIntoFuture=0] direction of message fetch
 * @param [data.limit=100] Number of messages to load
 * @param [options] Axios request options
 */
async function fetchMessages({
	token,
	lastKnownMessageId,
	includeLastKnown,
	lookIntoFuture = CHAT.FETCH_OLD,
	threadId,
	limit = 100,
}: ReceiveMessagesPayload, options?: AxiosRequestConfig): receiveMessagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), {
		...options,
		params: {
			setReadMarker: 0,
			lookIntoFuture,
			lastKnownMessageId,
			threadId,
			limit,
			timeout: 0,
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
 * @param [options] Axios request options
 */
async function pollNewMessages({
	token,
	lastKnownMessageId,
	limit = 100,
}: ReceiveMessagesPayload, options?: AxiosRequestConfig): receiveMessagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), {
		...options,
		params: {
			setReadMarker: 0,
			lookIntoFuture: CHAT.FETCH_NEW,
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
 * @param data.threadId The thread id to retrieve data
 * @param [data.limit=50] Number of messages to load
 * @param [options] Axios request options
 */
async function getMessageContext({ token, messageId, threadId, limit = 50 }: GetMessageContextPayload, options?: AxiosRequestConfig): getMessageContextResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/context', { token, messageId }), {
		...options,
		params: {
			threadId,
			limit,
		} as getMessageContextParams,
	})
}

/**
 * Posts a new message to the server.
 *
 * @param payload The message object that is destructured
 * @param payload.token The conversation token
 * @param payload.message The message text
 * @param payload.actorDisplayName The display name of the actor
 * @param payload.referenceId A reference id to identify the message later again
 * @param payload.replyTo The message id to be replied to
 * @param payload.silent whether the message should trigger a notifications
 * @param [options] Axios request options
 */
async function postNewMessage({
	token,
	message,
	actorDisplayName,
	referenceId,
	replyTo,
	silent,
	threadTitle,
}: postNewMessageParams & { token: string }, options?: AxiosRequestConfig): postNewMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), {
		message,
		actorDisplayName,
		referenceId,
		replyTo,
		silent,
		threadTitle,
	} as postNewMessageParams, options)
}

/**
 * Clears the conversation history
 *
 * @param token The token of the conversation to be deleted.
 * @param [options] Axios request options
 */
async function clearConversationHistory(token: string, options?: AxiosRequestConfig): clearHistoryResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), options)
}

/**
 * Deletes a message from the server.
 *
 * @param param0 The message object that is destructured
 * @param param0.token The conversation token
 * @param param0.id The id of the message to be deleted
 * @param [options] Axios request options
 */
async function deleteMessage({ token, id }: DeleteMessagePayload, options?: AxiosRequestConfig): deleteMessageResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{id}', { token, id }), options)
}

/**
 * Edit a message text / file share caption.
 *
 * @param param0 The destructured payload
 * @param param0.token The conversation token
 * @param param0.messageId The message id
 * @param param0.updatedMessage The modified text of the message / file share caption
 * @param [options] Axios request options
 */
async function editMessage({ token, messageId, updatedMessage }: EditMessagePayload, options?: AxiosRequestConfig): editMessageResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}', { token, messageId }), {
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
 * @param [options] Axios request options
 */
async function postRichObjectToConversation(token: string, { objectType, objectId, metaData, referenceId }: postRichObjectParams, options?: AxiosRequestConfig): postRichObjectResponse {
	if (!referenceId) {
		const tempId = 'richobject-' + objectType + '-' + objectId + '-' + token + '-' + (new Date().getTime())
		referenceId = Hex.stringify(SHA256(tempId))
	}
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share', { token }), {
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
 * @param [options] Axios request options
 */
async function updateLastReadMessage(token: string, lastReadMessage?: number | null, options?: AxiosRequestConfig): setReadMarkerResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }), {
		lastReadMessage,
	} as setReadMarkerParams, options)
}

/**
 * Set conversation as unread
 *
 * @param token The token of the conversation to be set as unread
 * @param [options] Axios request options
 */
async function setConversationUnread(token: string, options?: AxiosRequestConfig): markUnreadResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }), options)
}

/**
 * Request chat summary from a given message
 *
 * @param token The conversation token
 * @param fromMessageId The last read message to start from
 * @param [options] Axios request options
 */
async function summarizeChat(token: string, fromMessageId: summarizeChatParams['fromMessageId'], options?: AxiosRequestConfig): summarizeChatResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/summarize', { token }), {
		fromMessageId,
	} as summarizeChatParams, options)
}

/**
 * Fetch a list of recent threads for given conversation
 *
 * @param data the wrapping object
 * @param data.token the conversation token
 * @param [data.limit=50] Number of threads to return
 * @param [options] Axios request options
 */
async function getRecentThreadsForConversation({ token, limit }: { token: string } & getRecentThreadsParams, options?: AxiosRequestConfig): getRecentThreadsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/threads/recent', { token }), {
		...options,
		params: {
			limit,
		},
	})
}

/**
 * Fetch a thread for given conversation and thread id
 *
 * @param token the conversation token
 * @param threadId The thread id to retrieve data
 * @param [options] Axios request options
 */
async function getSingleThreadForConversation(token: string, threadId: number, options?: AxiosRequestConfig): getThreadResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/threads/{threadId}', { token, threadId }), options)
}

/**
 * Create a new thread for a conversation
 *
 * @param token The conversation token
 * @param messageId The message id of any message belonging to the future thread
 * @param level Level for thread notifications 0|1|2
 * @param [options] Axios request options
 */
async function setThreadNotificationLevel(token: string, messageId: number, level: number, options?: AxiosRequestConfig): setThreadNotificationLevelResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/threads/{messageId}/notify', { token, messageId }), {
		level,
	} as setThreadNotificationLevelParams, options)
}

export {
	clearConversationHistory,
	deleteMessage,
	editMessage,
	fetchMessages,
	getMessageContext,
	getRecentThreadsForConversation,
	getSingleThreadForConversation,
	pollNewMessages,
	postNewMessage,
	postRichObjectToConversation,
	setConversationUnread,
	setThreadNotificationLevel,
	summarizeChat,
	updateLastReadMessage,
}

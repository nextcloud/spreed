/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	clearHistoryResponse,
	deleteMessageResponse,
	deleteScheduledMessageResponse,
	editMessageParams,
	editMessageResponse,
	editScheduledMessageParams,
	editScheduledMessageResponse,
	getMessageContextParams,
	getMessageContextResponse,
	getRecentThreadsParams,
	getRecentThreadsResponse,
	getScheduledMessagesResponse,
	getSubscribedThreadsParams,
	getSubscribedThreadsResponse,
	getThreadResponse,
	hidePinnedMessageResponse,
	markUnreadResponse,
	pinMessageParams,
	pinMessageResponse,
	postNewMessageParams,
	postNewMessageResponse,
	postRichObjectParams,
	postRichObjectResponse,
	receiveMessagesParams,
	receiveMessagesResponse,
	renameThreadParams,
	renameThreadResponse,
	scheduleMessageParams,
	scheduleMessageResponse,
	setReadMarkerParams,
	setReadMarkerResponse,
	setThreadNotificationLevelParams,
	setThreadNotificationLevelResponse,
	summarizeChatParams,
	summarizeChatResponse,
	unpinMessageResponse,
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
type PinMessagePayload = { token: string, messageId: number, pinUntil?: pinMessageParams['pinUntil'] }

/**
 * Fetches messages that belong to a particular conversation
 * specified with its token.
 *
 * @param data the wrapping object;
 * @param data.token the conversation token;
 * @param data.lastKnownMessageId last known message id;
 * @param data.includeLastKnown whether to include the last known message in the response;
 * @param data.threadId The thread id to retrieve data
 * @param [data.lookIntoFuture] direction of message fetch
 * @param [data.limit] Number of messages to load
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
 * @param [data.limit] Number of messages to load
 * @param data.timeout Timeout duration for long polling
 * @param [options] Axios request options
 */
async function pollNewMessages({
	token,
	lastKnownMessageId,
	limit = 100,
	timeout,
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
			timeout,
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
 * @param [data.limit] Number of messages to load
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
 * @param payload.threadId The thread id to post the message in
 * @param payload.threadTitle The thread title to set when creating a new thread
 * @param [options] Axios request options
 */
async function postNewMessage({
	token,
	message,
	actorDisplayName,
	referenceId,
	replyTo,
	silent,
	threadId,
	threadTitle,
}: postNewMessageParams & { token: string }, options?: AxiosRequestConfig): postNewMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), {
		message,
		actorDisplayName,
		referenceId,
		replyTo,
		silent,
		threadId,
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
 * Pin a message in a conversation
 *
 * @param data The destructured payload
 * @param data.token The conversation token
 * @param data.messageId The message id
 * @param data.pinUntil The timestamp until the message should be pinned
 * @param [options] Axios request options
 */
async function pinMessage({ token, messageId, pinUntil }: PinMessagePayload, options?: AxiosRequestConfig): pinMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/pin', { token, messageId }), {
		pinUntil,
	} as pinMessageParams, options)
}

/**
 * Unpin a message in a conversation
 *
 * @param data The destructured payload
 * @param data.token The conversation token
 * @param data.messageId The message id
 * @param [options] Axios request options
 */
async function unpinMessage({ token, messageId }: { token: string, messageId: number }, options?: AxiosRequestConfig): unpinMessageResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/pin', { token, messageId }), options)
}

/**
 * Hide a pinned message for the current user in a conversation
 *
 * @param data The destructured payload
 * @param data.token The conversation token
 * @param data.messageId The message id
 * @param [options] Axios request options
 */
async function hidePinnedMessage({ token, messageId }: { token: string, messageId: number }, options?: AxiosRequestConfig): hidePinnedMessageResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/pin/self', { token, messageId }), options)
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
 * @param data.threadId thread id to retrieve data
 * @param [options] Axios request options
 */
async function postRichObjectToConversation(token: string, { objectType, objectId, metaData, referenceId, threadId }: postRichObjectParams, options?: AxiosRequestConfig): postRichObjectResponse {
	if (!referenceId) {
		const tempId = 'richobject-' + objectType + '-' + objectId + '-' + token + '-' + (new Date().getTime())
		referenceId = Hex.stringify(SHA256(tempId))
	}
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share', { token }), {
		objectType,
		objectId,
		metaData,
		referenceId,
		threadId,
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
 * @param [data.limit] Number of threads to return
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
 * Fetch a list of threads user subscribed to
 *
 * @param data the wrapping object
 * @param [data.limit] Number of threads to return
 * @param [data.offset] Thread offset to fetch from
 * @param [options] Axios request options
 */
async function getSubscribedThreads({ limit, offset }: getSubscribedThreadsParams = {}, options?: AxiosRequestConfig): getSubscribedThreadsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/subscribed-threads'), {
		...options,
		params: {
			limit,
			offset,
		},
	})
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

/**
 * Fetch a thread for given conversation and thread id
 *
 * @param token the conversation token
 * @param threadId The thread id to retrieve data
 * @param threadTitle The new thread title
 * @param [options] Axios request options
 */
async function renameThread(token: string, threadId: number, threadTitle: string, options?: AxiosRequestConfig): renameThreadResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/chat/{token}/threads/{threadId}', { token, threadId }), {
		threadTitle,
	} as renameThreadParams, options)
}

/**
 * Get a list of scheduled messages of this user for given conversation
 *
 * @param token the conversation token
 * @param [options] Axios request options
 */
async function getScheduledMessages(token: string, options?: AxiosRequestConfig): getScheduledMessagesResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/schedule', { token }), options)
}

/**
 * Schedules a new message to be poster
 *
 * @param payload The request payload
 * @param payload.token The conversation token
 * @param payload.message The message text
 * @param payload.sendAt The timestamp of when message should be posted
 * @param payload.replyTo The message id to be replied to
 * @param payload.silent whether the message should trigger a notifications
 * @param payload.threadId The thread id to post the message in
 * @param payload.threadTitle The thread title to set when creating a new thread
 * @param [options] Axios request options
 */
async function scheduleMessage({
	token,
	message,
	sendAt,
	replyTo,
	silent,
	threadId,
	threadTitle,
}: scheduleMessageParams & { token: string }, options?: AxiosRequestConfig): scheduleMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/schedule', { token }), {
		message,
		sendAt,
		replyTo,
		silent,
		threadId,
		threadTitle,
	} as scheduleMessageParams, options)
}

/**
 * Edit an already scheduled message
 *
 * @param payload The request payload
 * @param payload.token The conversation token
 * @param payload.messageId The id of scheduled message
 * @param payload.message The message text
 * @param payload.sendAt The timestamp of when message should be posted
 * @param payload.silent whether the message should trigger a notifications
 * @param payload.threadTitle The thread title to set when creating a new thread
 * @param [options] Axios request options
 */
async function editScheduledMessage({
	token,
	messageId,
	message,
	sendAt,
	silent,
	threadTitle,
}: editScheduledMessageParams & { token: string, messageId: string }, options?: AxiosRequestConfig): editScheduledMessageResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/schedule/{messageId}', { token, messageId }), {
		message,
		sendAt,
		silent,
		threadTitle,
	} as editScheduledMessageParams, options)
}

/**
 * Delete a scheduled message from the queue
 *
 * @param token The conversation token
 * @param messageId The id of scheduled message
 * @param [options] Axios request options
 */
async function deleteScheduledMessage(token: string, messageId: string, options?: AxiosRequestConfig): deleteScheduledMessageResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/schedule/{messageId}', { token, messageId }), options)
}

export {
	clearConversationHistory,
	deleteMessage,
	deleteScheduledMessage,
	editMessage,
	editScheduledMessage,
	fetchMessages,
	getMessageContext,
	getRecentThreadsForConversation,
	getScheduledMessages,
	getSingleThreadForConversation,
	getSubscribedThreads,
	hidePinnedMessage,
	pinMessage,
	pollNewMessages,
	postNewMessage,
	postRichObjectToConversation,
	renameThread,
	scheduleMessage,
	setConversationUnread,
	setThreadNotificationLevel,
	summarizeChat,
	unpinMessage,
	updateLastReadMessage,
}

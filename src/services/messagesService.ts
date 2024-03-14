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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import type {
	ChatMessage,
	deleteMessageResponse,
	editMessageParams,
	editMessageResponse,
	getMessageContextParams,
	getMessageContextResponse,
	postNewMessageParams,
	postNewMessageResponse,
	postRichObjectParams,
	postRichObjectResponse,
	receiveMessagesParams,
	receiveMessagesResponse,
	setReadMarkerParams,
	setReadMarkerResponse
} from '../types'

type ReceiveMessagesPayload = Partial<receiveMessagesParams> & { token: string }
type GetMessageContextPayload = getMessageContextParams & { token: string, messageId: number }
type PostNewMessagePayload = Omit<postNewMessageParams, 'replyTo'> & { token: string, parent: ChatMessage }
type PostNewMessageOptions = Pick<postNewMessageParams, 'silent'> & object
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
 * @param [data.limit=100] Number of messages to load
 * @param options options;
 */
const fetchMessages = async function({ token, lastKnownMessageId, includeLastKnown, limit = 100 }: ReceiveMessagesPayload, options: object): receiveMessagesResponse {
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
const lookForNewMessages = async ({ token, lastKnownMessageId, limit = 100 }: ReceiveMessagesPayload, options: object): receiveMessagesResponse => {
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
const getMessageContext = async function({ token, messageId, limit = 50 }: GetMessageContextPayload, options: object): getMessageContextResponse {
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
 * Deletes a message from the server.
 *
 * @param param0 The message object that is destructured
 * @param param0.token The conversation token
 * @param param0.id The id of the message to be deleted
 * @param options request options
 */
const deleteMessage = async function({ token, id }: DeleteMessagePayload, options: object): deleteMessageResponse {
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
const editMessage = async function({ token, messageId, updatedMessage }: EditMessagePayload, options: object): editMessageResponse {
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
const postRichObjectToConversation = async function(token: string, { objectType, objectId, metaData, referenceId }: postRichObjectParams, options: object): postRichObjectResponse {
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
const updateLastReadMessage = async function(token: string, lastReadMessage: setReadMarkerParams, options: object): setReadMarkerResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }, options), {
		lastReadMessage,
	} as setReadMarkerParams, options)
}

const addReactionToMessage = async function(token, messageId, selectedEmoji, options) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }, options), {
		reaction: selectedEmoji,
	}, options)
}

const removeReactionFromMessage = async function(token, messageId, selectedEmoji, options) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }, options), {
		...options,
		params: {
			reaction: selectedEmoji,
		},
	})
}

const getReactionsDetails = async function(token, messageId, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/reaction/{token}/{messageId}', { token, messageId }, options), options)
}

const getTranslationLanguages = async function(options) {
	return axios.get(generateOcsUrl('/translation/languages', undefined, options), options)
}

const translateText = async function(text, fromLanguage, toLanguage, options) {
	return axios.post(generateOcsUrl('/translation/translate', undefined, options), {
		text,
		fromLanguage,
		toLanguage,
	}, options)
}

export {
	fetchMessages,
	lookForNewMessages,
	getMessageContext,
	postNewMessage,
	deleteMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
	addReactionToMessage,
	removeReactionFromMessage,
	getReactionsDetails,
	editMessage,
	getTranslationLanguages,
	translateText,
}

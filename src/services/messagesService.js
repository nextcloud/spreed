/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import SHA256 from 'crypto-js/sha256'
import Hex from 'crypto-js/enc-hex'

/**
 * Fetches messages that belong to a particular conversation
 * specified with its token.
 *
 * @param {string} token the conversation token;
 * @param {object} options options;
 * @param {string} lastKnownMessageId last known message id;
 * @param {bool} includeLastKnown whether to include the last known message in the response;
 */
const fetchMessages = async function({ token, lastKnownMessageId, includeLastKnown }, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), Object.assign(options, {
		params: {
			setReadMarker: 0,
			lookIntoFuture: 0,
			lastKnownMessageId,
			includeLastKnown: includeLastKnown ? 1 : 0,
		},
	}))
}

/**
 * Fetches newly created messages that belong to a particular conversation
 * specified with its token.
 *
 * @param {string} token The conversation token;
 * @param {object} options options
 * @param {int} lastKnownMessageId The id of the last message in the store.
 */
const lookForNewMessages = async({ token, lastKnownMessageId }, options) => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), Object.assign(options, {
		params: {
			setReadMarker: 0,
			lookIntoFuture: 1,
			lastKnownMessageId,
			includeLastKnown: 0,
		},
	}))
}

/**
 * Posts a new message to the server.
 *
 * @param {object} param0 The message object that is destructured
 * @param {string} param0.token The conversation token
 * @param {string} param0.message The message object
 * @param {string} param0.referenceId A reference id to identify the message later again
 * @param {Number} param0.parent The id of the message to be replied to
 * @param {object} options request options
 */
const postNewMessage = async function({ token, message, actorDisplayName, referenceId, parent }, options) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }), {
		message,
		actorDisplayName,
		referenceId,
		replyTo: parent,
	}, options)
}

/**
 * Deletes a message from the server.
 *
 * @param {object} param0 The message object that is destructured
 * @param {string} token The conversation token
 * @param {string} id The id of the message to be deleted
 */
const deleteMessage = async function({ token, id }) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{id}', { token, id }))
}

/**
 * Post a rich object to a conversation
 *
 * @param {string} token conversation token
 * @param {string} objectType object type
 * @param {string} objectId object id
 * @param {string} metaData JSON metadata of the rich object encoded as string
 * @param {string} referenceId generated reference id, leave empty to generate it based on the other args
 */
const postRichObjectToConversation = async function(token, { objectType, objectId, metaData, referenceId }) {
	if (!referenceId) {
		const tempId = 'richobject-' + objectType + '-' + objectId + '-' + token + '-' + (new Date().getTime())
		referenceId = Hex.stringify(SHA256(tempId))
	}
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/share', { token }), {
		objectType,
		objectId,
		metaData,
		referenceId,
	})
}

/**
 * Updates the last read message id
 *
 * @param {string} token The token of the conversation to be removed from favorites
 * @param {int} lastReadMessage id of the last read message to set
 */
const updateLastReadMessage = async function(token, lastReadMessage) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }), {
		lastReadMessage,
	})
}

export {
	fetchMessages,
	lookForNewMessages,
	postNewMessage,
	deleteMessage,
	postRichObjectToConversation,
	updateLastReadMessage,
}

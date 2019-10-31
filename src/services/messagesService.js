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
import { generateOcsUrl } from 'nextcloud-router'

const CANCEL_TOKEN = axios.CancelToken

const cancelableFetchMessages = function() {
	/**
	 * cancelToken= the token that gets injected into the axios method and links it
	 * to the cancel function;
	 * cancel= function that allows to delete the api call;
	 */
	const { token: cancelToken, cancel: cancelFetchMessages } = CANCEL_TOKEN.source()
	/**
	 * Fetches messages that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {string} token The conversation token;
	 */
	const fetchMessages = async function(token) {
		try {
			const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/chat', 2) + token + '?lookIntoFuture=0', { cancelToken })
			return response
		} catch (exception) {
			if (!exception.response || !exception.response.status || exception.response.status !== 304) {
				console.debug('Error while fetching messages: ', exception)
			}
		}
	}
	return {
		fetchMessages,
		cancelFetchMessages,
	}
}

/**
 * Creates a cancelable axios 'request object'. We need this in order to cancel
 * long polling requests for new messages of previous conversatios when
 * switching to a new one.
 * @returns {object} An object that contains 2 functions:
 * - `lookForNewMessages` : the api call async function with the injected cancel
 * token;
 * - `cancel` : the function that allows to cancel that particular a call;
 */
const cancelableLookForNewMessages = function() {
	/**
	 * cancelToken= the token that gets injected into the axios method and links it
	 * to the cancel function;
	 * cancel= function that allows to delete the api call;
	 */
	const { token: cancelToken, cancel: cancelLookForNewMessages } = CANCEL_TOKEN.source()
	/**
	 * Fetches newly created messages that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {string} token The conversation token;
	 * @param {int} lastKnownMessageId The id of the last message in the store.
	 */
	const lookForNewMessages = async(token, lastKnownMessageId) => {
		try {
			const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/chat', 2) + token + '?lookIntoFuture=1' + '&includeLastKnown=0' + `&lastKnownMessageId=${lastKnownMessageId}`, { cancelToken })
			return response
		} catch (exception) {
			if (axios.isCancel(exception)) {
				console.debug(exception.message)
				return exception.message
			} else if (!exception.response || !exception.response.status || exception.response.status !== 304) {
				console.debug('Error while looking for new messages: ', exception)
			}
		}
	}
	return {
		lookForNewMessages,
		cancelLookForNewMessages,
	}
}

/**
 * Posts a new messageto the server.
 *
 * @param {object} param0 The message object that is destructured;
 * @param {string} token The conversation token;
 * @param {string} message The message object;
 * @param {Number} parent The id of the message to be replied to.
 */
const postNewMessage = async function({ token, message, parent }) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1/chat', 2) + token, { message, actorDisplayName: '', replyTo: parent })
		return response
	} catch (error) {
		console.debug(error)
	}
}

export {
	cancelableFetchMessages,
	cancelableLookForNewMessages,
	postNewMessage,
}

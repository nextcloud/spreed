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

import axios from 'nextcloud-axios'
import { generateOcsUrl } from 'nextcloud-router'

/**
 * Fetches messages that belong to a particular conversation
 * specified with its token.
 *
 * @param {string} token The conversation token;
 */
const fetchMessages = async function(token) {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/chat', 2) + token + '?lookIntoFuture=0')
		return response
	} catch (error) {
		console.debug('Error while fetching messages: ', error)
	}
}

/**
 * Posts a new messageto the server.
 *
 * @param {Object} param0 The message object that is destructured;
 * @param {String} token The conversation token;
 * @param {Object} message The message object.
 */
const postNewMessage = async function({ token, message }) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1/chat', 2) + token, { message, actorDisplayName: '' })
		return response
	} catch (error) {
		console.debug(error)
	}
}

export { fetchMessages, postNewMessage }

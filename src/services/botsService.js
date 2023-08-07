/**
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Get information about available bots for this conversation
 *
 * @param {string} token The conversation token
 * @return {object} The axios response
 */
const getConversationBots = async function(token) {
	return axios.get(generateOcsUrl('/apps/spreed/api/v1/bot/{token}', { token }))
}

/**
 * Enable bot for conversation
 *
 * @param {string} token The conversation token
 * @param {number} id The bot id
 * @return {object} The axios response
 */
const enableBotForConversation = async function(token, id) {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

/**
 * Disable bot for conversation
 *
 * @param {string} token The conversation token
 * @param {number} id The bot id
 * @return {object} The axios response
 */
const disableBotForConversation = async function(token, id) {
	return axios.delete(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/{id}', { token, id }))
}

/**
 * Send a message to bot in conversation
 *
 * @param {string} token The conversation token
 * @param {object} object Object with arguments
 * @param {string} object.message The message to send
 * @param {string} object.referenceId for the message to be able to later identify it again
 * @param {number} object.replyTo Parent id which this message is a reply to
 * @param {boolean} object.silent If sent silent the chat message will not create any notifications
 * @return {object} The axios response
 */
const sendMessageToBot = async function(token, { message, referenceId, replyTo, silent }) {
	return axios.post(generateOcsUrl('/apps/spreed/api/v1/bot/{token}/message', { token }), {
		message,
		referenceId,
		replyTo,
		silent,
	})
}

export {
	getConversationBots,
	enableBotForConversation,
	disableBotForConversation,
	sendMessageToBot,
}

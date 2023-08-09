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
 * Sets the reminder with defined timestamp on the message for the user
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @return {object} The axios response
 */
const getMessageReminder = async function(token, messageId) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}))
}

/**
 * Sets the reminder with defined timestamp on the message for the user
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @param {number} timestamp The timestamp of reminder (in seconds)
 * @return {object} The axios response
 */
const setMessageReminder = async function(token, messageId, timestamp) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}), { timestamp })
}

/**
 * Removes the reminder from the message
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @return {object} The axios response
 */
const removeMessageReminder = async function(token, messageId) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}))
}

export {
	getMessageReminder,
	setMessageReminder,
	removeMessageReminder,
}

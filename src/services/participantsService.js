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
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {String} token The conversation token;
 */
const joinConversation = async function(token) {
	try {
		const response = await axios.post(generateOcsUrl(`room/${token}/participants/active`))
		return response
	} catch (error) {
		console.debug(error)
	}
}

/**
 * Leaves the conversation specified with the token.
 *
 * @param {String} token The conversation token;
 */
const leaveConversation = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/participants/active`)
		return response
	} catch (error) {
		console.debug(error)
	}
}

/**
 * Removes the the current user from the conversation specified with the token.
 *
 * @param {String} token The conversation token;
 */
const removeCurrentUserFromConversation = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/participants/self`)
		return response
	} catch (error) {
		console.debug(error)
	}
}

export {
	joinConversation,
	leaveConversation,
	removeCurrentUserFromConversation
}

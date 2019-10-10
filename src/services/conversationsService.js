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
 * Fetches the conversations from the server.
 */
const fetchConversations = async function() {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1', 2) + 'room')
		return response
	} catch (error) {
		console.debug('Error while fetching conversations: ', error)
	}
}

/**
 * Fetch possible conversations
 * @param {string} searchText The string that will be used in the search query.
 */
const searchPossibleConversations = async function(searchText) {
	try {
		const response = await axios.get(generateOcsUrl('core/autocomplete', 2) + `get` + `?format=json` + `&search=${searchText}` + `&itemType=call` + `&itemId=new` + `&shareTypes[]=${OC.Share.SHARE_TYPE_USER}&shareTypes[]=${OC.Share.SHARE_TYPE_GROUP}`)
		return response
	} catch (error) {
		console.debug('Error while searching possible conversations: ', error)
	}
}

/**
 * Create a new one to one conversation with the specified user.
 * @param {string} userId The ID of the user with wich the new conversation will be opened.
 */
const createOneToOneConversation = async function(userId) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: 1, invite: userId })
		return response
	} catch (error) {
		console.debug('Error creating new one to one conversation: ', error)
	}
}

/**
 * Create a new group conversation.
 * @param {string} groupId The group ID, this parameter is optional.
 */
const createGroupConversation = async function(groupId) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: 2, invite: groupId })
		return response
	} catch (error) {
		console.debug('Error creating new one to one conversation: ', error)
	}
}

/**
 * Delete a conversation.
 * @param {string} token The token of the conversation to be deleted.
 */
const deleteConversation = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}`)
		return response
	} catch (error) {
		console.debug('Error while deleting the conversation: ', error)
	}
}

export {
	fetchConversations,
	searchPossibleConversations,
	createOneToOneConversation,
	createGroupConversation,
	deleteConversation }

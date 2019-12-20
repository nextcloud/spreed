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
import { CONVERSATION, SHARE } from '../constants'

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
 * Fetches a conversation from the server.
 * @param {string} token The token of the conversation to be fetched.
 */
const fetchConversation = async function(token) {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}`)
		return response
	} catch (error) {
		console.debug('Error while fetching a conversation: ', error)
	}
}

/**
 * Fetch possible conversations
 * @param {string} searchText The string that will be used in the search query.
 */
const searchPossibleConversations = async function(searchText) {
	try {
		const response = await axios.get(generateOcsUrl('core/autocomplete', 2) + `get` + `?format=json` + `&search=${searchText}` + `&itemType=call` + `&itemId=new` + `&shareTypes[]=${SHARE.TYPE.USER}&shareTypes[]=${SHARE.TYPE.GROUP}&shareTypes[]=${SHARE.TYPE.CIRCLE}`)
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
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: CONVERSATION.TYPE.ONE_TO_ONE, invite: userId })
		return response
	} catch (error) {
		console.debug('Error creating new one to one conversation: ', error)
	}
}

/**
 * Create a new group conversation.
 * @param {string} invite The group/circle ID
 * @param {string} source The source of the invite ID (defaults to groups)
 */
const createGroupConversation = async function(invite, source) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: CONVERSATION.TYPE.GROUP, invite, source: source || 'groups' })
		return response
	} catch (error) {
		console.debug('Error creating new group conversation: ', error)
	}
}

/**
 * Create a new private conversation.
 * @param {string} conversationName The name for the new conversation
 */
const createPrivateConversation = async function(conversationName) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: CONVERSATION.TYPE.GROUP, roomName: conversationName })
		return response
	} catch (error) {
		console.debug('Error creating new private conversation: ', error)
	}
}

/**
 * Create a new private conversation.
 * @param {string} conversationName The name for the new conversation
 */
const createPublicConversation = async function(conversationName) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room`, { roomType: CONVERSATION.TYPE.PUBLIC, roomName: conversationName })
		return response
	} catch (error) {
		console.debug('Error creating new public conversation: ', error)
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

/**
 * Add a conversation to the favorites
 * @param {string} token The token of the conversation to be favorites
 */
const addToFavorites = async function(token) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/favorite`)
		return response
	} catch (error) {
		console.debug('Error while adding the conversation to favorites: ', error)
	}
}

/**
 * Remove a conversation from the favorites
 * @param {string} token The token of the conversation to be removed from favorites
 */
const removeFromFavorites = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/favorite`)
		return response
	} catch (error) {
		console.debug('Error while removing the conversation from favorites: ', error)
	}
}

/**
 * Remove a conversation from the favorites
 * @param {string} token The token of the conversation to be removed from favorites
 * @param {int} level The notification level to set.
 */
const setNotificationLevel = async function(token, level) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/notify`, { level })
		return response
	} catch (error) {
		console.debug('Error while setting the notification level: ', error)
	}
}

/**
 * Make the conversation public
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makePublic = async function(token) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/public`)
		return response
	} catch (error) {
		console.debug('Error while making the conversation public: ', error)
	}
}

/**
 * Make the conversation private
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makePrivate = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/public`)
		return response
	} catch (error) {
		console.debug('Error while making the conversation private: ', error)
	}
}

/**
 * Change the lobby state
 * @param {string} token The token of the conversation to be modified
 * @param {int} newState The new lobby state to set
 */
const changeLobbyState = async function(token, newState) {
	try {
		const response = await axios.put(generateOcsUrl('apps/spreed/api/v1', 2) + `room/${token}/webinar/lobby`, {
			state: newState,
		})
		return response
	} catch (error) {
		console.debug('Error while updating webinar lobby: ', error)
	}
}

export {
	fetchConversations,
	fetchConversation,
	searchPossibleConversations,
	createOneToOneConversation,
	createGroupConversation,
	createPrivateConversation,
	createPublicConversation,
	deleteConversation,
	addToFavorites,
	removeFromFavorites,
	setNotificationLevel,
	makePublic,
	makePrivate,
	changeLobbyState,
}

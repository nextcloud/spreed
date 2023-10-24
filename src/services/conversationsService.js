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

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateOcsUrl } from '@nextcloud/router'

import { CONVERSATION, SHARE } from '../constants.js'

/**
 * Fetches the conversations from the server.
 *
 * @param {object} options options
 */
const fetchConversations = async function(options) {
	options = options || {}
	options.params = options.params || {}
	options.params.includeStatus = true
	return await axios.get(generateOcsUrl('apps/spreed/api/v4/room'), options)
}

/**
 * Fetches a conversation from the server.
 *
 * @param {string} token The token of the conversation to be fetched.
 */
const fetchConversation = async function(token) {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }))
}

/**
 * Fetch listed conversations
 *
 * @param {string} searchText The string that will be used in the search query.
 * @param {object} options options
 */
const searchListedConversations = async function({ searchText }, options) {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/listed-room'), Object.assign(options, {
		params: {
			searchTerm: searchText,
		},
	}))
}

/**
 * Generate note-to-self conversation
 *
 */
const fetchNoteToSelfConversation = async function() {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/note-to-self'))
}

/**
 * Fetch possible conversations
 *
 * @param {object} data the wrapping object;
 * @param {string} data.searchText The string that will be used in the search query.
 * @param {string} [data.token] The token of the conversation (if any), or "new" for a new one
 * @param {boolean} [data.onlyUsers] Only return users
 * @param {object} options options
 */
const searchPossibleConversations = async function({ searchText, token, onlyUsers }, options) {
	token = token || 'new'
	onlyUsers = !!onlyUsers
	const shareTypes = [
		SHARE.TYPE.USER,
	]

	if (!onlyUsers) {
		shareTypes.push(SHARE.TYPE.GROUP)
		shareTypes.push(SHARE.TYPE.CIRCLE)
		if (token !== 'new') {
			shareTypes.push(SHARE.TYPE.EMAIL)

			if (loadState('spreed', 'federation_enabled')) {
				shareTypes.push(SHARE.TYPE.REMOTE)
			}
		}
	}

	return axios.get(generateOcsUrl('core/autocomplete/get'), Object.assign(options, {
		params: {
			search: searchText,
			itemType: 'call',
			itemId: token,
			shareTypes,
		},
	}))
}

/**
 * Create a new one to one conversation with the specified user.
 *
 * @param {string} userId The ID of the user with wich the new conversation will be opened.
 */
const createOneToOneConversation = async function(userId) {
	try {
		return await axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
			roomType: CONVERSATION.TYPE.ONE_TO_ONE,
			invite: userId
		})
	} catch (error) {
		console.debug('Error creating new one to one conversation: ', error)
	}
}

/**
 * Create a new group conversation.
 *
 * @param {string} invite The group/circle ID
 * @param {string} source The source of the invite ID (defaults to groups)
 */
const createGroupConversation = async function(invite, source) {
	try {
		return await axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
			roomType: CONVERSATION.TYPE.GROUP,
			invite,
			source: source || 'groups'
		})
	} catch (error) {
		console.debug('Error creating new group conversation: ', error)
	}
}

/**
 * Create a new private conversation.
 *
 * @param {string} conversationName The name for the new conversation
 * @param {string} [objectType] The conversation object type
 */
const createPrivateConversation = async function(conversationName, objectType) {
	try {
		return await axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
			roomType: CONVERSATION.TYPE.GROUP,
			roomName: conversationName,
			objectType,
		})
	} catch (error) {
		console.debug('Error creating new private conversation: ', error)
	}
}

/**
 * Create a new private conversation.
 *
 * @param {string} conversationName The name for the new conversation
 * @param {string} [objectType] The conversation object type
 */
const createPublicConversation = async function(conversationName, objectType) {
	try {
		return await axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
			roomType: CONVERSATION.TYPE.PUBLIC,
			roomName: conversationName,
			objectType,
		})
	} catch (error) {
		console.debug('Error creating new public conversation: ', error)
	}
}

/**
 * Set a conversation's password
 *
 * @param {string} token the conversation's token
 * @param {string} password the password to be set
 */
const setConversationPassword = async function(token, password) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/password', { token }), {
		password,
	})
	return response
}

/**
 * Set a conversation's name
 *
 * @param {string} token the conversation's token
 * @param {string} name the name to be set
 */
const setConversationName = async function(token, name) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }), {
		roomName: name,
	})
	return response
}

/**
 * Delete a conversation.
 *
 * @param {string} token The token of the conversation to be deleted.
 */
const deleteConversation = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }))
		return response
	} catch (error) {
		console.debug('Error while deleting the conversation: ', error)
	}
}

/**
 * Clears the conversation history
 *
 * @param {string} token The token of the conversation to be deleted.
 */
const clearConversationHistory = async function(token) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}', { token }))
	return response
}

/**
 * Set conversation as unread
 *
 * @param {string} token The token of the conversation to be set as unread
 */
const setConversationUnread = async function(token) {
	try {
		const response = axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/read', { token }))
		return response
	} catch (error) {
		console.debug('Error while setting the conversation as unread: ', error)
	}
}

/**
 * Add a conversation to the favorites
 *
 * @param {string} token The token of the conversation to be favorites
 */
const addToFavorites = async function(token) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
		return response
	} catch (error) {
		console.debug('Error while adding the conversation to favorites: ', error)
	}
}

/**
 * Remove a conversation from the favorites
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const removeFromFavorites = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
		return response
	} catch (error) {
		console.debug('Error while removing the conversation from favorites: ', error)
	}
}

/**
 * Set notification level
 *
 * @param {string} token The token of the conversation to change the notification level
 * @param {number} level The notification level to set.
 */
const setNotificationLevel = async function(token, level) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify', { token }), { level })
		return response
	} catch (error) {
		console.debug('Error while setting the notification level: ', error)
	}
}

/**
 * Set call notifications
 *
 * @param {string} token The token of the conversation to change the call notification level
 * @param {number} level The call notification level.
 */
const setNotificationCalls = async function(token, level) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify-calls', { token }), { level })
		return response
	} catch (error) {
		console.debug('Error while setting the call notification level: ', error)
	}
}

/**
 * Make the conversation public
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makePublic = async function(token) {
	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }))
		return response
	} catch (error) {
		console.debug('Error while making the conversation public: ', error)
	}
}

/**
 * Make the conversation private
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makePrivate = async function(token) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }))
		return response
	} catch (error) {
		console.debug('Error while making the conversation private: ', error)
	}
}

/**
 * Change the SIP enabled
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} newState The new SIP state to set
 */
const setSIPEnabled = async function(token, newState) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/webinar/sip', { token }), {
		state: newState,
	})
}

/**
 * Change the recording consent per conversation
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} newState The new recording consent state to set
 */
const setRecordingConsent = async function(token, newState) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/recording-consent', { token }), {
		recordingConsent: newState,
	})
}

/**
 * Change the lobby state
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} newState The new lobby state to set
 * @param {number} timestamp The UNIX timestamp (in seconds) to set, if any
 */
const changeLobbyState = async function(token, newState, timestamp) {
	try {
		const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/webinar/lobby', { token }), {
			state: newState,
			timer: timestamp,
		})
		return response
	} catch (error) {
		console.debug('Error while updating webinar lobby: ', error)
	}
}

/**
 * Change the read-only state
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} readOnly The new read-only state to set
 */
const changeReadOnlyState = async function(token, readOnly) {
	try {
		const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/read-only', { token }), {
			state: readOnly,
		})
		return response
	} catch (error) {
		console.debug('Error while updating read-only state: ', error)
	}
}

/**
 * Change the listable scope
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} listable The new listable scope to set
 */
const changeListable = async function(token, listable) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/listable', { token }), {
		scope: listable,
	})
	return response
}

const setConversationDescription = async function(token, description) {
	const response = await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/description', { token }), {
		description,
	})
	return response
}

/**
 * Set the default permissions for participants in a conversation.
 *
 * @param {string} token conversation token
 * @param {number} permissions the type of permission to be granted. Valid values are
 * any sums of 'DEFAULT', 'CUSTOM', 'CALL_START', 'CALL_JOIN', 'LOBBY_IGNORE',
 * 'PUBLISH_AUDIO', 'PUBLISH_VIDEO', 'PUBLISH_SCREEN'.
 */
const setConversationPermissions = async (token, permissions) => {
	await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/default', { token }),
		{
			permissions,
		})
}

/**
 * Set the default permissions for participants in a call. These will be reset
 * to default once the call has ended.
 *
 * @param {string} token conversation token
 * @param {number} permissions the type of permission to be granted. Valid values are
 * any sums of 'DEFAULT', 'CUSTOM', 'CALL_START', 'CALL_JOIN', 'LOBBY_IGNORE',
 * 'PUBLISH_AUDIO', 'PUBLISH_VIDEO', 'PUBLISH_SCREEN'.
 */
const setCallPermissions = async (token, permissions) => {
	await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/call', { token }),
		{
			permissions,
		})
}

/**
 * Set the message expiration
 *
 * @param {string} token conversation token
 * @param {number} seconds the seconds for the message expiration, 0 to disable
 */
const setMessageExpiration = async (token, seconds) => {
	return await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/message-expiration', { token }), {
		seconds,
	})
}

const validatePassword = async (password) => {
	return await axios.post(generateOcsUrl('apps/password_policy/api/v1/validate'), {
		password,
	})
}

const setConversationAvatar = async function(token, file) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }), file,)
}

const setConversationEmojiAvatar = async function(token, emoji, color) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar/emoji', { token }), { emoji, color })
}

const deleteConversationAvatar = async function(token) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/room/{token}/avatar', { token }))
}

export {
	fetchConversations,
	fetchConversation,
	fetchNoteToSelfConversation,
	searchListedConversations,
	searchPossibleConversations,
	createOneToOneConversation,
	createGroupConversation,
	createPrivateConversation,
	createPublicConversation,
	deleteConversation,
	addToFavorites,
	removeFromFavorites,
	setNotificationLevel,
	setNotificationCalls,
	makePublic,
	makePrivate,
	setSIPEnabled,
	setRecordingConsent,
	changeLobbyState,
	changeReadOnlyState,
	changeListable,
	setConversationPassword,
	setConversationName,
	setConversationDescription,
	clearConversationHistory,
	setConversationUnread,
	setConversationPermissions,
	setCallPermissions,
	setMessageExpiration,
	validatePassword,
	setConversationAvatar,
	setConversationEmojiAvatar,
	deleteConversationAvatar,
}

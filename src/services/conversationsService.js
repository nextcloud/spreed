/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { ATTENDEE, CONVERSATION } from '../constants.js'

/**
 * Fetches the conversations from the server.
 *
 * @param {object} options options
 */
const fetchConversations = async function(options) {
	options = options || {}
	options.params = options.params || {}
	options.params.includeStatus = true
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room'), options)
}

/**
 * fetch future events for a given conversation within the next 31 days.
 *
 * @param {string} location room's absolute url
 */
const getUpcomingEvents = async (location) => {
	return axios.get(generateOcsUrl('/apps/dav/api/v1/events/upcoming'), {
		params: {
			location,
		},
	})
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
	return axios.get(generateOcsUrl('apps/spreed/api/v4/listed-room'), {
		...options,
		params: {
			searchTerm: searchText,
		},
	})
}

/**
 * Generate note-to-self conversation
 *
 */
const fetchNoteToSelfConversation = async function() {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/note-to-self'))
}

/**
 * Create a new one to one conversation with the specified user.
 *
 * @param {string} userId The ID of the user with which the new conversation will be opened.
 */
const createOneToOneConversation = async function(userId) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.ONE_TO_ONE,
		invite: userId,
	})
}

/**
 * Create a new group conversation.
 *
 * @param {string} invite The group/circle ID
 * @param {string} source The source of the invite ID (defaults to groups)
 */
const createGroupConversation = async function(invite, source) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.GROUP,
		invite,
		source: source || ATTENDEE.ACTOR_TYPE.GROUPS,
	})
}

/**
 * Create a new private conversation.
 *
 * @param {string} conversationName The name for the new conversation
 * @param {string} [objectType] The conversation object type
 */
const createPrivateConversation = async function(conversationName, objectType) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.GROUP,
		roomName: conversationName,
		objectType,
	})
}

/**
 * Create a new private conversation.
 *
 * @param {string} conversationName The name for the new conversation
 * @param {string} [objectType] The conversation object type
 */
const createPublicConversation = async function(conversationName, objectType) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.PUBLIC,
		roomName: conversationName,
		objectType,
	})
}

/**
 * Set a conversation's password
 *
 * @param {string} token the conversation's token
 * @param {string} password the password to be set
 */
const setConversationPassword = async function(token, password) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/password', { token }), {
		password,
	})
}

/**
 * Set a conversation's name
 *
 * @param {string} token the conversation's token
 * @param {string} name the name to be set (max 255 characters)
 */
const setConversationName = async function(token, name) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }), {
		roomName: name,
	})
}

/**
 * Set a conversation's description
 *
 * @param {string} token the conversation's token
 * @param {string} description the description to be set (max 500 characters)
 */
const setConversationDescription = async function(token, description) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/description', { token }), {
		description,
	})
}

/**
 * Delete a conversation.
 *
 * @param {string} token The token of the conversation to be deleted.
 */
const deleteConversation = async function(token) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }))
}

/**
 * Add a conversation to the favorites
 *
 * @param {string} token The token of the conversation to be favorites
 */
const addToFavorites = async function(token) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
}

/**
 * Remove a conversation from the favorites
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const removeFromFavorites = async function(token) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
}

/**
 * Add a conversation to the archive
 *
 * @param {string} token The token of the conversation to be archived
 */
const archiveConversation = async function(token) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/archive', { token }))
}

/**
 * Restore a conversation from the archive
 *
 * @param {string} token The token of the conversation to be removed from archive
 */
const unarchiveConversation = async function(token) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/archive', { token }))
}

/**
 * Set notification level
 *
 * @param {string} token The token of the conversation to change the notification level
 * @param {number} level The notification level to set.
 */
const setNotificationLevel = async function(token, level) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify', { token }), { level })
}

/**
 * Set call notifications
 *
 * @param {string} token The token of the conversation to change the call notification level
 * @param {number} level The call notification level.
 */
const setNotificationCalls = async function(token, level) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify-calls', { token }), { level })
}

/**
 * Make the conversation public
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makeConversationPublic = async function(token) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }))
}

/**
 * Make the conversation private
 *
 * @param {string} token The token of the conversation to be removed from favorites
 */
const makeConversationPrivate = async function(token) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }))
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
 * @param {number} [timestamp] The UNIX timestamp (in seconds) to set, if any
 */
const changeLobbyState = async function(token, newState, timestamp) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/webinar/lobby', { token }), {
		state: newState,
		timer: timestamp,
	})
}

/**
 * Change the read-only state
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} readOnly The new read-only state to set
 */
const changeReadOnlyState = async function(token, readOnly) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/read-only', { token }), {
		state: readOnly,
	})
}

/**
 * Change the listable scope
 *
 * @param {string} token The token of the conversation to be modified
 * @param {number} listable The new listable scope to set
 */
const changeListable = async function(token, listable) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/listable', { token }), {
		scope: listable,
	})
}

const setMentionPermissions = async function(token, mentionPermissions) {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/mention-permissions', { token }), {
		mentionPermissions,
	})
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
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/default', { token }), {
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
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/call', { token }), {
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
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/message-expiration', { token }), {
		seconds,
	})
}

export {
	fetchConversations,
	fetchConversation,
	fetchNoteToSelfConversation,
	getUpcomingEvents,
	searchListedConversations,
	createOneToOneConversation,
	createGroupConversation,
	createPrivateConversation,
	createPublicConversation,
	deleteConversation,
	addToFavorites,
	removeFromFavorites,
	archiveConversation,
	unarchiveConversation,
	setNotificationLevel,
	setNotificationCalls,
	makeConversationPublic,
	makeConversationPrivate,
	setSIPEnabled,
	setRecordingConsent,
	changeLobbyState,
	changeReadOnlyState,
	changeListable,
	setConversationPassword,
	setConversationName,
	setConversationDescription,
	setConversationPermissions,
	setCallPermissions,
	setMessageExpiration,
	setMentionPermissions,
}

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

import { hasTalkFeature } from './CapabilitiesManager.ts'
import { ATTENDEE, CONVERSATION } from '../constants.ts'
import type {
	getAllConversationsParams,
	getAllConversationsResponse,
	getSingleConversationResponse,
	getNoteToSelfConversationResponse,
	getListedConversationsParams,
	getListedConversationsResponse,
	createConversationResponse,
	legacyCreateConversationParams,
	deleteConversationResponse,
	setConversationNameParams,
	setConversationNameResponse,
	setConversationPasswordParams,
	setConversationPasswordResponse,
	setConversationDescriptionParams,
	setConversationDescriptionResponse,
	addConversationToFavoritesResponse,
	removeConversationFromFavoritesResponse,
	archiveConversationResponse,
	unarchiveConversationResponse,
	setConversationNotifyLevelParams,
	setConversationNotifyLevelResponse,
	setConversationNotifyCallsParams,
	setConversationNotifyCallsResponse,
	makeConversationPublicParams,
	makeConversationPublicResponse,
	makeConversationPrivateResponse,
	setConversationSipParams,
	setConversationSipResponse,
	setConversationLobbyParams,
	setConversationLobbyResponse,
	setConversationRecordingParams,
	setConversationRecordingResponse,
	setConversationReadonlyParams,
	setConversationReadonlyResponse,
	setConversationListableParams,
	setConversationListableResponse,
	setConversationMentionsPermissionsParams,
	setConversationMentionsPermissionsResponse,
	setConversationPermissionsParams,
	setConversationPermissionsResponse,
	setConversationMessageExpirationParams,
	setConversationMessageExpirationResponse,
} from '../types/index.ts'

/**
 * Fetches all conversations from the server.
 * @param params parameters
 * @param options options
 */
async function fetchConversations(params: getAllConversationsParams, options?: object): getAllConversationsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room'), {
		...options,
		params,
	})
}

/**
 * Fetches a conversation from the server.
 * @param token The token of the conversation to be fetched.
 */
async function fetchConversation(token: string): getSingleConversationResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }))
}

/**
 * Fetch listed conversations
 * @param searchTerm The string that will be used in the search query.
 * @param options options
 */
async function searchListedConversations(searchTerm: string, options?: object): getListedConversationsResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/listed-room'), {
		...options,
		params: {
			searchTerm,
		} as getListedConversationsParams,
	})
}

/**
 * Generate note-to-self conversation
 */
async function fetchNoteToSelfConversation(): getNoteToSelfConversationResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v4/room/note-to-self'))
}

/**
 * Create a new one to one conversation with the specified user.
 * @param userId The ID of the user with which the new conversation will be opened.
 */
async function createOneToOneConversation(userId: legacyCreateConversationParams['invite']): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.ONE_TO_ONE,
		invite: userId,
	} as legacyCreateConversationParams)
}

/**
 * Create a new group conversation.
 * @param invite The group/circle ID
 * @param source The source of the invite ID (defaults to groups)
 */
async function createGroupConversation(invite: legacyCreateConversationParams['invite'], source: legacyCreateConversationParams['source']): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.GROUP,
		invite,
		source: source || ATTENDEE.ACTOR_TYPE.GROUPS,
	} as legacyCreateConversationParams)
}

/**
 * Create a new private conversation.
 * @param conversationName The name for the new conversation
 * @param [objectType] The conversation object type
 */
async function createPrivateConversation(conversationName: legacyCreateConversationParams['roomName'], objectType: legacyCreateConversationParams['objectType']): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.GROUP,
		roomName: conversationName,
		objectType,
	} as legacyCreateConversationParams)
}

/**
 * Create a new private conversation.
 * @param conversationName The name for the new conversation
 * @param [password] The conversation password when creating a public conversation
 */
async function createPublicConversation(conversationName: legacyCreateConversationParams['roomName'], password: legacyCreateConversationParams['password']): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType: CONVERSATION.TYPE.PUBLIC,
		roomName: conversationName,
		password,
	} as legacyCreateConversationParams)
}

/**
 * Set a conversation's password
 * @param token the conversation's token
 * @param password the password to be set
 */
async function setConversationPassword(token: string, password: setConversationPasswordParams['password']): setConversationPasswordResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/password', { token }), {
		password,
	} as setConversationPasswordParams)
}

/**
 * Set a conversation's name
 * @param token the conversation's token
 * @param roomName the name to be set (max 255 characters)
 */
async function setConversationName(token: string, roomName: setConversationNameParams['roomName']): setConversationNameResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }), {
		roomName,
	} as setConversationNameParams)
}

/**
 * Set a conversation's description
 * @param token the conversation's token
 * @param description the description to be set (max 500 characters)
 */
async function setConversationDescription(token: string, description: setConversationDescriptionParams['description']): setConversationDescriptionResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/description', { token }), {
		description,
	} as setConversationDescriptionParams)
}

/**
 * Delete a conversation.
 * @param token The token of the conversation to be deleted.
 */
async function deleteConversation(token: string): deleteConversationResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}', { token }))
}

/**
 * Add a conversation to the favorites
 * @param token The token of the conversation to be favorites
 */
async function addToFavorites(token: string): addConversationToFavoritesResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
}

/**
 * Remove a conversation from the favorites
 * @param token The token of the conversation to be removed from favorites
 */
async function removeFromFavorites(token: string): removeConversationFromFavoritesResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/favorite', { token }))
}

/**
 * Add a conversation to the archive
 * @param token The token of the conversation to be archived
 */
async function archiveConversation(token: string): archiveConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/archive', { token }))
}

/**
 * Restore a conversation from the archive
 * @param token The token of the conversation to be removed from archive
 */
async function unarchiveConversation(token: string): unarchiveConversationResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/archive', { token }))
}

/**
 * Set notification level
 * @param token The token of the conversation to change the notification level
 * @param level The notification level to set.
 */
async function setNotificationLevel(token: string, level: setConversationNotifyLevelParams['level']): setConversationNotifyLevelResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify', { token }), { level } as setConversationNotifyLevelParams)
}

/**
 * Set call notifications
 * @param token The token of the conversation to change the call notification level
 * @param level The call notification level.
 */
async function setNotificationCalls(token: string, level: setConversationNotifyCallsParams['level']): setConversationNotifyCallsResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/notify-calls', { token }), { level } as setConversationNotifyCallsParams)
}

/**
 * Make the conversation public
 * @param token The token of the conversation to be removed from favorites
 * @param password The password to set for the conversation (optional, only if force password is enabled)
 */
async function makeConversationPublic(token: string, password: makeConversationPublicParams['password']): makeConversationPublicResponse {
	const data = (hasTalkFeature(token, 'conversation-creation-password') && password)
		? { password }
		: undefined
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }), data as makeConversationPublicParams)
}

/**
 * Make the conversation private
 * @param token The token of the conversation to be removed from favorites
 */
async function makeConversationPrivate(token: string): makeConversationPrivateResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/public', { token }))
}

/**
 * Change the SIP enabled
 * @param token The token of the conversation to be modified
 * @param state The new SIP state to set
 */
async function setSIPEnabled(token: string, state: setConversationSipParams['state']): setConversationSipResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/webinar/sip', { token }), {
		state,
	} as setConversationSipParams)
}

/**
 * Change the recording consent per conversation
 * @param token The token of the conversation to be modified
 * @param recordingConsent The new recording consent state to set
 */
async function setRecordingConsent(token: string, recordingConsent: setConversationRecordingParams['recordingConsent']): setConversationRecordingResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/recording-consent', { token }), {
		recordingConsent,
	} as setConversationRecordingParams)
}

/**
 * Change the lobby state
 * @param token The token of the conversation to be modified
 * @param state The new lobby state to set
 * @param timer The UNIX timestamp (in seconds) to set, if any
 */
async function changeLobbyState(token: string, state: setConversationLobbyParams['state'], timer: setConversationLobbyParams['timer']): setConversationLobbyResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/webinar/lobby', { token }), {
		state,
		timer,
	} as setConversationLobbyParams)
}

/**
 * Change the read-only state
 * @param token The token of the conversation to be modified
 * @param state The new read-only state to set
 */
async function changeReadOnlyState(token: string, state: setConversationReadonlyParams['state']): setConversationReadonlyResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/read-only', { token }), {
		state,
	} as setConversationReadonlyParams)
}

/**
 * Change the listable scope
 * @param token The token of the conversation to be modified
 * @param scope The new listable scope to set
 */
async function changeListable(token: string, scope: setConversationListableParams['scope']): setConversationListableResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/listable', { token }), {
		scope,
	} as setConversationListableParams)
}

/**
 * Set mention permissions to allow or disallow mentioning @all for non-moderators
 * @param token The token of the conversation to be modified
 * @param mentionPermissions The mention permissions to set
 */
async function setMentionPermissions(token: string, mentionPermissions: setConversationMentionsPermissionsParams['mentionPermissions']): setConversationMentionsPermissionsResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/mention-permissions', { token }), {
		mentionPermissions,
	} as setConversationMentionsPermissionsParams)
}

/**
 * Set the default permissions for participants in a conversation.
 * @param token conversation token
 * @param permissions the type of permission to be granted. Valid values are
 * any sums of 'DEFAULT', 'CUSTOM', 'CALL_START', 'CALL_JOIN', 'LOBBY_IGNORE',
 * 'PUBLISH_AUDIO', 'PUBLISH_VIDEO', 'PUBLISH_SCREEN'.
 */
async function setConversationPermissions(token: string, permissions: setConversationPermissionsParams['permissions']): setConversationPermissionsResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/default', { token }), {
		permissions,
	} as setConversationPermissionsParams)
}

/**
 * Set the default permissions for participants in a call. These will be reset
 * to default once the call has ended.
 * @param token conversation token
 * @param permissions the type of permission to be granted. Valid values are
 * any sums of 'DEFAULT', 'CUSTOM', 'CALL_START', 'CALL_JOIN', 'LOBBY_IGNORE',
 * 'PUBLISH_AUDIO', 'PUBLISH_VIDEO', 'PUBLISH_SCREEN'.
 */
async function setCallPermissions(token: string, permissions: setConversationPermissionsParams['permissions']): setConversationPermissionsResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/permissions/call', { token }), {
		permissions,
	} as setConversationPermissionsParams)
}

/**
 * Set the message expiration
 * @param token conversation token
 * @param seconds the seconds for the message expiration, 0 to disable
 */
async function setMessageExpiration(token: string, seconds: setConversationMessageExpirationParams['seconds']): setConversationMessageExpirationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/message-expiration', { token }), {
		seconds,
	} as setConversationMessageExpirationParams)
}

export {
	fetchConversations,
	fetchConversation,
	fetchNoteToSelfConversation,
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

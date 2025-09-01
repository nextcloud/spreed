/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'
import type {
	addConversationToFavoritesResponse,
	archiveConversationResponse,
	createConversationParams,
	createConversationResponse,
	deleteConversationResponse,
	getAllConversationsParams,
	getAllConversationsResponse,
	getListedConversationsParams,
	getListedConversationsResponse,
	getNoteToSelfConversationResponse,
	getSingleConversationResponse,
	legacyCreateConversationParams,
	makeConversationPrivateResponse,
	makeConversationPublicParams,
	makeConversationPublicResponse,
	markConversationAsImportantResponse,
	markConversationAsInsensitiveResponse,
	markConversationAsSensitiveResponse,
	markConversationAsUnimportantResponse,
	removeConversationFromFavoritesResponse,
	setConversationDescriptionParams,
	setConversationDescriptionResponse,
	setConversationListableParams,
	setConversationListableResponse,
	setConversationLobbyParams,
	setConversationLobbyResponse,
	setConversationMentionsPermissionsParams,
	setConversationMentionsPermissionsResponse,
	setConversationMessageExpirationParams,
	setConversationMessageExpirationResponse,
	setConversationNameParams,
	setConversationNameResponse,
	setConversationNotifyCallsParams,
	setConversationNotifyCallsResponse,
	setConversationNotifyLevelParams,
	setConversationNotifyLevelResponse,
	setConversationPasswordParams,
	setConversationPasswordResponse,
	setConversationPermissionsParams,
	setConversationPermissionsResponse,
	setConversationReadonlyParams,
	setConversationReadonlyResponse,
	setConversationRecordingParams,
	setConversationRecordingResponse,
	setConversationSipParams,
	setConversationSipResponse,
	unarchiveConversationResponse,
	unbindConversationFromObjectResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { hasTalkFeature } from './CapabilitiesManager.ts'

/**
 * Fetches all conversations from the server.
 * @param params parameters
 * @param [options] Axios request options
 */
async function fetchConversations(params: getAllConversationsParams, options?: AxiosRequestConfig): getAllConversationsResponse {
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
 * @param [options] Axios request options
 */
async function searchListedConversations(searchTerm: string, options?: AxiosRequestConfig): getListedConversationsResponse {
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
 * Create a new conversation (with params available in legacy API only).
 * @param params legacy API params
 * @param params.roomType Type of the room
 * @param params.roomName Name of the room
 * @param params.password The conversation password
 * @param params.objectType Type of the object
 * @param params.objectId ID of the object
 * @param params.invite User, group, … ID to invite
 * @param params.source Source of the invite ID
 */
async function createLegacyConversation({ roomType, roomName, password, objectType, objectId, invite, source }: legacyCreateConversationParams): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), {
		roomType,
		roomName,
		password,
		objectType,
		objectId,
		invite,
		source,
	} as legacyCreateConversationParams)
}

/**
 * Create a new conversation (with params available with 'conversation-creation-all' capability)
 * @param params API params
 */
async function createConversation(params: createConversationParams): createConversationResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room'), params)
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
 * Detach a conversation from an object and it becomes a "normal" conversation.
 * @param token The token of the conversation to be deleted.
 */
async function unbindConversationFromObject(token: string): unbindConversationFromObjectResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/object', { token }))
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
 * Mark a conversation as important
 * @param token The conversation token of the conversation to be favorites
 */
async function markAsImportant(token: string): markConversationAsImportantResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/important', { token }))
}

/**
 * Unmark an important conversation
 * @param token The token of the conversation to be removed from favorites
 */
async function markAsUnimportant(token: string): markConversationAsUnimportantResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/important', { token }))
}

/**
 * Mark a conversation as important
 * @param token The token of the conversation to be favorites
 */
async function markAsSensitive(token: string): markConversationAsSensitiveResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/sensitive', { token }))
}

/**
 * Remove a conversation from the favorites
 * @param token The token of the conversation to be removed from favorites
 */
async function markAsInsensitive(token: string): markConversationAsInsensitiveResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/sensitive', { token }))
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
	addToFavorites,
	archiveConversation,
	changeListable,
	changeLobbyState,
	changeReadOnlyState,
	createConversation,
	createLegacyConversation,
	deleteConversation,
	fetchConversation,
	fetchConversations,
	fetchNoteToSelfConversation,
	makeConversationPrivate,
	makeConversationPublic,
	markAsImportant,
	markAsInsensitive,
	markAsSensitive,
	markAsUnimportant,
	removeFromFavorites,
	searchListedConversations,
	setCallPermissions,
	setConversationDescription,
	setConversationName,
	setConversationPassword,
	setConversationPermissions,
	setMentionPermissions,
	setMessageExpiration,
	setNotificationCalls,
	setNotificationLevel,
	setRecordingConsent,
	setSIPEnabled,
	unarchiveConversation,
	unbindConversationFromObject,
}

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
/* import {
	showWarning
} from '@nextcloud/dialogs' */
import {
	generateOcsUrl,
} from '@nextcloud/router'

import { PARTICIPANT } from '../constants.js'
import {
	signalingJoinConversation,
	signalingLeaveConversation,
	signalingSetTyping,
} from '../utils/webrtc/index.js'

const PERMISSIONS = PARTICIPANT.PERMISSIONS

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {object} data the wrapping object;
 * @param {string} data.token The conversation token;
 * @param {boolean} data.forceJoin whether to force join;
 * @param {options} options request options;
 */
const joinConversation = async ({ token, forceJoin = false }, options) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }), {
		force: forceJoin,
	}, options)

	if (response.headers.get('X-Nextcloud-Bruteforce-Throttled')) {
		console.error(
			'Remote address is bruteforce throttled: '
			+ response.headers.get('X-Nextcloud-Bruteforce-Throttled')
			+ ' (Request ID: ' + response.headers.get('X-Request-ID') + ')'
		)
		const throttleMs = parseInt(response.headers.get('X-Nextcloud-Bruteforce-Throttled'), 10)
		if (throttleMs > 5000) {
			window.OCP.Toast.warning(
				t('spreed', 'Your requests are throttled at the moment due to brute force protection')
			)
		}
	}

	// FIXME Signaling should not be synchronous
	await signalingJoinConversation(token, response.data.ocs.data.sessionId)

	return response
}

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 */
const rejoinConversation = async (token) => {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }))
}

/**
 * Leaves the conversation specified with the token.
 *
 * @param {string} token The conversation token;
 */
const leaveConversation = async function(token) {
	try {
		// FIXME Signaling should not be synchronous
		await signalingLeaveConversation(token)

		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }))
		return response
	} catch (error) {
		console.debug(error)
		// FIXME: should throw
	}
}

/**
 * Leaves the conversation specified with the token.
 *
 * @param {string} token The conversation token;
 */
const leaveConversationSync = function(token) {
	axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }))
}

/**
 * Add a participant to a conversation.
 *
 * @param {token} token the conversation token.
 * @param {string} newParticipant the id of the new participant
 * @param {string} source the source Source of the participant as returned by the autocomplete suggestion endpoint (default is users)
 */
const addParticipant = async function(token, newParticipant, source) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants', { token }), {
		newParticipant,
		source,
	})
	return response
}

/**
 * Removes the the current user from the conversation specified with the token.
 *
 * @param {string} token The conversation token;
 */
const removeCurrentUserFromConversation = async function(token) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/self', { token }))
	return response
}

const removeAttendeeFromConversation = async function(token, attendeeId) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees', { token }), {
		params: {
			attendeeId,
		},
	})
	return response
}

const promoteToModerator = async (token, options) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/moderators', { token }), options)
	return response
}

const demoteFromModerator = async (token, options) => {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/moderators', { token }), {
		params: options,
	})
	return response
}

const fetchParticipants = async (token, options) => {
	options = options || {}
	options.params = options.params || {}
	options.params.includeStatus = true
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants', { token }), options)
	return response
}

const setGuestUserName = async (token, userName) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v1/guest/{token}/name', { token }), {
		displayName: userName,
	})
	return response
}

/**
 * Resends email invitations for the given conversation.
 * If no userId is set, send to all applicable participants.
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target, or null for all
 */
const resendInvitations = async (token, { attendeeId = null }) => {
	await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/resend-invitations', { token }), {
		attendeeId,
	})
}
/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 * @param {number} state Session state;
 */
const setSessionState = async (token, state) => {
	return axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/state', { token }),
		{ state }
	)
}

/**
 * Sends call notification for the given attendee in the conversation.
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 */
const sendCallNotification = async (token, { attendeeId }) => {
	await axios.post(generateOcsUrl('apps/spreed/api/v4/call/{token}/ring/{attendeeId}', { token, attendeeId }))
}

/**
 * Grants all permissions to an attendee in a given conversation
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 */
const grantAllPermissionsToParticipant = async (token, attendeeId) => {
	await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees/permissions', { token }), {
		attendeeId,
		method: 'set',
		permissions: PERMISSIONS.MAX_CUSTOM,
	})
}

/**
 * Removes all permissions to an attendee in a given conversation
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 */
const removeAllPermissionsFromParticipant = async (token, attendeeId) => {
	await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees/permissions', { token }), {
		attendeeId,
		method: 'set',
		permissions: PERMISSIONS.CUSTOM,
	})
}

/**
 * Set permission for an attendee in a given conversation.
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 * @param {'set'|'add'|'remove'} method permissions update method
 * @param {number} permission the type of permission to be granted. Valid values are
 * any sums of 'DEFAULT', 'CUSTOM', 'CALL_START', 'CALL_JOIN', 'LOBBY_IGNORE',
 * 'PUBLISH_AUDIO', 'PUBLISH_VIDEO', 'PUBLISH_SCREEN'.
 */
const setPermissions = async (token, attendeeId, method = 'set', permission) => {
	await axios.put(generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees/permissions', { token }),
		{
			attendeeId,
			method,
			permissions: permission,
		})
}

/**
 * Sets whether the current participant is typing or not.
 *
 * @param {boolean} typing whether the current participant is typing.
 */
const setTyping = (typing) => {
	signalingSetTyping(typing)
}

/**
 * Get absence information for a user (in a given 1-1 conversation).
 *
 * @param {string} userId user id
 */
const getUserAbsence = async (userId) => {
	return axios.get(generateOcsUrl('/apps/dav/api/v1/outOfOffice/{userId}/now', { userId }))
}

export {
	joinConversation,
	rejoinConversation,
	leaveConversation,
	leaveConversationSync,
	addParticipant,
	removeCurrentUserFromConversation,
	removeAttendeeFromConversation,
	promoteToModerator,
	demoteFromModerator,
	fetchParticipants,
	setGuestUserName,
	resendInvitations,
	sendCallNotification,
	grantAllPermissionsToParticipant,
	removeAllPermissionsFromParticipant,
	setPermissions,
	setSessionState,
	setTyping,
	getUserAbsence,
}

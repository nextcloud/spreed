/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import {
	showWarning,
} from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import {
	generateOcsUrl,
} from '@nextcloud/router'
import { PARTICIPANT } from '../constants.ts'
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
async function joinConversation({ token, forceJoin = false }, options) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }), {
		force: forceJoin,
	}, options)

	if (response.headers.get('X-Nextcloud-Bruteforce-Throttled')) {
		console.error('Remote address is bruteforce throttled: '
			+ response.headers.get('X-Nextcloud-Bruteforce-Throttled')
			+ ' (Request ID: ' + response.headers.get('X-Request-ID') + ')')
		const throttleMs = parseInt(response.headers.get('X-Nextcloud-Bruteforce-Throttled'), 10)
		if (throttleMs > 5000) {
			showWarning(t('spreed', 'Your requests are throttled at the moment due to brute force protection'))
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
async function rejoinConversation(token) {
	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }))
}

/**
 * Leaves the conversation specified with the token.
 *
 * @param {string} token The conversation token;
 */
async function leaveConversation(token) {
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
function leaveConversationSync(token) {
	axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }))
}

/**
 * Add a participant to a conversation.
 *
 * @param {token} token the conversation token.
 * @param {string} newParticipant the id of the new participant
 * @param {string} source the source Source of the participant as returned by the autocomplete suggestion endpoint (default is users)
 */
async function addParticipant(token, newParticipant, source) {
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
async function removeCurrentUserFromConversation(token) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/self', { token }))
	return response
}

/**
 *
 * @param token
 * @param attendeeId
 */
async function removeAttendeeFromConversation(token, attendeeId) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees', { token }), {
		params: {
			attendeeId,
		},
	})
	return response
}

/**
 *
 * @param token
 * @param options
 */
async function promoteToModerator(token, options) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/moderators', { token }), options)
	return response
}

/**
 *
 * @param token
 * @param options
 */
async function demoteFromModerator(token, options) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4/room/{token}/moderators', { token }), {
		params: options,
	})
	return response
}

/**
 *
 * @param token
 * @param options
 */
async function fetchParticipants(token, options) {
	options = options || {}
	options.params = options.params || {}
	options.params.includeStatus = true
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants', { token }), options)
	return response
}

/**
 *
 * @param token
 * @param userName
 */
async function setGuestUserName(token, userName) {
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
 * @param {number|null} [attendeeId] attendee id to target, or null for all
 */
async function resendInvitations(token, attendeeId = null) {
	await axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/resend-invitations', { token }), {
		attendeeId,
	})
}

/**
 *
 * @param {string} token conversation token
 * @param {File} file file to upload
 * @param {boolean} testRun whether to perform a verification only
 * @return {import('../types/index.ts').importEmailsResponse}
 */
async function importEmails(token, file, testRun = false) {
	let data = {
		file,
	}

	if (testRun) {
		data = {
			file,
			testRun,
		}
	}

	return axios.post(generateOcsUrl('apps/spreed/api/v4/room/{token}/import-emails', { token }), data, {
		headers: {
			'Content-Type': 'multipart/form-data',
		},
	})
}

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 * @param {number} state Session state;
 */
async function setSessionState(token, state) {
	return axios.put(
		generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/state', { token }),
		{ state },
	)
}

/**
 * Sends call notification for the given attendee in the conversation.
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 */
async function sendCallNotification(token, { attendeeId }) {
	await axios.post(generateOcsUrl('apps/spreed/api/v4/call/{token}/ring/{attendeeId}', { token, attendeeId }))
}

/**
 * Grants all permissions to an attendee in a given conversation
 *
 * @param {string} token conversation token
 * @param {number} attendeeId attendee id to target
 */
async function grantAllPermissionsToParticipant(token, attendeeId) {
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
async function removeAllPermissionsFromParticipant(token, attendeeId) {
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
async function setPermissions(token, attendeeId, method = 'set', permission) {
	await axios.put(
		generateOcsUrl('apps/spreed/api/v4/room/{token}/attendees/permissions', { token }),
		{
			attendeeId,
			method,
			permissions: permission,
		},
	)
}

/**
 * Sets whether the current participant is typing or not.
 *
 * @param {boolean} typing whether the current participant is typing.
 */
function setTyping(typing) {
	signalingSetTyping(typing)
}

export {
	addParticipant,
	demoteFromModerator,
	fetchParticipants,
	grantAllPermissionsToParticipant,
	importEmails,
	joinConversation,
	leaveConversation,
	leaveConversationSync,
	promoteToModerator,
	rejoinConversation,
	removeAllPermissionsFromParticipant,
	removeAttendeeFromConversation,
	removeCurrentUserFromConversation,
	resendInvitations,
	sendCallNotification,
	setGuestUserName,
	setPermissions,
	setSessionState,
	setTyping,
}

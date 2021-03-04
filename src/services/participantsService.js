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
import {
	generateUrl,
	generateOcsUrl,
} from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import {
	signalingJoinConversation,
	signalingLeaveConversation,
} from '../utils/webrtc/index'
import { EventBus } from './EventBus'
import SessionStorage from './SessionStorage'
import { PARTICIPANT } from '../constants'
import store from '../store/index'

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 */
const joinConversation = async(token) => {
	// When the token is in the last joined conversation, the user is reloading or force joining
	const forceJoin = SessionStorage.getItem('joined_conversation') === token

	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/active`, {
			force: forceJoin,
		})

		// Update the participant and actor session after a force join
		store.dispatch('setCurrentParticipant', response.data.ocs.data)
		store.dispatch('addConversation', response.data.ocs.data)
		store.dispatch('updateSessionId', {
			token: token,
			participantIdentifier: store.getters.getParticipantIdentifier(),
			sessionId: response.data.ocs.data.sessionId,
		})

		// FIXME Signaling should not be synchronous
		await signalingJoinConversation(token, response.data.ocs.data.sessionId)
		SessionStorage.setItem('joined_conversation', token)
		EventBus.$emit('joinedConversation', { token })
		return response
	} catch (error) {
		if (error.response.status === 409) {
			const responseData = error.response.data.ocs.data
			let maxLastPingAge = new Date().getTime() / 1000 - 40
			if (responseData.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				// When the user is/was in a call, we accept 20 seconds more delay
				maxLastPingAge -= 20
			}
			if (maxLastPingAge > responseData.lastPing) {
				console.debug('Force joining automatically because the old session didn\'t ping for 40 seconds')
				await forceJoinConversation(token)
			} else {
				await confirmForceJoinConversation(token)
			}
		} else {
			console.debug(error)
			showError(t('spreed', 'Failed to join the conversation. Try to reload the page.'))
		}
	}
}

const confirmForceJoinConversation = async(token) => {
	// Little hack to check if the close button was used which we can't disable,
	// not listen to when it was used.
	const interval = setInterval(function() {
		// eslint-disable-next-line no-undef
		if ($('.oc-dialog-dim').length === 0) {
			clearInterval(interval)
			EventBus.$emit('duplicateSessionDetected')
			window.location = generateUrl('/apps/spreed')
		}
	}, 3000)

	await OC.dialogs.confirmDestructive(
		t('spreed', 'You are trying to join a conversation while having an active session in another window or device. This is currently not supported by Nextcloud Talk. What do you want to do?'),
		t('spreed', 'Duplicate session'),
		{
			type: OC.dialogs.YES_NO_BUTTONS,
			confirm: t('spreed', 'Join here'),
			confirmClasses: 'error',
			cancel: t('spreed', 'Leave this page'),
		},
		decision => {
			clearInterval(interval)
			if (!decision) {
				// Cancel
				EventBus.$emit('duplicateSessionDetected')
				window.location = generateUrl('/apps/spreed')
			} else {
				// Confirm
				forceJoinConversation(token)
			}
		}
	)
}

const forceJoinConversation = async(token) => {
	SessionStorage.setItem('joined_conversation', token)
	await joinConversation(token)
}

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 */
const rejoinConversation = async(token) => {
	return axios.post(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/active`)
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

		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/active`)
		return response
	} catch (error) {
		console.debug(error)
	}
}

/**
 * Leaves the conversation specified with the token.
 *
 * @param {string} token The conversation token;
 */
const leaveConversationSync = function(token) {
	axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/active`)
}

/**
 * Add a participant to a conversation.
 * @param {token} token the conversation token.
 * @param {string} newParticipant the id of the new participant
 * @param {string} source the source Source of the participant as returned by the autocomplete suggestion endpoint (default is users)
 */
const addParticipant = async function(token, newParticipant, source) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants`, {
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
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/self`)
	return response
}

const removeUserFromConversation = async function(token, userId) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants`, {
			params: {
				participant: userId,
			},
		})
		return response
	} catch (error) {
		console.debug(error)
	}
}

const removeGuestFromConversation = async function(token, sessionId) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/guests`, {
			params: {
				participant: sessionId,
			},
		})
		return response
	} catch (error) {
		console.debug(error)
	}
}

const removeAttendeeFromConversation = async function(token, attendeeId) {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/attendees`, {
		params: {
			attendeeId,
		},
	})
	return response
}

const promoteToModerator = async(token, options) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/moderators`, options)
	return response
}

const demoteFromModerator = async(token, options) => {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/moderators`, {
		params: options,
	})
	return response
}

const fetchParticipants = async(token, options) => {
	options = options || {}
	options.params = options.params || {}
	options.params.includeStatus = true
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants`, options)
	return response
}

const setGuestUserName = async(token, userName) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v1', 2) + `/guest/${token}/name`, {
		displayName: userName,
	})
	return response
}

/**
 * Resends email invitations for the given conversation.
 * If no userId is set, send to all applicable participants.
 *
 * @param {string} token conversation token
 * @param {int} attendeeId attendee id to target, or null for all
 */
const resendInvitations = async(token, { attendeeId = null }) => {
	await axios.post(generateOcsUrl('apps/spreed/api/v4', 2) + `room/${token}/participants/resend-invitations`, {
		attendeeId,
	})
}

export {
	joinConversation,
	rejoinConversation,
	leaveConversation,
	leaveConversationSync,
	addParticipant,
	removeCurrentUserFromConversation,
	removeUserFromConversation,
	removeGuestFromConversation,
	removeAttendeeFromConversation,
	promoteToModerator,
	demoteFromModerator,
	fetchParticipants,
	setGuestUserName,
	resendInvitations,
}

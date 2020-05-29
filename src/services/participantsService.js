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
import {
	signalingJoinConversation,
	signalingLeaveConversation,
} from '../utils/webrtc/index'
import { EventBus } from './EventBus'
import SessionStorage from './SessionStorage'

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 */
const joinConversation = async(token) => {
	const isReloading = SessionStorage.getItem('joined_conversation') === token

	try {
		const response = await axios.post(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants/active`, {
			force: isReloading,
		})

		// FIXME Signaling should not be synchronous
		await signalingJoinConversation(token, response.data.ocs.data.sessionId)
		SessionStorage.setItem('joined_conversation', token)
		EventBus.$emit('joinedConversation')
		return response
	} catch (error) {
		console.debug(error)
	}
}

/**
 * Joins the current user to a conversation specified with
 * the token.
 *
 * @param {string} token The conversation token;
 */
const rejoinConversation = async(token) => {
	return axios.post(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants/active`)
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

		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants/active`)
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
	axios.delete(generateOcsUrl('apps/spreed/api/v2/room', 2) + token + '/participants/active')
}

/**
 * Add a participant to a conversation.
 * @param {token} token the conversation token.
 * @param {string} newParticipant the id of the new participant
 * @param {string} source the source Source of the participant as returned by the autocomplete suggestion endpoint (default is users)
 */
const addParticipant = async function(token, newParticipant, source) {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants`, {
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
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants/self`)
	return response
}

const removeUserFromConversation = async function(token, userId) {
	try {
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants`, {
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
		const response = await axios.delete(generateOcsUrl('apps/spreed/api/v2', 2) + `room/${token}/participants/guests`, {
			params: {
				participant: sessionId,
			},
		})
		return response
	} catch (error) {
		console.debug(error)
	}
}

const promoteToModerator = async(token, options) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v2/room', 2) + token + '/moderators', options)
	return response
}

const demoteFromModerator = async(token, options) => {
	const response = await axios.delete(generateOcsUrl('apps/spreed/api/v2/room', 2) + token + '/moderators', {
		params: options,
	})
	return response
}

const fetchParticipants = async(token, options) => {
	const response = await axios.get(generateOcsUrl('apps/spreed/api/v2/room', 2) + token + '/participants', options)
	return response
}

const setGuestUserName = async(token, userName) => {
	const response = await axios.post(generateOcsUrl('apps/spreed/api/v1/guest', 2) + token + '/name', {
		displayName: userName,
	})
	return response
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
	promoteToModerator,
	demoteFromModerator,
	fetchParticipants,
	setGuestUserName,
}

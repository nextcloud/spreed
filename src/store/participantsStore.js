/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
import Vue from 'vue'
import {
	promoteToModerator,
	demoteFromModerator,
	removeAttendeeFromConversation,
	resendInvitations,
	joinConversation,
	leaveConversation,
	removeCurrentUserFromConversation,
	grantAllPermissionsToParticipant,
	removeAllPermissionsFromParticipant,
	setPermissions,
} from '../services/participantsService'
import { generateUrl } from '@nextcloud/router'
import {
	joinCall,
	leaveCall,
} from '../services/callsService'
import SessionStorage from '../services/SessionStorage'
import { PARTICIPANT } from '../constants'
import { EventBus } from '../services/EventBus'
import { showError } from '@nextcloud/dialogs'

const state = {
	attendees: {
	},
	peers: {
	},
	inCall: {
	},
	connecting: {
	},
}

const getters = {
	isInCall: (state) => (token) => {
		return !!(state.inCall[token] && Object.keys(state.inCall[token]).length > 0)
	},

	isConnecting: (state) => (token) => {
		return !!(state.connecting[token] && Object.keys(state.connecting[token]).length > 0)
	},
	/**
	 * Gets the participants array.
	 *
	 * @param {object} state - the state object.
	 * @return {Array} the participants array (if there are participants in the
	 * store).
	 */
	participantsList: (state) => (token) => {
		if (state.attendees[token]) {
			return Object.values(state.attendees[token])
		}
		return []
	},

	/**
	 * Replaces the legacy getParticipant getter. Returns a callback function in which you can
	 * pass in the token and attendeeId as arguments to get the participant object.
	 *
	 * @param {*} state - the state object.
	 * param {string} token - the conversation token.
	 * param {number} attendeeId - Unique identifier for a participant in a conversation.
	 * @return {object} - The participant object.
	 */
	getParticipant: (state) => (token, attendeeId) => {
		if (state.attendees[token] && state.attendees[token][attendeeId]) {
			return state.attendees[token][attendeeId]
		}
		return null
	},

	/**
	 * Replaces the legacy getParticipant getter. Returns a callback function in which you can
	 * pass in the token and attendeeId as arguments to get the participant object.
	 *
	 * @param {*} state - the state object.
	 * param {string} token - the conversation token.
	 * param {number} attendeeId - Unique identifier for a participant in a conversation.
	 * @return {object|null} - The participant object.
	 */
	findParticipant: (state) => (token, participantIdentifier) => {
		if (!state.attendees[token]) {
			return null
		}

		if (participantIdentifier.attendeeId) {
			if (state.attendees[token][participantIdentifier.attendeeId]) {
				return state.attendees[token][participantIdentifier.attendeeId]
			}
			return null
		}

		let foundAttendee = null
		Object.keys(state.attendees[token]).forEach((attendeeId) => {
			if (participantIdentifier.actorType && participantIdentifier.actorId
				&& state.attendees[token][attendeeId].actorType === participantIdentifier.actorType
				&& state.attendees[token][attendeeId].actorId === participantIdentifier.actorId) {
				foundAttendee = attendeeId
			}
			if (participantIdentifier.sessionId && state.attendees[token][attendeeId].sessionId === participantIdentifier.sessionId) {
				foundAttendee = attendeeId
			}
		})

		if (!foundAttendee) {
			return null
		}

		return state.attendees[token][foundAttendee]
	},
	getPeer: (state) => (token, sessionId, userId) => {
		if (state.peers[token]) {
			if (Object.prototype.hasOwnProperty.call(state.peers[token], sessionId)) {
				return state.peers[token][sessionId]
			}
		}

		// Fallback to the participant list, if we have a user id that should be easy
		if (state.attendees[token] && userId) {
			let foundAttendee = null
			Object.keys(state.attendees[token]).forEach((attendeeId) => {
				if (state.attendees[token][attendeeId].actorType === 'users'
					&& state.attendees[token][attendeeId].actorId === userId) {
					foundAttendee = attendeeId
				}
			})

			if (foundAttendee) {
				return state.attendees[token][foundAttendee]
			}
		}

		return {}
	},
}

const mutations = {
	/**
	 * Add a message to the store.
	 *
	 * @param {object} state - current store state.
	 * @param {object} data - the wrapping object.
	 * @param {object} data.token - the token of the conversation.
	 * @param {object} data.participant - the participant.
	 */
	addParticipant(state, { token, participant }) {
		if (!state.attendees[token]) {
			Vue.set(state.attendees, token, {})
		}
		Vue.set(state.attendees[token], participant.attendeeId, participant)
	},

	updateParticipant(state, { token, attendeeId, updatedData }) {
		if (state.attendees[token] && state.attendees[token][attendeeId]) {
			state.attendees[token][attendeeId] = Object.assign(state.attendees[token][attendeeId], updatedData)
		} else {
			console.error('Error while updating the participant')
		}
	},

	deleteParticipant(state, { token, attendeeId }) {
		if (state.attendees[token] && state.attendees[token][attendeeId]) {
			Vue.delete(state.attendees[token], attendeeId)
		} else {
			console.error('The conversation you are trying to purge doesn\'t exist')
		}
	},

	setInCall(state, { token, sessionId, flags }) {
		if (flags === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
			if (state.inCall[token] && state.inCall[token][sessionId]) {
				Vue.delete(state.inCall[token], sessionId)
			}

			if (state.connecting[token] && state.connecting[token][sessionId]) {
				Vue.delete(state.connecting[token], sessionId)
			}
		} else {
			if (!state.inCall[token]) {
				Vue.set(state.inCall, token, {})
			}
			Vue.set(state.inCall[token], sessionId, flags)

			if (!state.connecting[token]) {
				Vue.set(state.connecting, token, {})
			}
			Vue.set(state.connecting[token], sessionId, flags)
		}
	},

	finishedConnecting(state, { token, sessionId }) {
		if (state.connecting[token] && state.connecting[token][sessionId]) {
			Vue.delete(state.connecting[token], sessionId)
		}
	},

	/**
	 * Purge a given conversation from the previously added participants.
	 *
	 * @param {object} state - current store state.
	 * @param {string} token - the conversation to purge.
	 */
	purgeParticipantsStore(state, token) {
		if (state.attendees[token]) {
			Vue.delete(state.attendees, token)
		}
	},

	addPeer(state, { token, peer }) {
		if (!state.peers[token]) {
			Vue.set(state.peers, token, [])
		}
		Vue.set(state.peers[token], peer.sessionId, peer)
	},

	purgePeersStore(state, token) {
		if (state.peers[token]) {
			Vue.delete(state.peers, token)
		}
	},
}

const actions = {
	/**
	 * Add participant to the store.
	 *
	 * Only call this after purgeParticipantsStore, otherwise use addParticipantOnce.
	 *
	 * @param {object} context - default store context.
	 * @param {Function} context.commit - the contexts commit function.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - the conversation to add the participant.
	 * @param {object} data.participant - the participant.
	 */
	addParticipant({ commit }, { token, participant }) {
		commit('addParticipant', { token, participant })
	},

	/**
	 * Only add a participant when they are not there yet
	 *
	 * @param {object} context - default store context.
	 * @param {Function} context.commit - the contexts commit function.
	 * @param {object} context.getters - the contexts getters object.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - the conversation to add the participant.
	 * @param {object} data.participant - the participant.
	 */
	addParticipantOnce({ commit, getters }, { token, participant }) {
		const attendee = getters.findParticipant(token, participant)
		if (!attendee) {
			commit('addParticipant', { token, participant })
		}
	},

	async promoteToModerator({ commit, getters }, { token, attendeeId }) {
		const attendee = getters.getParticipant(token, attendeeId)
		if (!attendee) {
			return
		}

		await promoteToModerator(token, {
			attendeeId,
		})

		// FIXME: don't promote already promoted or read resulting type from server response
		const updatedData = {
			participantType: attendee.participantType === PARTICIPANT.TYPE.GUEST ? PARTICIPANT.TYPE.GUEST_MODERATOR : PARTICIPANT.TYPE.MODERATOR,
		}
		commit('updateParticipant', { token, attendeeId, updatedData })
	},

	async demoteFromModerator({ commit, getters }, { token, attendeeId }) {
		const attendee = getters.getParticipant(token, attendeeId)
		if (!attendee) {
			return
		}

		await demoteFromModerator(token, {
			attendeeId,
		})

		// FIXME: don't demote already demoted, use server response instead
		const updatedData = {
			participantType: attendee.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR ? PARTICIPANT.TYPE.GUEST : PARTICIPANT.TYPE.USER,
		}
		commit('updateParticipant', { token, attendeeId, updatedData })
	},

	async removeParticipant({ commit, getters }, { token, attendeeId }) {
		const attendee = getters.getParticipant(token, attendeeId)
		if (!attendee) {
			return
		}

		await removeAttendeeFromConversation(token, attendeeId)
		commit('deleteParticipant', { token, attendeeId })
	},

	/**
	 * Purges a given conversation from the previously added participants
	 *
	 * @param {object} context default store context;
	 * @param {Function} context.commit the contexts commit function.
	 * @param {string} token the conversation to purge;
	 */
	purgeParticipantsStore({ commit }, token) {
		commit('purgeParticipantsStore', token)
	},

	addPeer({ commit }, { token, peer }) {
		commit('addPeer', { token, peer })
	},

	purgePeersStore({ commit }, token) {
		commit('purgePeersStore', token)
	},

	updateSessionId({ commit, getters }, { token, participantIdentifier, sessionId }) {
		const attendee = getters.findParticipant(token, participantIdentifier)
		if (!attendee) {
			console.error('Participant not found for conversation', token, participantIdentifier)
			return
		}

		const updatedData = {
			sessionId,
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })
	},

	updateUser({ commit, getters }, { token, participantIdentifier, updatedData }) {
		const attendee = getters.findParticipant(token, participantIdentifier)
		if (!attendee) {
			console.error('Participant not found for conversation', token, participantIdentifier)
			return
		}

		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })
	},

	async joinCall({ commit, getters }, { token, participantIdentifier, flags }) {
		if (!participantIdentifier?.sessionId) {
			console.error('Trying to join call without sessionId')
			return
		}

		const attendee = getters.findParticipant(token, participantIdentifier)
		if (!attendee) {
			console.error('Participant not found for conversation', token, participantIdentifier)
			return
		}

		commit('setInCall', {
			token,
			sessionId: participantIdentifier.sessionId,
			flags,
		})

		const actualFlags = await joinCall(token, flags)

		const updatedData = {
			inCall: actualFlags,
		}
		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })

		EventBus.$once('signaling-users-in-room', () => {
			commit('finishedConnecting', { token, sessionId: participantIdentifier.sessionId })
		})

		setTimeout(() => {
			// If by accident we never receive a users list, just switch to
			// "Waiting for others to join the call …" after some seconds.
			commit('finishedConnecting', { token, sessionId: participantIdentifier.sessionId })
		}, 10000)
	},

	async leaveCall({ commit, getters }, { token, participantIdentifier, all = false }) {
		if (!participantIdentifier?.sessionId) {
			console.error('Trying to leave call without sessionId')
		}

		const attendee = getters.findParticipant(token, participantIdentifier)
		if (!attendee) {
			console.error('Participant not found for conversation', token, participantIdentifier)
			return
		}

		await leaveCall(token, all)

		const updatedData = {
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })

		commit('setInCall', {
			token,
			sessionId: participantIdentifier.sessionId,
			flags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		})
	},

	/**
	 * Resends email invitations for the given conversation.
	 * If no userId is set, send to all applicable participants.
	 *
	 * @param {object} _ - unused.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - conversation token.
	 * @param {number} data.attendeeId - attendee id to target, or null for all.
	 */
	async resendInvitations(_, { token, attendeeId }) {
		await resendInvitations(token, { attendeeId })
	},

	/**
	 * Makes the current user active in the given conversation.
	 *
	 * @param {object} context - unused.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - conversation token.
	 */
	async joinConversation(context, { token }) {
		const forceJoin = SessionStorage.getItem('joined_conversation') === token

		try {
			const response = await joinConversation({ token, forceJoin })

			// Update the participant and actor session after a force join
			context.dispatch('setCurrentParticipant', response.data.ocs.data)
			context.dispatch('addConversation', response.data.ocs.data)
			context.dispatch('updateSessionId', {
				token,
				participantIdentifier: context.getters.getParticipantIdentifier(),
				sessionId: response.data.ocs.data.sessionId,
			})

			SessionStorage.setItem('joined_conversation', token)
			EventBus.$emit('joined-conversation', { token })
			return response
		} catch (error) {
			if (error?.response?.status === 409 && error?.response?.data?.ocs?.data) {
				const responseData = error.response.data.ocs.data
				let maxLastPingAge = new Date().getTime() / 1000 - 40
				if (responseData.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
					// When the user is/was in a call, we accept 20 seconds more delay
					maxLastPingAge -= 20
				}
				if (maxLastPingAge > responseData.lastPing) {
					console.debug('Force joining automatically because the old session didn\'t ping for 40 seconds')
					await context.dispatch('forceJoinConversation', { token })
				} else {
					await context.dispatch('confirmForceJoinConversation', { token })
				}
			} else {
				console.debug(error)
				showError(t('spreed', 'Failed to join the conversation. Try to reload the page.'))
			}
		}
	},

	async confirmForceJoinConversation(context, { token }) {
		// FIXME: UI stuff doesn't belong here, should rather
		// be triggered using a store flag and a dedicated Vue component

		// Little hack to check if the close button was used which we can't disable,
		// not listen to when it was used.
		const interval = setInterval(function() {
			// eslint-disable-next-line no-undef
			if (document.getElementsByClassName('oc-dialog-dim').length === 0) {
				clearInterval(interval)
				EventBus.$emit('duplicate-session-detected')
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
					EventBus.$emit('duplicate-session-detected')
					window.location = generateUrl('/apps/spreed')
				} else {
					// Confirm
					context.dispatch('forceJoinConversation', { token })
				}
			}
		)
	},

	async forceJoinConversation(context, { token }) {
		SessionStorage.setItem('joined_conversation', token)
		await context.dispatch('joinConversation', { token })
	},

	/**
	 * Makes the current user inactive in the given conversation.
	 *
	 * @param {object} context - unused.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - conversation token.
	 */
	async leaveConversation(context, { token }) {
		if (context.getters.isInCall(token)) {
			await context.dispatch('leaveCall', {
				token,
				participantIdentifier: context.getters.getParticipantIdentifier(),
			})
		}

		await leaveConversation(token)
	},

	/**
	 * Removes the current user from the conversation, which means the user is
	 * not a participant any more.
	 *
	 * @param {object} context - The context object.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - conversation token.
	 */
	async removeCurrentUserFromConversation(context, { token }) {
		await removeCurrentUserFromConversation(token)
		// If successful, deletes the conversation from the store
		await context.dispatch('deleteConversation', token)
	},

	/**
	 * PUBLISHING PERMISSIONS
	 */

	/**
	 * Grant all permissions for a given participant.
	 *
	 * @param {object} context - the context object.
	 * @param {object} root0 - the arguments object.
	 * @param {string} root0.token - the conversation token.
	 * @param {string} root0.attendeeId - the participant-s attendeeId.
	 */
	async grantAllPermissionsToParticipant(context, { token, attendeeId }) {
		await grantAllPermissionsToParticipant(token, attendeeId)
		const updatedData = {
			permissions: PARTICIPANT.PERMISSIONS.MAX_CUSTOM,
			attendeePermissions: PARTICIPANT.PERMISSIONS.MAX_CUSTOM,
		}
		context.commit('updateParticipant', { token, attendeeId, updatedData })
	},

	/**
	 * Remove all permissions for a given participant.
	 *
	 * @param {object} context - the context object.
	 * @param {object} root0 - the arguments object.
	 * @param {string} root0.token - the conversation token.
	 * @param {string} root0.attendeeId - the participant-s attendeeId.
	 */
	async removeAllPermissionsFromParticipant(context, { token, attendeeId }) {
		await removeAllPermissionsFromParticipant(token, attendeeId)
		const updatedData = {
			permissions: PARTICIPANT.PERMISSIONS.CUSTOM,
			attendeePermissions: PARTICIPANT.PERMISSIONS.CUSTOM,
		}
		context.commit('updateParticipant', { token, attendeeId, updatedData })
	},

	/**
	 * Add a specific permission or permission combination to a given
	 * participant.
	 *
	 * @param {object} context - the context object.
	 * @param {object} root0 - the arguments object.
	 * @param {string} root0.token - the conversation token.
	 * @param {string} root0.attendeeId - the participant-s attendeeId.
	 * @param {number} root0.permissions - bitwise combination of the permissions.
	 */
	async setPermissions(context, { token, attendeeId, permissions }) {
		await setPermissions(token, attendeeId, permissions)
		const updatedData = {
			permissions,
			attendeePermissions: permissions,
		}
		context.commit('updateParticipant', { token, attendeeId, updatedData })
	},
}

export default { state, mutations, getters, actions }

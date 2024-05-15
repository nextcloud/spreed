/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import Vue from 'vue'

// eslint-disable-next-line
// import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { generateUrl } from '@nextcloud/router'

import { ATTENDEE, PARTICIPANT } from '../constants.js'
import {
	joinCall,
	leaveCall,
} from '../services/callsService.js'
import { EventBus } from '../services/EventBus.js'
import {
	promoteToModerator,
	demoteFromModerator,
	removeAttendeeFromConversation,
	resendInvitations,
	sendCallNotification,
	joinConversation,
	leaveConversation,
	removeCurrentUserFromConversation,
	grantAllPermissionsToParticipant,
	removeAllPermissionsFromParticipant,
	setPermissions,
	setTyping,
	fetchParticipants,
} from '../services/participantsService.js'
import SessionStorage from '../services/SessionStorage.js'
import { talkBroadcastChannel } from '../services/talkBroadcastChannel.js'
import { useGuestNameStore } from '../stores/guestName.js'
import CancelableRequest from '../utils/cancelableRequest.js'

/**
 * Emit global event for user status update with the status from a participant
 *
 * @param {object} participant - a participant object
 */
function emitUserStatusUpdated(participant) {
	if (participant.actorType === ATTENDEE.ACTOR_TYPE.USERS) {
		emit('user_status:status.updated', {
			status: participant.status,
			message: participant.statusMessage,
			icon: participant.statusIcon,
			clearAt: participant.statusClearAt,
			userId: participant.actorId,
		})
	}
}

const state = () => ({
	attendees: {
	},
	peers: {
	},
	phones: {
	},
	inCall: {
	},
	connecting: {
	},
	typing: {
	},
	speaking: {
	},
	initialised: {
	},
	/**
	 * Stores the cancel function returned by `cancelableFetchParticipants`,
	 * which allows to cancel the previous request for participants
	 * when quickly switching to a new conversation.
	 */
	cancelFetchParticipants: null,
	speakingInterval: null,
})

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
	 * Gets the array of external session ids.
	 *
	 * @param {object} state - the state object.
	 * @param {object} getters - the getters object.
	 * @param {object} rootState - the rootState object.
	 * @param {object} rootGetters - the rootGetters object.
	 * @return {Array} the typing session IDs array.
	 */
	externalTypingSignals: (state, getters, rootState, rootGetters) => (token) => {
		if (!state.typing[token]) {
			return []
		}

		return Object.keys(state.typing[token]).filter(sessionId => rootGetters.getSessionId() !== sessionId)
	},

	/**
	 * Gets the array of external session ids.
	 *
	 * @param {object} state - the state object.
	 * @param {object} getters - the getters object.
	 * @param {object} rootState - the rootState object.
	 * @param {object} rootGetters - the rootGetters object.
	 * @return {boolean} the typing status of actor.
	 */
	actorIsTyping: (state, getters, rootState, rootGetters) => {
		if (!state.typing[rootGetters.getToken()]) {
			return false
		}

		return Object.keys(state.typing[rootGetters.getToken()]).some(sessionId => rootGetters.getSessionId() === sessionId)
	},

	/**
	 * Gets the participants array filtered to include only those that are
	 * currently typing.
	 *
	 * @param {object} state - the state object.
	 * @param {object} getters - the getters object.
	 * @param {object} rootState - the rootState object.
	 * @param {object} rootGetters - the rootGetters object.
	 * @return {Array} the participants array (for registered users only).
	 */
	participantsListTyping: (state, getters, rootState, rootGetters) => (token) => {
		if (!getters.externalTypingSignals(token).length) {
			return []
		}

		return getters.participantsList(token).filter(attendee => {
			// Check if participant's sessionId matches with any of sessionIds from signaling...
			return getters.externalTypingSignals(token).some((sessionId) => attendee.sessionIds.includes(sessionId))
				// ... and it's not the participant with same actorType and actorId as yourself
				&& (attendee.actorType !== rootGetters.getActorType() || attendee.actorId !== rootGetters.getActorId())
		})
	},

	/**
	 * Gets the speaking information for the participant.
	 *
	 * @param {object} state - the state object.
	 * param {number} attendeeId - attendee's ID for the participant in conversation.
	 * @return {object|undefined}
	 */
	getParticipantSpeakingInformation: (state) => (attendeeId) => {
		return state.speaking[attendeeId]
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
	 * Gets the initialisation status of the participants for a conversation.
	 * This is used to determine if the participants have been fetched for a
	 * conversation or not.
	 *
	 * @param {object} state - the state object.
	 * param {string} token - the conversation token.
	 * @return {boolean} - The initialisation status of the participants.
	 */
	participantsInitialised: (state) => (token) => {
		return state.initialised[token]
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
			return state.attendees[token][participantIdentifier.attendeeId] ?? null
		}

		// Fallback, sometimes actorId and actorType are set before the attendeeId
		return Object.entries(state.attendees[token]).find(([attendeeId, attendee]) => {
			return (participantIdentifier.actorType && participantIdentifier.actorId
					&& attendee.actorType === participantIdentifier.actorType
					&& attendee.actorId === participantIdentifier.actorId)
				|| (participantIdentifier.sessionId && attendee.sessionIds.includes(participantIdentifier.sessionId))
		})?.[1] ?? null
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
				if (state.attendees[token][attendeeId].actorType === ATTENDEE.ACTOR_TYPE.USERS
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

	getPhoneStatus: (state) => (callId) => {
		return state.phones[callId]?.state?.status
	},

	getPhoneMute: (state) => (callId) => {
		return state.phones[callId]?.mute
	},

	participantsInCall: (state) => (token) => {
		if (state.attendees[token]) {
			return Object.values(state.attendees[token]).filter(attendee => attendee.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED).length
		}
		return 0
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
			state.attendees[token][attendeeId] = Object.assign({}, state.attendees[token][attendeeId], updatedData)
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

	setParticipantsInitialised(state, { token, initialised }) {
		Vue.set(state.initialised, token, initialised)
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
	 * Sets the typing status of a participant in a conversation.
	 *
	 * Note that "updateParticipant" should not be called to add a "typing"
	 * property to an existing participant, as the participant would be reset
	 * when the participants are purged whenever they are fetched again.
	 * Similarly, "addParticipant" can not be called either to add a participant
	 * if it was not fetched yet but the signaling reported it as being typing,
	 * as the attendeeId would be unknown.
	 *
	 * @param {object} state - current store state.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - the conversation that the participant is
	 *        typing in.
	 * @param {string} data.sessionId - the Nextcloud session ID of the
	 *        participant.
	 * @param {boolean} data.typing - whether the participant is typing or not.
	 * @param {number} data.expirationTimeout - id of timeout to watch for received signal expiration.
	 */
	setTyping(state, { token, sessionId, typing, expirationTimeout }) {
		if (!state.typing[token]) {
			Vue.set(state.typing, token, {})
		}

		if (state.typing[token][sessionId]) {
			clearTimeout(state.typing[token][sessionId].expirationTimeout)
		}

		if (typing) {
			Vue.set(state.typing[token], sessionId, { expirationTimeout })
		} else {
			Vue.delete(state.typing[token], sessionId)
		}
	},

	/**
	 * Sets the speaking status of a participant in a conversation / call.
	 *
	 * Note that "updateParticipant" should not be called to add a "speaking"
	 * property to an existing participant, as the participant would be reset
	 * when the participants are purged whenever they are fetched again.
	 * Similarly, "addParticipant" can not be called either to add a participant
	 * if it was not fetched yet but the call model reported it as being
	 * speaking, as the attendeeId would be unknown.
	 *
	 * @param {object} state - current store state.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.attendeeId - the attendee ID of the participant in conversation.
	 * @param {boolean} data.speaking - whether the participant is speaking or not
	 */
	setSpeaking(state, { attendeeId, speaking }) {
		// create a dummy object for current call
		if (!state.speaking[attendeeId]) {
			Vue.set(state.speaking, attendeeId, { speaking, lastTimestamp: Date.now(), totalCountedTime: 0 })
		}
		state.speaking[attendeeId].speaking = speaking
	},

	/**
	 * Tracks the interval id to update speaking information for a current call.
	 *
	 * @param {object} state - current store state.
	 * @param {number} interval - interval id.
	 */
	setSpeakingInterval(state, interval) {
		Vue.set(state, 'speakingInterval', interval)
	},

	/**
	 * Update speaking information for a participant.
	 *
	 * @param {object} state - current store state.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.attendeeId - the attendee ID of the participant in conversation.
	 * @param {boolean} data.speaking - whether the participant is speaking or not
	 */
	updateTimeSpeaking(state, { attendeeId, speaking }) {
		if (!state.speaking[attendeeId]) {
			return
		}

		const currentTimestamp = Date.now()
		const currentSpeakingState = state.speaking[attendeeId].speaking

		if (!currentSpeakingState && !speaking) {
			// false -> false, no updates
			return
		}

		if (currentSpeakingState) {
			// true -> false / true -> true, participant is still speaking or finished to speak, update total time
			state.speaking[attendeeId].totalCountedTime += (currentTimestamp - state.speaking[attendeeId].lastTimestamp)
		}

		// false -> true / true -> false / true -> true, update timestamp of last check / signal
		state.speaking[attendeeId].lastTimestamp = currentTimestamp
	},

	/**
	 * Purge the speaking information for recent call when local participant leaves call
	 * (including cases when the call ends for everyone).
	 *
	 * @param {object} state - current store state.
	 */
	purgeSpeakingStore(state) {
		Vue.set(state, 'speaking', {})

		if (state.speakingInterval) {
			clearInterval(state.speakingInterval)
			Vue.set(state, 'speakingInterval', null)
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

	setCancelFetchParticipants(state, cancelFunction) {
		state.cancelFetchParticipants = cancelFunction
	},

	setPhoneState(state, { callid, value = {} }) {
		if (!state.phones[callid]) {
			Vue.set(state.phones, callid, { state: null, mute: 0 })
		}
		Vue.set(state.phones[callid], 'state', value)
	},

	setPhoneMute(state, { callid, value }) {
		if (!state.phones[callid]) {
			Vue.set(state.phones, callid, { state: null, mute: 0 })
		}
		Vue.set(state.phones[callid], 'mute', value)
	},

	deletePhoneState(state, callid) {
		Vue.delete(state.phones, callid)
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
			commit('setParticipantsInitialised', { token, initialised: false })
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

	/**
	 * Fetches participants that belong to a particular conversation
	 * specified with its token.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the conversation token;
	 * @return {object|null}
	 */
	async fetchParticipants(context, { token }) {
		// Cancel a previous request
		context.dispatch('cancelFetchParticipants')
		// Get a new cancelable request function and cancel function pair
		const { request, cancel } = CancelableRequest(fetchParticipants)
		// Assign the new cancel function to our data value
		context.commit('setCancelFetchParticipants', cancel)

		try {
			const response = await request(token)
			const hasUserStatuses = !!response.headers['x-nextcloud-has-user-statuses']

			context.dispatch('patchParticipants', { token, newParticipants: response.data.ocs.data, hasUserStatuses })

			if (context.state.initialised[token] === false) {
				context.commit('setParticipantsInitialised', { token, initialised: true })
			}
			// Discard current cancel function
			context.commit('setCancelFetchParticipants', null)

			return response
		} catch (exception) {
			if (exception?.response?.status === 403) {
				context.dispatch('fetchConversation', { token })
			} else if (!CancelableRequest.isCancel(exception)) {
				console.error(exception)
				window.OCP.Toast.error(t('spreed', 'An error occurred while fetching the participants'))
			}
			return null
		}
	},

	/**
	 * Update participants in the store with specified token.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the conversation token;
	 * @param {object} data.newParticipants the participant array;
	 * @param {boolean} data.hasUserStatuses whether participants has user statuses or not;
	 */
	async patchParticipants(context, { token, newParticipants, hasUserStatuses }) {
		const guestNameStore = useGuestNameStore()

		const currentParticipants = context.state.attendees[token]
		for (const attendeeId of Object.keys(Object(currentParticipants))) {
			if (!newParticipants.some(participant => participant.attendeeId === +attendeeId)) {
				context.commit('deleteParticipant', { token, attendeeId })
			}
		}

		newParticipants.forEach(participant => {
			if (context.state.attendees[token]?.[participant.attendeeId]) {
				context.dispatch('updateParticipantIfHasChanged', { token, participant, hasUserStatuses })
			} else {
				context.dispatch('addParticipant', { token, participant })
				if (hasUserStatuses) {
					emitUserStatusUpdated(participant)
				}
			}

			if (participant.participantType === PARTICIPANT.TYPE.GUEST
				|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR) {
				guestNameStore.addGuestName({
					token,
					actorId: Hex.stringify(SHA1(participant.sessionIds[0])),
					actorDisplayName: participant.displayName,
				}, { noUpdate: false })
			}
		})
	},

	/**
	 * Update participant in store according to a new participant object
	 *
	 * @param {object} context store context
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the conversation token;
	 * @param {object} data.participant the new participant object;
	 * @param {boolean} data.hasUserStatuses whether user status is enabled or not;
	 * @return {boolean} whether the participant was changed
	 */
	updateParticipantIfHasChanged(context, { token, participant, hasUserStatuses }) {
		const { attendeeId } = participant
		const oldParticipant = context.state.attendees[token][attendeeId]

		// Check if any property has changed
		const changedEntries = Object.entries(participant).filter(([key, value]) => {
			// "sessionIds" is the only property with non-primitive (array) value and cannot be compared by ===
			return key === 'sessionIds'
				? JSON.stringify(oldParticipant[key]) !== JSON.stringify(value)
				: oldParticipant[key] !== value
		})

		if (changedEntries.length === 0) {
			return false
		}

		const updatedData = Object.fromEntries(changedEntries)
		context.commit('updateParticipant', { token, attendeeId, updatedData })

		// check if status-related properties have been changed
		if (hasUserStatuses && changedEntries.some(([key]) => key.startsWith('status'))) {
			emitUserStatusUpdated(participant)
		}

		return true
	},

	/**
	 * Cancels a previously running "fetchParticipants" action if applicable.
	 *
	 * @param {object} context default store context;
	 * @return {boolean} true if a request got cancelled, false otherwise
	 */
	cancelFetchParticipants(context) {
		if (context.state.cancelFetchParticipants) {
			context.state.cancelFetchParticipants('canceled')
			context.commit('setCancelFetchParticipants', null)
			return true
		}
		return false
	},

	async joinCall({ commit, getters }, { token, participantIdentifier, flags, silent, recordingConsent }) {
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

		const actualFlags = await joinCall(token, flags, silent, recordingConsent)

		const updatedData = {
			inCall: actualFlags,
		}
		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })

		EventBus.once('signaling-users-in-room', () => {
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
	 * Sends call notification for the given attendee in the conversation.
	 *
	 * @param {object} _ - unused.
	 * @param {object} data - the wrapping object.
	 * @param {string} data.token - conversation token.
	 * @param {number} data.attendeeId - attendee id to target.
	 */
	async sendCallNotification(_, { token, attendeeId }) {
		await sendCallNotification(token, { attendeeId })
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
			EventBus.emit('joined-conversation', { token })
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
				console.error(error)
				window.OCP.Toast.error(t('spreed', 'Failed to join the conversation. Try to reload the page.'))
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
				EventBus.emit('duplicate-session-detected')
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
					EventBus.emit('duplicate-session-detected')
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
		talkBroadcastChannel.postMessage({ message: 'force-fetch-all-conversations' })
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
	 * @param {'set'|'add'|'remove'} [root0.method] permissions update method
	 * @param {number} root0.permissions - bitwise combination of the permissions.
	 */
	async setPermissions(context, { token, attendeeId, method, permissions }) {
		await setPermissions(token, attendeeId, method, permissions)
		const updatedData = {
			permissions,
			attendeePermissions: permissions,
		}
		context.commit('updateParticipant', { token, attendeeId, updatedData })
	},

	async sendTypingSignal(context, { typing }) {
		if (!context.getters.currentConversationIsJoined) {
			return
		}

		await setTyping(typing)
	},

	async setTyping(context, { token, sessionId, typing }) {
		if (!typing) {
			context.commit('setTyping', { token, sessionId, typing: false })
		} else {
			const expirationTimeout = setTimeout(() => {
				// If updated 'typing' signal doesn't come in last 15s, remove it from store
				context.commit('setTyping', { token, sessionId, typing: false })
			}, 15000)
			context.commit('setTyping', { token, sessionId, typing: true, expirationTimeout })
		}
	},

	setSpeaking(context, { attendeeId, speaking }) {
		// We should update time before speaking state, to be able to check previous state
		context.commit('updateTimeSpeaking', { attendeeId, speaking })
		context.commit('setSpeaking', { attendeeId, speaking })

		if (!context.state.speakingInterval && speaking) {
			const interval = setInterval(() => {
				context.dispatch('updateIntervalTimeSpeaking')
			}, 1000)
			context.commit('setSpeakingInterval', interval)
		}
	},

	updateIntervalTimeSpeaking(context) {
		if (!context.state.speaking || !context.state.speakingInterval) {
			return
		}

		for (const attendeeId in context.state.speaking) {
			if (context.state.speaking[attendeeId].speaking) {
				context.commit('updateTimeSpeaking', { attendeeId, speaking: true })
			}
		}
	},

	purgeSpeakingStore(context) {
		context.commit('purgeSpeakingStore')
	},

	processDialOutAnswer(context, { callid }) {
		context.commit('setPhoneState', { callid })
	},

	processTransientCallStatus(context, { value }) {
		context.commit('setPhoneState', { callid: value.callid, value })

		if (value.status === 'cleared' || value.status === 'rejected') {
			setTimeout(() => {
				context.commit('deletePhoneState', value.callid)
			}, 5000)
		}
	},

	addPhonesStates(context, { phoneStates }) {
		Object.values(phoneStates).forEach(phoneState => {
			context.commit('setPhoneState', {
				callid: phoneState.callid,
				value: phoneState
			})
		})
	},

	deletePhoneState(context, { callid }) {
		context.commit('deletePhoneState', callid)
	},

	setPhoneMute(context, { callid, value }) {
		context.commit('setPhoneMute', { callid, value })
	},
}

export default { state, mutations, getters, actions }

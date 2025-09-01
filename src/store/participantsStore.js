import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import { ATTENDEE, PARTICIPANT } from '../constants.ts'
import { banActor } from '../services/banService.ts'
import {
	joinCall,
	leaveCall,
} from '../services/callsService.ts'
import { hasTalkFeature, setRemoteCapabilities } from '../services/CapabilitiesManager.ts'
import { EventBus } from '../services/EventBus.ts'
import {
	demoteFromModerator,
	fetchParticipants,
	grantAllPermissionsToParticipant,
	joinConversation,
	leaveConversation,
	promoteToModerator,
	removeAllPermissionsFromParticipant,
	removeAttendeeFromConversation,
	removeCurrentUserFromConversation,
	resendInvitations,
	sendCallNotification,
	setPermissions,
	setTyping,
} from '../services/participantsService.js'
import SessionStorage from '../services/SessionStorage.js'
import { talkBroadcastChannel } from '../services/talkBroadcastChannel.js'
import { useActorStore } from '../stores/actor.ts'
import { useCallViewStore } from '../stores/callView.ts'
import { useGuestNameStore } from '../stores/guestName.js'
import pinia from '../stores/pinia.ts'
import { useSessionStore } from '../stores/session.ts'
import { useTokenStore } from '../stores/token.ts'
import CancelableRequest from '../utils/cancelableRequest.js'
import { convertToUnix } from '../utils/formattedTime.ts'
import { messagePleaseTryToReload } from '../utils/talkDesktopUtils.ts'

const tokenStore = useTokenStore(pinia)

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

/**
 *
 */
function state() {
	return {
		attendees: {
		},
		peers: {
		},
		phones: {
		},
		inCall: {
		},
		joiningCall: {
		},
		connecting: {
		},
		connectionFailed: {
		},
		typing: {
		},
		speaking: {
		},
		// TODO: moved from callViewStore, separate to callExtras (with typing + speaking)
		participantRaisedHands: {
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
	}
}

const getters = {
	isInCall: (state) => (token) => {
		return !!(state.inCall[token] && Object.keys(state.inCall[token]).length > 0)
	},

	isJoiningCall: (state) => (token) => {
		return !!(state.joiningCall[token] && Object.keys(state.joiningCall[token]).length > 0)
	},

	isConnecting: (state) => (token) => {
		return !!(state.connecting[token] && Object.keys(state.connecting[token]).length > 0)
	},
	connectionFailed: (state) => (token) => {
		return state.connectionFailed[token]
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
	 * @return {Array} the typing session IDs array.
	 */
	externalTypingSignals: (state) => (token) => {
		if (!state.typing[token]) {
			return []
		}
		const actorStore = useActorStore()
		return Object.keys(state.typing[token]).filter((sessionId) => actorStore.sessionId !== sessionId)
	},

	/**
	 * Gets the array of external session ids.
	 *
	 * @param {object} state - the state object.
	 * @return {boolean} the typing status of actor.
	 */
	actorIsTyping: (state) => {
		if (!state.typing[tokenStore.token]) {
			return false
		}
		const actorStore = useActorStore()
		return Object.keys(state.typing[tokenStore.token]).some((sessionId) => actorStore.sessionId === sessionId)
	},

	/**
	 * Gets the participants array filtered to include only those that are
	 * currently typing.
	 *
	 * @param {object} state - the state object.
	 * @param {object} getters - the getters object.
	 * @return {Array} the participants array (for registered users only).
	 */
	participantsListTyping: (state, getters) => (token) => {
		if (!getters.externalTypingSignals(token).length) {
			return []
		}

		const actorStore = useActorStore()
		return getters.participantsList(token).filter((attendee) => {
			// Check if participant's sessionId matches with any of sessionIds from signaling...
			return getters.externalTypingSignals(token).some((sessionId) => attendee.sessionIds.includes(sessionId))
				// ... and it's not the participant with same actorType and actorId as yourself
				&& !actorStore.checkIfSelfIsActor(attendee)
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

	participantRaisedHandList: (state) => {
		return state.participantRaisedHands
	},
	getParticipantRaisedHand: (state) => (sessionIds) => {
		for (let i = 0; i < sessionIds.length; i++) {
			if (state.participantRaisedHands[sessionIds[i]]) {
				// note: only the raised states are stored, so no need to confirm
				return state.participantRaisedHands[sessionIds[i]]
			}
		}

		return { state: false, timestamp: null }
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
			if (Object.hasOwn(state.peers[token], sessionId)) {
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
			return Object.values(state.attendees[token]).filter((attendee) => attendee.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED).length
		}
		return 0
	},

	getParticipantBySessionId: (state) => (token, sessionId) => {
		return Object.values(Object(state.attendees[token])).find((attendee) => attendee.sessionIds.includes(sessionId))
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
			state.attendees[token] = {}
		}
		state.attendees[token][participant.attendeeId] = participant
	},

	updateParticipant(state, { token, attendeeId, updatedData }) {
		if (state.attendees[token] && state.attendees[token][attendeeId]) {
			state.attendees[token][attendeeId] = { ...state.attendees[token][attendeeId], ...updatedData }
		} else {
			console.error('Error while updating the participant')
		}
	},

	deleteParticipant(state, { token, attendeeId }) {
		if (state.attendees[token] && state.attendees[token][attendeeId]) {
			delete state.attendees[token][attendeeId]
		} else {
			console.error('The conversation you are trying to purge doesn\'t exist')
		}
	},

	setParticipantsInitialised(state, { token, initialised }) {
		state.initialised[token] = initialised
	},

	setInCall(state, { token, sessionId, flags }) {
		if (flags === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
			if (state.inCall[token] && state.inCall[token][sessionId]) {
				delete state.inCall[token][sessionId]
			}
		} else {
			if (!state.inCall[token]) {
				state.inCall[token] = {}
			}
			state.inCall[token][sessionId] = flags
		}
	},

	connectionFailed(state, { token, payload }) {
		state.connectionFailed[token] = payload
	},

	clearConnectionFailed(state, token) {
		delete state.connectionFailed[token]
	},

	joiningCall(state, { token, sessionId, flags }) {
		if (!state.joiningCall[token]) {
			state.joiningCall[token] = {}
		}
		state.joiningCall[token][sessionId] = flags
	},

	finishedJoiningCall(state, { token, sessionId }) {
		if (state.joiningCall[token] && state.joiningCall[token][sessionId]) {
			delete state.joiningCall[token][sessionId]
			if (!Object.keys(state.joiningCall[token]).length) {
				delete state.joiningCall[token]
			}
		}
	},

	connecting(state, { token, sessionId, flags }) {
		if (!state.connecting[token]) {
			state.connecting[token] = {}
		}
		state.connecting[token][sessionId] = flags
	},

	finishedConnecting(state, { token, sessionId }) {
		if (state.connecting[token] && state.connecting[token][sessionId]) {
			delete state.connecting[token][sessionId]
			if (!Object.keys(state.connecting[token]).length) {
				delete state.connecting[token]
			}
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
			state.typing[token] = {}
		}

		if (state.typing[token][sessionId]) {
			clearTimeout(state.typing[token][sessionId].expirationTimeout)
		}

		if (typing) {
			state.typing[token][sessionId] = { expirationTimeout }
		} else {
			delete state.typing[token][sessionId]
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
			state.speaking[attendeeId] = { speaking, lastTimestamp: Date.now(), totalCountedTime: 0 }
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
		state.speakingInterval = interval
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
		state.speaking = {}

		if (state.speakingInterval) {
			clearInterval(state.speakingInterval)
			state.speakingInterval = null
		}
	},

	setParticipantHandRaised(state, { sessionId, raisedHand }) {
		if (!sessionId) {
			throw new Error('Missing or empty sessionId argument in call to setParticipantHandRaised')
		}
		if (raisedHand && raisedHand.state) {
			state.participantRaisedHands[sessionId] = raisedHand
		} else {
			delete state.participantRaisedHands[sessionId]
		}
	},

	clearParticipantHandRaised(state) {
		state.participantRaisedHands = {}
	},

	/**
	 * Purge a given conversation from the previously added participants.
	 *
	 * @param {object} state - current store state.
	 * @param {string} token - the conversation to purge.
	 */
	purgeParticipantsStore(state, token) {
		if (state.attendees[token]) {
			delete state.attendees[token]
		}
	},

	addPeer(state, { token, peer }) {
		if (!state.peers[token]) {
			state.peers[token] = {} // TODO check
		}
		state.peers[token][peer.sessionId] = peer
	},

	purgePeersStore(state, token) {
		if (state.peers[token]) {
			delete state.peers[token]
		}
	},

	setCancelFetchParticipants(state, cancelFunction) {
		state.cancelFetchParticipants = cancelFunction
	},

	setPhoneState(state, { callid, value = {} }) {
		if (!state.phones[callid]) {
			state.phones[callid] = { state: null, mute: 0 }
		}
		state.phones[callid].state = value
	},

	setPhoneMute(state, { callid, value }) {
		if (!state.phones[callid]) {
			state.phones[callid] = { state: null, mute: 0 }
		}
		state.phones[callid].mute = value
	},

	deletePhoneState(state, callid) {
		delete state.phones[callid]
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

	async removeParticipant({ commit, getters }, { token, attendeeId, banParticipant, internalNote = '' }) {
		const attendee = getters.getParticipant(token, attendeeId)
		if (!attendee) {
			return
		}

		if (hasTalkFeature(token, 'ban-v1') && banParticipant) {
			try {
				await banActor(token, {
					actorId: attendee.actorId,
					actorType: attendee.actorType,
					internalNote,
				})
				showSuccess(t('spreed', 'Participant is banned successfully'))
			} catch (error) {
				showError(t('spreed', 'Error while banning the participant'))
				throw error
			}
		} else {
			await removeAttendeeFromConversation(token, attendeeId)
		}
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
				showError(t('spreed', 'An error occurred while fetching the participants'))
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
		const sessionStore = useSessionStore()

		const currentParticipants = context.state.attendees[token]
		for (const attendeeId of Object.keys(Object(currentParticipants))) {
			if (!newParticipants.some((participant) => participant.attendeeId === +attendeeId)) {
				context.commit('deleteParticipant', { token, attendeeId })
			}
		}

		newParticipants.forEach((participant) => {
			if (context.state.attendees[token]?.[participant.attendeeId]) {
				context.dispatch('updateParticipantIfHasChanged', { token, participant, hasUserStatuses })
			} else {
				context.dispatch('addParticipant', { token, participant })
				if (hasUserStatuses) {
					emitUserStatusUpdated(participant)
				}
			}

			// Heal unknown sessions from participants request
			// If session.inCall is undefined, this is the best attempt to get the data; but in that case
			// it will be the same for different sessions, otherwise we trust signaling messages
			const sessionsToUpdate = sessionStore.orphanSessions.filter((session) => participant.sessionIds.includes(session.sessionId))
			for (const session of sessionsToUpdate) {
				sessionStore.updateSession(session.signalingSessionId, {
					attendeeId: participant.attendeeId,
					inCall: session.inCall ?? participant.inCall,
				})
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

	async joinCall({ commit, getters, state }, { token, participantIdentifier, flags, silent, recordingConsent, silentFor }) {
		// SUMMARY: join call process
		// There are 2 main steps to join a call:
		// 1. Join the call (signaling-join-call)
		// 2A. Wait for the users list (signaling-users-in-room) INTERNAL server event
		// 2B. Wait for the users list (signaling-users-changed) EXTERNAL server event
		// In case of failure, we receive a signaling-join-call-failed event

		// Exception 1: We may receive the users list before the signaling-join-call event
		// In this case, we use the isParticipantsListReceived flag to handle this case

		// Exception 2: We may receive the users list in a second event of signaling-users-changed or signaling-users-in-room
		// In this case, we always check if the list is the updated one (it has the current participant in the call)

		const { sessionId } = participantIdentifier ?? {}

		if (!sessionId) {
			console.error('Trying to join call without sessionId')
			return
		}

		const attendee = getters.findParticipant(token, participantIdentifier)
		if (!attendee) {
			console.error('Participant not found for conversation', token, participantIdentifier)
			return
		}

		let isParticipantsListReceived = false
		let connectingTimeout = null
		commit('joiningCall', { token, sessionId, flags })

		const handleJoinCall = ([token, flags]) => {
			commit('setInCall', { token, sessionId, flags })
			commit('finishedJoiningCall', { token, sessionId })
			if (isParticipantsListReceived) {
				finishConnecting()
			} else {
				commit('connecting', { token, sessionId, flags })
				// Fallback in case we never receive the users list after joining the call
				connectingTimeout = setTimeout(() => {
					// If, by accident, we never receive a users list, just switch to
					// "Waiting for others to join the call …" after some seconds.
					finishConnecting()
				}, 10000)
			}
		}

		const handleJoinCallFailed = ([token, payload]) => {
			finishConnecting()
			commit('connectionFailed', {
				token,
				payload,
			})
			commit('setInCall', {
				token,
				sessionId: participantIdentifier.sessionId,
				flags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			})
		}

		const handleParticipantsListReceived = (payload, key) => {
			const participant = payload[0].find((p) => p[key] === sessionId)
			if (participant && participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				if (state.joiningCall[token]?.[sessionId]) {
					isParticipantsListReceived = true
					commit('connecting', { token, sessionId, flags })
					return
				}
				finishConnecting()
			}
		}

		const handleUsersInRoom = (payload) => {
			handleParticipantsListReceived(payload, 'sessionId')
		}

		const handleUsersChanged = (payload) => {
			handleParticipantsListReceived(payload, 'nextcloudSessionId')
		}

		const finishConnecting = () => {
			commit('finishedConnecting', { token, sessionId })
			commit('finishedJoiningCall', { token, sessionId })
			EventBus.off('signaling-join-call', handleJoinCall)
			EventBus.off('signaling-join-call-failed', handleJoinCallFailed)
			EventBus.off('signaling-users-in-room', handleUsersInRoom)
			EventBus.off('signaling-users-changed', handleUsersChanged)
			clearTimeout(connectingTimeout)
		}

		EventBus.once('signaling-join-call', handleJoinCall)
		EventBus.once('signaling-join-call-failed', handleJoinCallFailed)
		EventBus.on('signaling-users-in-room', handleUsersInRoom)
		EventBus.on('signaling-users-changed', handleUsersChanged)

		try {
			const actualFlags = await joinCall(token, flags, silent, recordingConsent, silentFor)
			const updatedData = {
				inCall: actualFlags,
			}
			commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })
			const callViewStore = useCallViewStore()
			callViewStore.handleJoinCall(getters.conversation(token))
		} catch (e) {
			console.error('Error while joining call: ', e)
		}
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

		const callViewStore = useCallViewStore()
		if (callViewStore.isLiveTranscriptionEnabled) {
			// It is not awaited as it is not needed to guarantee that the
			// transcription was disabled (the live_transcription app should
			// detect it when the participant leaves) and thus it would
			// unnecesarily delay leaving the call.
			callViewStore.disableLiveTranscription(token)
		}

		await leaveCall(token, all)

		const updatedData = {
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, attendeeId: attendee.attendeeId, updatedData })

		// clear raised hands as they were specific to the call
		commit('clearParticipantHandRaised')

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
	 * @param {number} [data.attendeeId] - attendee id to target, or null for all.
	 * @param {string} [data.actorId] - if attendee is provided, the actorId (email) to show in the message.
	 */
	async resendInvitations(_, { token, attendeeId, actorId }) {
		if (attendeeId) {
			try {
				await resendInvitations(token, attendeeId)
				showSuccess(t('spreed', 'Invitation was sent to {actorId}', { actorId }))
			} catch (error) {
				showError(t('spreed', 'Could not send invitation to {actorId}', { actorId }))
			}
		} else {
			try {
				await resendInvitations(token)
				showSuccess(t('spreed', 'Invitations sent'))
			} catch (e) {
				showError(t('spreed', 'Error occurred when sending invitations'))
			}
		}
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
		const actorStore = useActorStore()

		try {
			const response = await joinConversation({ token, forceJoin })

			// Update the participant and actor session after a force join
			actorStore.setCurrentParticipant(response.data.ocs.data)
			context.dispatch('addConversation', response.data.ocs.data)
			context.dispatch('updateSessionId', {
				token,
				participantIdentifier: actorStore.participantIdentifier,
				sessionId: response.data.ocs.data.sessionId,
			})

			if (response.data.ocs.data.remoteServer) {
				// fetch and store remote capabilities for federated conversation
				await setRemoteCapabilities(response)
			}

			SessionStorage.setItem('joined_conversation', token)
			EventBus.emit('joined-conversation', { token })
			return response
		} catch (error) {
			if (error?.response?.status === 409 && error?.response?.data?.ocs?.data) {
				const responseData = error.response.data.ocs.data
				let maxLastPingAge = convertToUnix(Date.now()) - 40
				if (responseData.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED) {
					// When the user is/was in a call, we accept 20 seconds more delay
					maxLastPingAge -= 20
				}
				if (maxLastPingAge > responseData.lastPing) {
					console.debug('Force joining automatically because the old session didn\'t ping for 40 seconds')
					await context.dispatch('forceJoinConversation', { token })
				} else {
					EventBus.emit('session-conflict-confirmation', token)
				}
			} else if (error?.response?.status === 403 && error?.response?.data?.ocs?.data?.error === 'ban') {
				EventBus.emit('forbidden-route', error.response.data.ocs.data)
			} else {
				console.error(error)
				showError(t('spreed', 'Failed to join the conversation.') + '\n' + messagePleaseTryToReload)
			}
		}
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
		const actorStore = useActorStore()
		if (context.getters.isInCall(token)) {
			await context.dispatch('leaveCall', {
				token,
				participantIdentifier: actorStore.participantIdentifier,
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
		talkBroadcastChannel.postMessage({ message: 'force-fetch-all-conversations', options: { all: true } })
	},

	/**
	 * PUBLISHING PERMISSIONS
	 */

	/**
	 * Grant all permissions for a given participant.
	 *
	 * @param {object} context - the context object.
	 * @param {object} payload - the arguments object.
	 * @param {string} payload.token - the conversation token.
	 * @param {string} payload.attendeeId - the participant-s attendeeId.
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
	 * @param {object} payload - the arguments object.
	 * @param {string} payload.token - the conversation token.
	 * @param {string} payload.attendeeId - the participant-s attendeeId.
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
	 * @param {object} payload - the arguments object.
	 * @param {string} payload.token - the conversation token.
	 * @param {string} payload.attendeeId - the participant-s attendeeId.
	 * @param {'set'|'add'|'remove'} [payload.method] permissions update method
	 * @param {number} payload.permissions - bitwise combination of the permissions.
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
		if (!tokenStore.currentConversationIsJoined) {
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

	setParticipantHandRaised(context, { sessionId, raisedHand }) {
		context.commit('setParticipantHandRaised', { sessionId, raisedHand })
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
		Object.values(phoneStates).forEach((phoneState) => {
			context.commit('setPhoneState', {
				callid: phoneState.callid,
				value: phoneState,
			})
		})
	},

	deletePhoneState(context, { callid }) {
		context.commit('deletePhoneState', callid)
	},

	setPhoneMute(context, { callid, value }) {
		context.commit('setPhoneMute', { callid, value })
	},

	clearConnectionFailed(context, token) {
		context.commit('clearConnectionFailed', token)
	},
}

export default { state, mutations, getters, actions }

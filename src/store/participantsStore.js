/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
import Vue from 'vue'
import {
	promoteToModerator,
	demoteFromModerator,
	removeUserFromConversation,
	removeGuestFromConversation,
} from '../services/participantsService'
import {
	joinCall,
	leaveCall,
} from '../services/callsService'
import { PARTICIPANT } from '../constants'
import { EventBus } from '../services/EventBus'

const state = {
	forceCallFlag: {
	},
	participants: {
	},
}

const getters = {
	/**
	 * Gets the participants array
	 * @param {object} state the state object.
	 * @returns {array} the participants array (if there are participants in the store)
	 */
	participantsList: (state) => (token) => {
		if (state.participants[token]) {
			return state.participants[token]
		}
		return []
	},
	getParticipant: (state) => (token, index) => {
		if (state.participants[token] && state.participants[token][index]) {
			return state.participants[token][index]
		}
		return {}
	},
	getParticipantIndex: (state) => (token, participantIdentifier) => {
		if (!state.participants[token]) {
			return -1
		}

		let index

		if (participantIdentifier.hasOwnProperty('userId')) {
			index = state.participants[token].findIndex(participant => participant.userId === participantIdentifier.userId)
		} else {
			index = state.participants[token].findIndex(participant => participant.sessionId === participantIdentifier.sessionId)
		}

		return index
	},
}

const mutations = {
	/**
	 * Adds a message to the store.
	 * @param {object} state current store state;
	 * @param {string} token the token of the conversation;
	 * @param {object} participant the participant;
	 */
	addParticipant(state, { token, participant }) {
		if (!state.participants[token]) {
			Vue.set(state.participants, token, [])
		}
		if (participant.sessionId !== '0') {
			if (state.forceCallFlag[token] && state.forceCallFlag[token][participant.sessionId]) {
				participant.flags = state.forceCallFlag[token][participant.sessionId]
			}
		}
		state.participants[token].push(participant)
	},

	/**
	 * Adds a message to the store.
	 * @param {object} state current store state;
	 * @param {string} token the token of the conversation;
	 * @param {string} sessionId the participant;
	 * @param {string} flags the participant;
	 */
	forceCallState(state, { token, sessionId, flags }) {
		if (!state.forceCallFlag[token]) {
			Vue.set(state.forceCallFlag, token, [])
		}
		Vue.set(state.forceCallFlag[token], sessionId, flags)
	},

	/**
	 * Adds a message to the store.
	 * @param {object} state current store state;
	 * @param {string} token the token of the conversation;
	 * @param {string} sessionId the participant;
	 * @param {string} flags the participant;
	 */
	removeForceCallState(state, { token, sessionId }) {
		if (!state.forceCallFlag[token]) {
			Vue.set(state.forceCallFlag, token, [])
		}
		Vue.unset(state.forceCallFlag[token], sessionId)
	},

	updateParticipant(state, { token, index, updatedData }) {
		if (state.participants[token] && state.participants[token][index]) {
			state.participants[token][index] = Object.assign(state.participants[token][index], updatedData)
		} else {
			console.error('Error while updating the participant')
		}
	},
	deleteParticipant(state, { token, index }) {
		if (state.participants[token] && state.participants[token][index]) {
			Vue.delete(state.participants[token], index)
		} else {
			console.error(`The conversation you are trying to purge doesn't exist`)
		}
	},
	/**
	 * Purges a given conversation from the previously added participants
	 * @param {object} state current store state;
	 * @param {string} token the conversation to purge;
	 */
	purgeParticipantsStore(state, token) {
		if (state.participants[token]) {
			Vue.delete(state.participants, token)
		}
	},
}

const actions = {

	/**
	 * Adds participant to the store.
	 *
	 * Only call this after purgeParticipantsStore, otherwise use addParticipantOnce
	 *
	 * @param {object} context default store context;
	 * @param {string} token the conversation to add the participant;
	 * @param {object} participant the participant;
	 */
	addParticipant({ commit }, { token, participant }) {
		commit('addParticipant', { token, participant })
	},
	/**
	 * Only add a participant when they are not there yet
	 *
	 * @param {object} context default store context;
	 * @param {string} token the conversation to add the participant;
	 * @param {object} participant the participant;
	 */
	addParticipantOnce({ commit, getters }, { token, participant }) {
		const index = getters.getParticipantIndex(token, participant)
		if (index === -1) {
			commit('addParticipant', { token, participant })
		}
	},
	async promoteToModerator({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		if (participantIdentifier.userId) {
			// Moderation endpoint requires "participant" instead of "userId"
			await promoteToModerator(token, {
				participant: participantIdentifier.userId,
			})
		} else {
			// Guests are identified by sessionId in both cases
			await promoteToModerator(token, participantIdentifier)
		}

		const participant = getters.getParticipant(token, index)
		const updatedData = {
			participantType: participant.participantType === PARTICIPANT.TYPE.GUEST ? PARTICIPANT.TYPE.GUEST_MODERATOR : PARTICIPANT.TYPE.MODERATOR,
		}
		commit('updateParticipant', { token, index, updatedData })
	},
	async demoteFromModerator({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		if (participantIdentifier.userId) {
			// Moderation endpoint requires "participant" instead of "userId"
			await demoteFromModerator(token, {
				participant: participantIdentifier.userId,
			})
		} else {
			// Guests are identified by sessionId in both cases
			await demoteFromModerator(token, participantIdentifier)
		}

		const participant = getters.getParticipant(token, index)
		const updatedData = {
			participantType: participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR ? PARTICIPANT.TYPE.GUEST : PARTICIPANT.TYPE.USER,
		}
		commit('updateParticipant', { token, index, updatedData })
	},
	async removeParticipant({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		const participant = getters.getParticipant(token, index)
		if (participant.userId) {
			await removeUserFromConversation(token, participant.userId)
		} else {
			await removeGuestFromConversation(token, participant.sessionId)
		}
		commit('deleteParticipant', { token, index })
	},
	/**
	 * Purges a given conversation from the previously added participants
	 * @param {object} context default store context;
	 * @param {string} token the conversation to purge;
	 */
	purgeParticipantsStore({ commit }, token) {
		commit('purgeParticipantsStore', token)
	},

	async joinCall({ commit, getters }, { token, participantIdentifier, flags }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			console.error('Participant not found', participantIdentifier)
			return
		}

		const participant = getters.getParticipant(token, index)

		await joinCall(token, flags)

		const updatedData = {
			inCall: flags,
		}
		commit('updateParticipant', { token, index, updatedData })

		if (participant.sessionId !== '0') {
			commit('forceCallState', {
				token,
				sessionId: participant.sessionId,
				flags,
			})

			EventBus.$once('Signaling::joinCall', () => {
				commit('removeForceCallState', {
					token,
					sessionId: participant.sessionId,
				})
			})
		}
	},

	async leaveCall({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		const participant = getters.getParticipant(token, index)

		await leaveCall(token)

		const updatedData = {
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, index, updatedData })

		if (participant.sessionId !== '0') {
			commit('forceCallState', {
				token,
				sessionId: participant.sessionId,
				flags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
			})

			EventBus.$once('Signaling::leaveCall', () => {
				commit('removeForceCallState', {
					token,
					sessionId: participant.sessionId,
				})
			})
		}
	},
}

export default { state, mutations, getters, actions }

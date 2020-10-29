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

const state = {
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

		if (participantIdentifier.hasOwnProperty('attendeeId')) {
			index = state.participants[token].findIndex(participant => participant.attendeeId === participantIdentifier.attendeeId)
		} else if (participantIdentifier.hasOwnProperty('actorId') && participantIdentifier.hasOwnProperty('actorType')) {
			index = state.participants[token].findIndex(participant => participant.actorId === participantIdentifier.actorId && participant.actorType === participantIdentifier.actorType)
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
	 * @param {object} token the token of the conversation;
	 * @param {object} participant the participant;
	 */
	addParticipant(state, { token, participant }) {
		if (!state.participants[token]) {
			Vue.set(state.participants, token, [])
		}
		state.participants[token].push(participant)
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
	async promoteToModerator({ commit, getters }, { token, attendeeId }) {
		const index = getters.getParticipantIndex(token, { attendeeId })
		if (index === -1) {
			return
		}

		await promoteToModerator(token, {
			attendeeId,
		})

		const participant = getters.getParticipant(token, index)
		const updatedData = {
			participantType: participant.participantType === PARTICIPANT.TYPE.GUEST ? PARTICIPANT.TYPE.GUEST_MODERATOR : PARTICIPANT.TYPE.MODERATOR,
		}
		commit('updateParticipant', { token, index, updatedData })
	},
	async demoteFromModerator({ commit, getters }, { token, attendeeId }) {
		const index = getters.getParticipantIndex(token, { attendeeId })
		if (index === -1) {
			return
		}

		await demoteFromModerator(token, {
			attendeeId,
		})

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
		if (participant.actorType === 'users') {
			await removeUserFromConversation(token, participant.actorId)
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

	updateSessionId({ commit, getters }, { token, participantIdentifier, sessionId }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			console.error('Participant not found', participantIdentifier)
			return
		}

		const updatedData = {
			sessionId: sessionId,
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, index, updatedData })
	},

	updateUser({ commit, getters }, { token, participantIdentifier, updatedData }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			console.error('Participant not found', participantIdentifier)
			return
		}

		commit('updateParticipant', { token, index, updatedData })
	},

	async joinCall({ commit, getters }, { token, participantIdentifier, flags }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			console.error('Participant not found', participantIdentifier)
			return
		}

		await joinCall(token, flags)

		const updatedData = {
			inCall: flags,
		}
		commit('updateParticipant', { token, index, updatedData })
	},

	async leaveCall({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		await leaveCall(token)

		const updatedData = {
			inCall: PARTICIPANT.CALL_FLAG.DISCONNECTED,
		}
		commit('updateParticipant', { token, index, updatedData })
	},
}

export default { state, mutations, getters, actions }

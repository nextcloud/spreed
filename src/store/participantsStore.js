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
import { promoteToModerator, demoteFromModerator } from '../services/participantsService'
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
		let index

		if (participantIdentifier.hasOwnProperty('participant')) {
			index = state.participants[token].findIndex(participant => participant.userId === participantIdentifier.participant)
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
		}
	},
	/**
	 * Resets the store to it's original state
	 * @param {object} state current store state;
	 * @param {string} token the conversation to purge;
	 */
	purgeParticipantsStore(state, token) {
		Vue.delete(state.participants, token)
	},
}

const actions = {

	/**
	 * Adds participant to the store.
	 *
	 * @param {object} context default store context;
	 * @param {string} token the conversation to purge;
	 * @param {object} participant the participant;
	 */
	addParticipant({ commit }, { token, participant }) {
		commit('addParticipant', { token, participant })
	},
	async promoteToModerator({ commit, getters }, { token, participantIdentifier }) {
		const index = getters.getParticipantIndex(token, participantIdentifier)
		if (index === -1) {
			return
		}

		await promoteToModerator(token, participantIdentifier)

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

		await demoteFromModerator(token, participantIdentifier)

		const participant = getters.getParticipant(token, index)
		const updatedData = {
			participantType: participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR ? PARTICIPANT.TYPE.GUEST : PARTICIPANT.TYPE.USER,
		}
		commit('updateParticipant', { token, index, updatedData })
	},
	/**
	 * Resets the store to it's original state.
	 * @param {object} context default store context;
	 * @param {string} token the conversation to purge;
	 */
	purgeParticipantsStore({ commit }, token) {
		commit('purgeParticipantsStore', token)
	},
}

export default { state, mutations, getters, actions }

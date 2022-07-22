/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
import isEqual from 'lodash/isEqual'

const state = {
	selectedParticipants: [],
}

const getDefaultState = () => {
	return {
		selectedParticipants: [],
	}
}

const getters = {
	/**
	 * Gets the selected participants array
	 *
	 * @param {object} state the state object.
	 * @return {Array} the selected participants array
	 */
	selectedParticipants: (state) => {
		if (state.selectedParticipants) {
			return state.selectedParticipants
		}
		return []
	},
}

const mutations = {
	/**
	 * Adds a the selected participants to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} participant the selected participant;
	 */
	addSelectedParticipant(state, participant) {
		state.selectedParticipants = [...state.selectedParticipants, participant]
	},

	/**
	 * Adds a the selected participants to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} participant the selected participants
	 */
	removeSelectedParticipant(state, participant) {
		state.selectedParticipants = state.selectedParticipants.filter((selectedParticipant) => {
			return selectedParticipant.id !== participant.id
		})
	},

	/**
	 * Purges the store
	 *
	 * @param {object} state current store state;
	 */
	purgeNewGroupConversationStore(state) {
		Object.assign(state, getDefaultState())
	},
}

const actions = {

	/**
	 * Adds or removes the participant to the selected participants array
	 *
	 * @param {object} context default store context;
	 * @param {Function} context.commit the contexts commit function.
	 * @param {object} context.state the contexts state object.
	 * @param {object} participant the clicked participant;
	 */
	updateSelectedParticipants({ commit, state }, participant) {
		let isAlreadySelected = false
		state.selectedParticipants.forEach(selectedParticipant => {
			if (isEqual(selectedParticipant, participant)) {
				isAlreadySelected = true
			}
		})
		if (isAlreadySelected) {
			/**
			 * Remove the clicked participant from the selected participants list
			 */
			commit('removeSelectedParticipant', participant)
		} else {
			/**
			 * Add the clicked participant from the selected participants list
			 */
			commit('addSelectedParticipant', participant)
		}
	},

	/**
	 * Purge the store
	 *
	 * @param {object} context default store context;
	 */
	purgeNewGroupConversationStore(context) {
		context.commit('purgeNewGroupConversationStore')
	},
}

export default { state, mutations, getters, actions }

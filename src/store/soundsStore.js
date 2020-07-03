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

import { loadState } from '@nextcloud/initial-state'
import BrowserStorage from '../services/BrowserStorage'

const state = {
	playSoundsUser: loadState('talk', 'play_sounds'),
	playSoundsGuest: BrowserStorage.getItem('play_sounds') !== 'no',
}

const getters = {
	playSounds: (state) => {
		if (state.userId) {
			return state.playSoundsUser
		}
		return state.playSoundsGuest
	},
}

const mutations = {
	/**
	 * Set play sounds
	 *
	 * @param {object} state current store state
	 * @param {boolean} enabled Whether sounds should be played
	 */
	setPlaySounds(state, enabled) {
		state.playSoundsUser = enabled
		state.playSoundsGuest = enabled
	},
}

const actions = {
	/**
	 * Set the actor from the current user
	 *
	 * @param {object} context default store context;
	 * @param {boolean} enabled Whether sounds should be played
	 */
	setPlaySounds({ commit }, enabled) {
		commit('setPlaySounds', enabled)
	},
}

export default { state, mutations, getters, actions }

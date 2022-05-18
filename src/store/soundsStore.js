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

import fromStateOr from './helper.js'
import BrowserStorage from '../services/BrowserStorage.js'
import { setPlaySounds } from '../services/settingsService.js'

const state = {
	userId: undefined,
	playSoundsUser: fromStateOr('spreed', 'play_sounds', false),
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

	setUserId(state, userId) {
		state.userId = userId
	},
}

const actions = {

	/**
	 * @param {object} context default store context;
	 * @param {object} user A NextcloudUser object as returned by @nextcloud/auth
	 * @param {string} user.uid The user id of the user
	 */
	setCurrentUser(context, user) {
		context.commit('setUserId', user.uid)
	},

	/**
	 * Set the actor from the current user
	 *
	 * @param {object} context default store context;
	 * @param {boolean} enabled Whether sounds should be played
	 */
	async setPlaySounds(context, enabled) {
		await setPlaySounds(!context.state.userId, enabled)
		context.commit('setPlaySounds', enabled)
	},
}

export default { state, mutations, getters, actions }

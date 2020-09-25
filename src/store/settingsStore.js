/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { loadState } from '@nextcloud/initial-state'

const state = {
	readStatusPrivacy: loadState('talk', 'read_status_privacy'),
}

const getters = {
	getReadStatusPrivacy: (state) => () => {
		return state.readStatusPrivacy
	},
}

const mutations = {
	/**
	 * Updates the token
	 *
	 * @param {object} state current store state;
	 * @param {string} privacy The token of the active conversation
	 */
	updateReadStatusPrivacy(state, privacy) {
		state.readStatusPrivacy = privacy
	},
}

const actions = {

	/**
	 * Updates the token
	 *
	 * @param {object} context default store context;
	 * @param {string} privacy The new selected privacy
	 */
	updateReadStatusPrivacy(context, privacy) {
		context.commit('updateReadStatusPrivacy', privacy)
	},
}

export default { state, mutations, getters, actions }

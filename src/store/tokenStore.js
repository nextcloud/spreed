/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

const state = {
	token: '',
}

const getters = {
	getToken: (state) => () => {
		return state.token
	},
}

const mutations = {
	/**
	 * Updates the token
	 *
	 * @param {object} state current store state;
	 * @param {string} newToken the new token,
	 */
	updateToken(state, newToken) {
		state.token = newToken
	},
}

const actions = {

	/**
	 * Updates the token
	 *
	 * @param {object} context default store context;
	 * @param {string} newToken the new token,

	 */
	updateToken(context, newToken) {
		context.commit('updateToken', newToken)
	},
}

export default { state, mutations, getters, actions }

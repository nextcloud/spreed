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
	userId: null,
	displayName: null,
	actorType: null,
}

const getters = {
	getUserId: (state) => () => {
		return state.userId
	},
	getActorId: (state) => () => {
		return state.userId // FIXME adjust for guests
	},
	getActorType: (state) => () => {
		return state.actorType
	},
	getDisplayName: (state) => () => {
		return state.displayName
	},
}

const mutations = {
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {string} userId The user id
	 */
	setUserId(state, userId) {
		state.userId = userId
	},
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {string} displayName The name
	 */
	setDisplayName(state, displayName) {
		state.displayName = displayName
	},
	/**
	 * Set the userId
	 *
	 * @param {object} state current store state;
	 * @param {actorType} actorType The actor type of the user
	 */
	setActorType(state, actorType) {
		state.actorType = actorType
	},
}

const actions = {

	/**
	 * Shows the sidebar
	 *
	 * @param {object} context default store context;
	 * @param {object} user A NextcloudUser object as returned by @nextcloud/auth
	 * @param {string} user.uid The user id of the user
	 * @param {string|null} user.displayName The display name of the user
	 */
	setCurrentUser(context, user) {
		context.commit('setUserId', user.uid)
		context.commit('setDisplayName', user.displayName || user.uid)
		context.commit('setActorType', 'users')
	},
}

export default { state, mutations, getters, actions }

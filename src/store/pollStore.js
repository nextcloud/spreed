/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
import Vue from 'vue'

const state = {
	polls: {},
}

const getters = {
	getPoll: (state) => (token, id) => {
		return state.polls[token][id]
	},
}

const mutations = {
	addPoll(state, { token, poll }) {
		if (!state[token]) {
			Vue.set(state.polls, token, {})
		}
		Vue.set(state.polls[token], poll.id, poll)
		console.log(state.polls)
	},
}

const actions = {
	addPoll(context, { token, poll }) {
		context.commit('addPoll', { token, poll })
	},
}

export default { state, mutations, getters, actions }

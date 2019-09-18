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
import Vue from 'vue'

const state = {
	messages: {
	}
}

const getters = {
	messagesList: (state) => (token) => {
		if (state.messages[token]) {
			return Object.values(state.messages[token])
		}
		return []
	},
	messages: (state) => (token) => {
		if (state.messages[token]) {
			return state.messages[token]
		}
		return {}
	}
}

const mutations = {
	addMessage(state, message) {
		if (!state.messages[message.token]) {
			Vue.set(state.messages, message.token, {})
		}
		Vue.set(state.messages[message.token], message.id, message)
	},
	deleteMessage(state, token, id) {
		delete state.messages[token][id]
	}
}

const actions = {
	processMessage(context, message) {
		if (message.parent) {
			context.commit('addMessage', message.parent)
			message.parent = message.parent.id
		}
		context.commit('addMessage', message)
	},
	deleteMessage(context, token, id) {
		context.commit('deleteMessage', token, id)
	}
}

export default { state, mutations, getters, actions }

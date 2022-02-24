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
import {
	getReactionsDetails,
} from '../services/messagesService'

const state = {
	/**
	 * Structure:
	 * reactions.token.messageId
	 */
	reactions: {},
}

const getters = {
	reactions: (state) => (token, messageId) => {
		if (state.reactions?.[token]?.[messageId]) {
			return state.reactions[token][messageId]
		} else {
			return undefined
		}
	},
}

const mutations = {
	addReactions(state, { token, messageId, reactions }) {
		if (!state.reactions[token]) {
			Vue.set(state.reactions, [token], [messageId])
		}
		state.reactions[token][messageId] = reactions
	},
}

const actions = {
	/**
	 * Gets the full reactions array for a given message.
	 *
	 * @param {*} context the context object
	 * @param {*} param1 conversation token, message id
	 */
	async getReactions(context, { token, messageId }) {

		try {
			const response = await getReactionsDetails(token, messageId)
			context.commit('addReactions', {
				token,
				messageId,
				reactions: response.data.ocs.data,
			})

			return response
		} catch (error) {
			console.debug(error)
		}
	},
}

export default { state, mutations, getters, actions }

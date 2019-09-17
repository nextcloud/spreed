/**
 * @copyright Copyright (c) 2018 Team Popcorn <teampopcornberlin@gmail.com>
 *
 * @author Team Popcorn <teampopcornberlin@gmail.com>
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
	conversations: {
	},
	conversationsNames: {
	}
}

const getters = {
	conversationsList: state => Object.values(state.conversations)
}

const mutations = {
	/**
     * Adds a conversation to the store
     * @param {Object} state current store state
     * @param {Object} conversation the conversation api object
     */
	addConversation(state, conversation) {
		state.conversations[conversation.id] = conversation
	},
	/**
     *
     * @param {Object} state current state object
     * @param {Object} object destructuring object
     * @param {int} object.id conversation id
     * @param {string} object.displayName conversation name
     */
	indexConversationName(state, { id, displayName }) {
		state.conversationsNames[id] = displayName
	}
}

const actions = {
	/**
     * Add a conversation to the store and index the displayname
     * @param {Object} context default store context
     * @param {Object} conversation the conversation api object
     */
	addConversation(context, conversation) {
		context.commit('addConversation', conversation)
		context.commit('indexConversationName', conversation)
	}
}

export default { state, mutations, getters, actions }

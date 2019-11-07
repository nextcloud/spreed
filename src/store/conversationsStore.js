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
import { makePublic, makePrivate } from '../services/conversationsService'
import { CONVERSATION } from '../constants'

const getDefaultState = () => {
	return {
		conversations: {
		},
	}
}

const state = {
	conversations: {
	},
}

const getters = {
	conversations: state => state.conversations,
	conversationsList: state => Object.values(state.conversations),
}

const mutations = {
	/**
	 * Adds a conversation to the store.
	 *
	 * @param {object} state current store state;
	 * @param {object} conversation the conversation;
	 */
	addConversation(state, conversation) {
		Vue.set(state.conversations, conversation.token, conversation)
	},
	/**
	 * Deletes a conversation from the store.
	 * @param {object} state current store state;
	 * @param {object} conversation the message;
	 */
	deleteConversation(state, conversation) {
		Vue.delete(state.conversations, conversation.token)
	},
	/**
	 * Resets the store to it's original state
	 * @param {object} state current store state;
	 */
	purgeConversationsStore(state) {
		Object.assign(state, getDefaultState())
	},
}

const actions = {
	/**
	 * Add a conversation to the store and index the displayname.
	 *
	 * @param {object} context default store context;
	 * @param {object} conversation the conversation;
	 */
	addConversation(context, conversation) {
		context.commit('addConversation', conversation)
	},

	/**
	 * Delete a object
	 *
	 * @param {object} context default store context;
	 * @param {object} conversation the conversation to be deleted;
	 */
	deleteConversation(context, conversation) {
		context.commit('deleteConversation', conversation)
	},
	/**
	 * Resets the store to it's original state.
	 * @param {object} context default store context;
	 */
	purgeConversationsStore(context) {
		context.commit('purgeConversationsStore')
	},

	async toggleGuests({ commit, getters }, { token, allowGuests }) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		if (allowGuests) {
			await makePublic(token)
			conversation.type = CONVERSATION.TYPE.PUBLIC
		} else {
			await makePrivate(token)
			conversation.type = CONVERSATION.TYPE.GROUP
		}

		commit('addConversation', conversation)
	},
}

export default { state, mutations, getters, actions }

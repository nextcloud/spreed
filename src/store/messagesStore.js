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
	},
}

const getters = {
	/**
	 * Gets the messages array
	 * @param {object} state the state object.
	 * @returns {array} the messages array (if there are messages in the store)
	 */
	messagesList: (state) => (token) => {
		if (state.messages[token]) {
			return Object.values(state.messages[token])
		}
		return []
	},
	/**
	 * Gets the messages object
	 * @param {object} state the state object.
	 * @param {string} token the conversation token.
	 * @returns {object} the messages object (if there are messages in the store)
	 */
	messages: (state) => (token) => {
		if (state.messages[token]) {
			return state.messages[token]
		}
		return {}
	},
	/**
	 * Gets a single message object
	 * @param {object} state the state object.
	 * @param {string} token the conversation token.
	 * @param {string} id the message id.
	 * @returns {object} the message object (if the message is found in the store)
	 */
	message: (state) => (token, id) => {
		if (state.messages[token][id]) {
			return state.messages[token][id]
		}
		return {}
	},
}

const mutations = {
	/**
	 * Adds a message to the store.
	 * @param {object} state current store state;
	 * @param {object} message the message;
	 */
	addMessage(state, message) {
		if (!state.messages[message.token]) {
			Vue.set(state.messages, message.token, {})
		}
		Vue.set(state.messages[message.token], message.id, message)
	},
	/**
	 * Deletes a message from the store.
	 * @param {object} state current store state;
	 * @param {object} message the message;
	 */
	deleteMessage(state, message) {
		Vue.delete(state.messages[message.token], message.id)
	},
	/**
	 * Adds a temporary message to the store.
	 * @param {object} state current store state;
	 * @param {object} message the temporary message;
	 */
	addTemporaryMessage(state, message) {
		Vue.set(state.messages[message.token], message.id, message)
	},
}

const actions = {

	/**
	 * Adds message to the store.
	 *
	 * If the message has a parent message object,
	 * first it adds the parent to the store.
	 *
	 * @param {object} context default store context;
	 * @param {object} message the message;
	 */
	processMessage(context, message) {
		if (message.parent) {
			context.commit('addMessage', message.parent)
			message.parent = message.parent.id
		}
		context.commit('addMessage', message)
	},

	/**
	 * Delete a message
	 *
	 * @param {object} context default store context;
	 * @param {string} message the message to be deleted;
	 */
	deleteMessage(context, message) {
		context.commit('deleteMessage', message)
	},

	/**
	 * Add a temporary message generated in the client to
	 * the store, these messages are deleted once the full
	 * message object is recived from the server.
	 *
	 * @param {object} context default store context;
	 * @param {object} message the temporary message;
	 */
	addTemporaryMessage(context, message) {
		context.commit('addTemporaryMessage', message)
	},
}

export default { state, mutations, getters, actions }

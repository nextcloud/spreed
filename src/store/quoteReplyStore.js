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
	messagesToBeReplied: {},
}

const getters = {
	getMessageToBeReplied: (state) => (token) => {
		if (state.messagesToBeReplied[token]) {
			return state.messagesToBeReplied[token]
		}
	},
}

const mutations = {
	/**
	 * Add a message to be replied to the store. This message is generated when the
	 * reply button is clicked.
	 *
	 * @param {object} state current store state;
	 * @param {object} messageToBeReplied The message to be replied;
	 */
	addMessageToBeReplied(state, messageToBeReplied) {
		Vue.set(state.messagesToBeReplied, [messageToBeReplied.token], messageToBeReplied)
	},
	/**
	 * Add a message to be replied to the store. This message is generated when the
	 * reply button is clicked.
	 *
	 * @param {object} state current store state;
	 * @param {object} token The message to be replied;
	 */
	removeMessageToBeReplied(state, token) {
		Vue.delete(state.messagesToBeReplied, token)
	},
}

const actions = {

	/**
	 * Add a message to be replied to the store. This message is generated when the
	 * reply button is clicked.
	 *
	 * @param {object} context default store context;
	 * @param {object} messageToBeReplied The message to be replied;
	 */
	addMessageToBeReplied(context, messageToBeReplied) {
		context.commit('addMessageToBeReplied', messageToBeReplied)
	},
	/**
	 * Remove a message to be replied to the store. This is used either when the message
	 * has been replied to or the user finally decides to dismiss the reply operation.
	 *
	 * @param {object} context default store context;
	 * @param {object} token The token of the conversation whose message to be replied is
	 * being removed;
	 */
	removeMessageToBeReplied(context, token) {
		context.commit('removeMessageToBeReplied', token)
	},
}

export default { state, mutations, getters, actions }

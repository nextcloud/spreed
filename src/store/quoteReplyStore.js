/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	messagesToBeReplied: {},

	/**
	 * Cached last message input by conversation token
	 */
	currentMessageInput: {},
}

const getters = {
	getMessageToBeReplied: (state) => (token) => {
		if (state.messagesToBeReplied[token]) {
			return state.messagesToBeReplied[token]
		}
	},

	currentMessageInput: (state) => (token) => {
		return state.currentMessageInput[token] ?? ''
	},
}

const mutations = {
	/**
	 * Add a message to be replied to the store. This message is generated when the
	 * reply button is clicked.
	 *
	 * @param {object} state current store state;
	 * @param {object} message The message to be replied;
	 * @param {string} message.token The conversation token;
	 * @param {number} message.id The id of message;
	 */
	addMessageToBeReplied(state, { token, id }) {
		Vue.set(state.messagesToBeReplied, token, id)
	},
	/**
	 * Removes message to be replied from the store for the
	 * given conversation.
	 *
	 * @param {object} state current store state;
	 * @param {string} token The conversation token
	 */
	removeMessageToBeReplied(state, token) {
		Vue.delete(state.messagesToBeReplied, token)
	},

	/**
	 * Sets the current message input for a given conversation
	 *
	 * @param {object} state Current store state;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token The conversation token;
	 * @param {string} data.text Message text to set or null to clear it;
	 */
	setCurrentMessageInput(state, { token, text = null }) {
		if (text !== null) {
			// FIXME upstream: https://github.com/nextcloud-libraries/nextcloud-vue/issues/4492
			const temp = document.createElement('textarea')
			temp.innerHTML = text?.replace(/&/gmi, '&amp;') || ''
			const parsedText = temp.value.replace(/&amp;/gmi, '&').replace(/&lt;/gmi, '<')
				.replace(/&gt;/gmi, '>').replace(/&sect;/gmi, '§')

			Vue.set(state.currentMessageInput, token, parsedText)
		} else {
			Vue.delete(state.currentMessageInput, token)
		}
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

	/**
	 * Clears current messages from a deleted conversation
	 *
	 * @param {object} context default store context;
	 * @param {string} token the token of the conversation to be deleted;
	 */
	deleteMessages(context, token) {
		context.commit('removeMessageToBeReplied', token)
		context.commit('setCurrentMessageInput', { token, text: null })
	},

	/**
	 * Stores the current message input for a given conversation
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {string} data.token the token of the conversation to be deleted;
	 * @param {string} data.text string to set or null to clear it;
	 */
	setCurrentMessageInput(context, { token, text }) {
		context.commit('setCurrentMessageInput', { token, text })
	},
}

export default { state, mutations, getters, actions }

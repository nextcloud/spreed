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
import {
	makePublic,
	makePrivate,
	changeLobbyState,
	addToFavorites,
	removeFromFavorites,
} from '../services/conversationsService'
import { getCurrentUser } from '@nextcloud/auth'
import { CONVERSATION, WEBINAR } from '../constants'

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
	 * @param {object} token the token of the conversation to delete;
	 */
	deleteConversation(state, token) {
		Vue.delete(state.conversations, token)
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

		let currentUser = {
			uid: context.getters.getUserId(),
			displayName: context.getters.getDisplayName(),
		}

		// Fallback to getCurrentUser() only if if has not been set yet (as
		// getCurrentUser() needs to be overriden in public share pages as it
		// always returns an anonymous user).
		if (!currentUser.uid) {
			currentUser = getCurrentUser()
		}
		context.dispatch('addParticipantOnce', {
			token: conversation.token,
			participant: {
				inCall: conversation.participantFlags,
				lastPing: conversation.lastPing,
				sessionId: conversation.sessionId,
				participantType: conversation.participantType,
				userId: currentUser ? currentUser.uid : '',
				displayName: currentUser && currentUser.displayName ? currentUser.displayName : '', // TODO guest name from localstore?
			},
		})
	},

	/**
	 * Delete a object
	 *
	 * @param {object} context default store context;
	 * @param {object} conversation the conversation to be deleted;
	 */
	deleteConversation(context, conversation) {
		context.commit('deleteConversation', conversation.token)
	},

	/**
	 * Delete a object
	 *
	 * @param {object} context default store context;
	 * @param {object} token the token of the conversation to be deleted;
	 */
	deleteConversationByToken(context, token) {
		context.commit('deleteConversation', token)
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

	async toggleFavorite({ commit, getters }, { token, isFavorite }) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		if (isFavorite) {
			await removeFromFavorites(token)
		} else {
			await addToFavorites(token)
		}
		conversation.isFavorite = !isFavorite

		commit('addConversation', conversation)
	},

	async toggleLobby({ commit, getters }, { token, enableLobby }) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		if (enableLobby) {
			await changeLobbyState(token, WEBINAR.LOBBY.NON_MODERATORS)
			conversation.lobbyState = WEBINAR.LOBBY.NON_MODERATORS
		} else {
			await changeLobbyState(token, WEBINAR.LOBBY.NONE)
			conversation.lobbyState = WEBINAR.LOBBY.NONE
		}

		commit('addConversation', conversation)
	},

	async setLobbyTimer({ commit, getters }, { token, timestamp }) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		// The backend requires the state and timestamp to be set together.
		await changeLobbyState(token, conversation.lobbyState, timestamp)
		conversation.lobbyTimer = timestamp

		commit('addConversation', conversation)
	},

	async markConversationRead({ commit, getters }, token) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		conversation.unreadMessages = 0
		conversation.unreadMention = false

		commit('addConversation', conversation)
	},

	async updateConversationLastActive({ commit, getters }, token) {
		const conversation = Object.assign({}, getters.conversations[token])
		if (!conversation) {
			return
		}

		conversation.lastActivity = (new Date().getTime()) / 1000

		commit('addConversation', conversation)
	},
}

export default { state, mutations, getters, actions }

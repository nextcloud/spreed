/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const state = {
	token: '',
	conversationSettingsToken: '',
	fileIdForToken: null,
	/**
	 * The joining of a room with the signaling server always lags
	 * behind the "joining" of it in talk's UI. For this reason we
	 * might have a  window of time in which we might be in
	 * conversation B in talk's UI while still leaving conversation
	 * A in the signaling server.
	 */
	lastJoinedConversationToken: '',
}

const getters = {
	getToken: (state) => () => {
		return state.token
	},
	getConversationSettingsToken: (state) => () => {
		return state.conversationSettingsToken
	},
	getFileIdForToken: (state) => () => {
		return state.fileIdForToken
	},
	currentConversationIsJoined: (state) => {
		return state.lastJoinedConversationToken === state.token
	},
}

const mutations = {
	/**
	 * Updates the token
	 *
	 * @param {object} state current store state;
	 * @param {string} newToken The token of the active conversation
	 */
	updateToken(state, newToken) {
		state.token = newToken
	},

	/**
	 * Updates the token of the conversation settings dialog
	 *
	 * @param {object} state current store state;
	 * @param {string} newToken The token of the active conversation
	 */
	updateConversationSettingsToken(state, newToken) {
		state.conversationSettingsToken = newToken
	},

	/**
	 * Updates the file ID for the current token
	 *
	 * @param {object} state current store state
	 * @param {object} data the wrapping object;
	 * @param {string} data.newToken The token of the active conversation
	 * @param {number} data.newFileId The file ID of the active conversation
	 */
	updateTokenAndFileIdForToken(state, { newToken, newFileId }) {
		state.token = newToken
		state.fileIdForToken = newFileId
	},

	updateLastJoinedConversationToken(state, { token }) {
		state.lastJoinedConversationToken = token
	},
}

const actions = {

	/**
	 * Updates the token
	 *
	 * @param {object} context default store context;
	 * @param {string} newToken The token of the active conversation
	 */
	updateToken(context, newToken) {
		context.commit('updateToken', newToken)
	},

	/**
	 * Updates the token
	 *
	 * @param {object} context default store context;
	 * @param {string} newToken The token of the active conversation
	 */
	updateConversationSettingsToken(context, newToken) {
		context.commit('updateConversationSettingsToken', newToken)
	},

	/**
	 * Updates the file ID for the current token
	 *
	 * @param {object} context default store context
	 * @param {object} data the wrapping object;
	 * @param {string} data.newToken The token of the active conversation
	 * @param {number} data.newFileId The file ID of the active conversation
	 */
	updateTokenAndFileIdForToken(context, { newToken, newFileId }) {
		context.commit('updateTokenAndFileIdForToken', { newToken, newFileId })
	},

	updateLastJoinedConversationToken({ commit }, token) {
		commit('updateLastJoinedConversationToken', { token })
	},
}

export default { state, mutations, getters, actions }

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const state = {
	show: true,
	isRenamingConversation: false,
	sidebarOpenBeforeEditing: null,

}

const getters = {
	getSidebarStatus: (state) => {
		return state.show
	},
	isRenamingConversation: (state) => {
		return state.isRenamingConversation
	},
}

const mutations = {
	/**
	 * Shows the sidebar
	 *
	 * @param {object} state current store state;
	 */
	showSidebar(state) {
		state.show = true
	},
	/**
	 * Hides the sidebar
	 *
	 * @param {object} state current store state;
	 */
	hideSidebar(state) {
		state.show = false
	},
	/**
	 * Renaming state of the conversation
	 *
	 * @param {object} state current store state;
	 * @param {boolean} boolean the state of the renaming action;
	 */
	isRenamingConversation(state, boolean) {
		if (boolean) {
			// Record sidebar status before starting editing process
			state.sidebarOpenBeforeEditing = state.show
			state.isRenamingConversation = true
		} else {
			state.isRenamingConversation = false
			// Go back to the previous sidebar state
			state.show = state.sidebarOpenBeforeEditing
		}
	},
}

const actions = {

	/**
	 * Shows the sidebar
	 *
	 * @param {object} context default store context;
	 */
	showSidebar(context) {
		context.commit('showSidebar')
	},
	/**
	 * Hides the sidebar
	 *
	 * @param {object} context default store context;
	 */
	hideSidebar(context) {
		context.commit('hideSidebar')
	},
	/**
	 * Renaming state of the conversation
	 *
	 * @param {object} context default store context;
	 * @param {boolean} boolean the state of the renaming action;
	 */
	isRenamingConversation(context, boolean) {
		context.commit('isRenamingConversation', boolean)
	},
}

export default { state, mutations, getters, actions }

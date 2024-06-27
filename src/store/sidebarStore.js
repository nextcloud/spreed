/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const state = {
	show: true,
}

const getters = {
	getSidebarStatus: (state) => {
		return state.show
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
}

export default { state, mutations, getters, actions }

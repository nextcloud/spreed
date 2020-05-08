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
	 * @param {object} context default store context;
	 * @param {boolean} boolean the state of the renaming action;
	 */
	isRenamingConversation(context, boolean) {
		context.commit('isRenamingConversation', boolean)
	},
}

export default { state, mutations, getters, actions }

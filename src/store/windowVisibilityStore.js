/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const state = {
	visible: true,
	fullscreen: false,
}

const getters = {
	windowIsVisible: (state) => () => {
		return state.visible
	},
	isFullscreen: (state) => () => {
		return state.fullscreen
	},
}

const mutations = {
	/**
	 * Sets the current visibility state
	 *
	 * @param {object} state current store state;
	 * @param {boolean} value the value;
	 */
	setVisibility(state, value) {
		state.visible = value
	},

	/**
	 * Sets the fullscreen state
	 *
	 * @param {object} state current store state;
	 * @param {boolean} value the value;
	 */
	setIsFullscreen(state, value) {
		state.fullscreen = value
	},
}

const actions = {
	/**
	 * Sets the current visibility state
	 *
	 * @param {object} context the context object;
	 * @param {boolean} value the value;
	 */
	setWindowVisibility(context, value) {
		context.commit('setVisibility', value)
	},

	/**
	 * Sets the fullscreen state
	 *
	 * @param {object} context the context object;
	 * @param {boolean} value the value;
	 */
	setIsFullscreen(context, value) {
		context.commit('setIsFullscreen', value)
	},

}

export default { state, mutations, getters, actions }

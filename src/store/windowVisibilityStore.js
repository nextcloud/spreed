/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const state = {
	fullscreen: false,
}

const getters = {
	isFullscreen: (state) => () => {
		return state.fullscreen
	},
}

const mutations = {
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

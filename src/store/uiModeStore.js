/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * This store handles the values that need to be customized depending on the
 * current UI mode of Talk (main UI, embedded in Files sidebar, video
 * verification page...).
 */

const state = () => ({
	mainContainerSelector: undefined,
})

const getters = {
	getMainContainerSelector: (state, getters, rootState, rootGetters) => () => {
		return rootGetters.isFullscreen() ? state.mainContainerSelector : 'body'
	},
}

const mutations = {
	setMainContainerSelector(state, mainContainerSelector) {
		state.mainContainerSelector = mainContainerSelector
	},
}

const actions = {
	/**
	 * Set the main container selector.
	 *
	 * By default the container selector is undefined, which in practice will
	 * cause the components to use "body" as the selector.
	 *
	 * @param {object} context default store context
	 * @param {string} mainContainerSelector the selector for the container
	 */
	setMainContainerSelector(context, mainContainerSelector) {
		context.commit('setMainContainerSelector', mainContainerSelector)
	},
}

export default { state, mutations, getters, actions }

/**
 * @copyright Copyright (c) 2021 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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

/**
 * This store handles the values that need to be customized depending on the
 * current UI mode of Talk (main UI, embedded in Files sidebar, video
 * verification page...).
 */

const state = {
	mainContainerSelector: undefined,
}

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

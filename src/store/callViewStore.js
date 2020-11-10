/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
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

import BrowserStorage from '../services/BrowserStorage'
import {
	CONVERSATION,
} from '../constants'

const state = {
	isGrid: false,
	isSidebar: false,
	selectedVideoPeerId: null,
	videoBackgroundBlur: 1,
}

const getters = {
	isGrid: (state) => {
		return state.isGrid
	},
	isSidebar: (state) => {
		return state.isSidebar
	},
	selectedVideoPeerId: (state) => {
		return state.selectedVideoPeerId
	},
	getBlurFilter: (state) => (width, height) => {
		return `filter: blur(${(width * height * state.videoBackgroundBlur) / 1000}px)`
	},
}

const mutations = {

	isGrid(state, value) {
		state.isGrid = value
	},
	isSidebar(state, value) {
		state.isSidebar = value
	},
	selectedVideoPeerId(state, value) {
		state.selectedVideoPeerId = value
	},
}

const actions = {
	/**
	 * Sets the current grid mode and saves it in preferences.
	 *
	 * @param {object} context default store context;
	 * @param {bool} value true for enabled grid mode, false for speaker view;
	 */
	isGrid(context, value) {
		if (!context.getters.isSidebar()) {
			BrowserStorage.setItem('callprefs-' + context.getters.getToken() + '-isgrid', value)
		}
		context.commit('isGrid', value)
	},
	isSidebar(context, value) {
		context.commit('isSidebar', value)
	},
	selectedVideoPeerId(context, value) {
		context.commit('selectedVideoPeerId', value)
	},

	joinCall(context, { token }) {
		if (context.getters.isSidebar()) {
			context.dispatch('isGrid', false)
		}
		let isGrid = BrowserStorage.getItem('callprefs-' + token + '-isgrid')
		if (isGrid === null) {
			const conversationType = context.getters.conversations[token].type
			// default to grid view for group/public calls, otherwise speaker view
			isGrid = (conversationType === CONVERSATION.TYPE.GROUP
				|| conversationType === CONVERSATION.TYPE.PUBLIC)
		} else {
			// BrowserStorage.getItem returns a string instead of a boolean
			isGrid = (isGrid === 'true')
		}
		context.dispatch('isGrid', isGrid)
	},
}

export default { state, mutations, getters, actions }

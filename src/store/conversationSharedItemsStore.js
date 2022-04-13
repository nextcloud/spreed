/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

import Vue from 'vue'
import { getSharedItemsOverview, getSharedItems } from '../services/conversationSharedItemsService'

// Store structure
// token: {
//    media: {},
//    file: {},
//    voice: {},
//    audio: {},
//    location: {}
//    deckcard: {},
//    other: {},

const state = () => ({
	state: {},
})

const getters = {
	sharedItems: state => token => {
		const sharedItems = {}
		if (!state[token]) {
			return {}
		}
		for (const type of Object.keys(state[token])) {
			if (Object.keys(state[token][type]).length !== 0) {
				sharedItems[type] = state[token][type]
			}
		}
		return sharedItems
	},
}

export const mutations = {
	addSharedItemsOverview: (state, { token, data }) => {
		if (!state[token]) {
			Vue.set(state, token, {})
		}
		for (const type of Object.keys(data)) {
			if (!state[token][type]) {
				Vue.set(state[token], type, {})
				for (const message of data[type]) {
					if (!state[token][type]?.[message.id]) {
						Vue.set(state[token][type], message.id, message)
					}
				}
			}
		}
	},
}

const actions = {
	async getSharedItems({ commit }, { token, type, lastKnownMessageId, limit }) {
		try {
			const response = await getSharedItems(token, type, lastKnownMessageId, limit)
			// loop over the response elements and add them to the store
			for (const sharedItem in response) {
				commit('addSharedItem', sharedItem)
			}

		} catch (error) {
			console.debug(error)
		}
	},

	async getSharedItemsOverview({ commit }, { token }) {
		try {
			const response = await getSharedItemsOverview(token, 10)
			commit('addSharedItemsOverview', {
				token,
				data: response.data.ocs.data,
			})
		} catch (error) {
			console.debug(error)
		}
	},
}

export default { state, mutations, getters, actions }

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

const getItemTypeFromMessage = function(message) {
	if (message.message === '{object}') {
		if (message.messageParameters.object.type === 'geo-location') {
			return 'location'
		} else if (message.messageParameters.object.type === 'deck-card') {
			return 'deckcard'
		} else {
			return 'other'
		}
	} else {
		const messageType = message.messageType || ''
		const mimetype = message.messageParameters.file?.mimetype || ''

		if (messageType === 'voice-message') {
			return 'voice'
		} else if (mimetype.startsWith('audio/')) {
			return 'audio'
		} else if (mimetype.startsWith('image/') || mimetype.startsWith('video/')) {
			return 'media'
		} else {
			return 'file'
		}
	}
}

// sharedItemsByConversationAndType structure
// token: {
//    media: {},
//    file: {},
//    voice: {},
//    audio: {},
//    location: {}
//    deckcard: {},
//    other: {},
// },

const state = {
	sharedItemsByConversationAndType: {},
	overviewLoaded: {},
}

const getters = {
	sharedItems: state => token => {
		const sharedItems = {}
		if (!state.sharedItemsByConversationAndType[token]) {
			return {}
		}
		for (const type of Object.keys(state.sharedItemsByConversationAndType[token])) {
			if (Object.keys(state.sharedItemsByConversationAndType[token][type]).length !== 0) {
				sharedItems[type] = state.sharedItemsByConversationAndType[token][type]
			}
		}
		return sharedItems
	},
}

export const mutations = {
	addSharedItemsOverview: (state, { token, data }) => {
		Vue.set(state.overviewLoaded, token, true)

		if (!state.sharedItemsByConversationAndType[token]) {
			Vue.set(state.sharedItemsByConversationAndType, token, {})
		}

		for (const type of Object.keys(data)) {
			if (!state.sharedItemsByConversationAndType[token][type]) {
				Vue.set(state.sharedItemsByConversationAndType[token], type, {})
			}

			for (const message of data[type]) {
				Vue.set(state.sharedItemsByConversationAndType[token][type], message.id, message)
			}
		}
	},

	addSharedItemMessage: (state, { token, type, message }) => {
		if (!state.sharedItemsByConversationAndType[token]) {
			Vue.set(state.sharedItemsByConversationAndType, token, {})
		}
		if (!state.sharedItemsByConversationAndType[token][type]) {
			Vue.set(state.sharedItemsByConversationAndType[token], type, {})
		}
		if (!state.sharedItemsByConversationAndType[token][type]?.[message.id]) {
			Vue.set(state.sharedItemsByConversationAndType[token][type], message.id, message)
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

	async getSharedItemsOverview({ commit, state }, { token }) {
		if (state.overviewLoaded[token]) {
			return
		}

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

	async addSharedItemMessage({ commit }, { message }) {
		commit('addSharedItemMessage', {
			token: message.token,
			type: getItemTypeFromMessage(message),
			message,
		})
	},
}

export default { state, mutations, getters, actions }

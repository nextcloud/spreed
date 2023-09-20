/**
 * @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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

import { SHARED_ITEM } from '../constants.js'
import { getSharedItemsOverview, getSharedItems } from '../services/sharedItemsService.js'

const getItemTypeFromMessage = function(message) {
	if (message.message === '{object}') {
		if (message.messageParameters.object.type === 'geo-location') {
			return SHARED_ITEM.TYPES.LOCATION
		} else if (message.messageParameters.object.type === 'deck-card') {
			return SHARED_ITEM.TYPES.DECK_CARD
		} else if (message.messageParameters.object.type === 'talk-poll') {
			return SHARED_ITEM.TYPES.POLL
		} else {
			return SHARED_ITEM.TYPES.OTHER
		}
	} else {
		const messageType = message.messageType || ''
		const mimetype = message.messageParameters.file?.mimetype || ''
		if (messageType === 'record-audio' || messageType === 'record-video') {
			return SHARED_ITEM.TYPES.RECORDING
		} else if (messageType === 'voice-message') {
			return SHARED_ITEM.TYPES.VOICE
		} else if (mimetype.startsWith('audio/')) {
			return SHARED_ITEM.TYPES.AUDIO
		} else if (mimetype.startsWith('image/') || mimetype.startsWith('video/')) {
			return SHARED_ITEM.TYPES.MEDIA
		} else {
			return SHARED_ITEM.TYPES.FILE
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
	isOverviewLoaded: state => token => {
		return !!state.overviewLoaded[token]
	},

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
	async getSharedItems({ commit, state }, { token, type }) {
		if (!state.sharedItemsByConversationAndType[token]
			|| !state.sharedItemsByConversationAndType[token][type]) {
			console.error('Missing overview for shared items in ', token)
			return { hasMoreItems: false, messages: [] }
		}

		const limit = 20
		const lastKnownMessageId = Math.min.apply(Math, Object.keys(state.sharedItemsByConversationAndType[token][type]))
		try {
			const response = await getSharedItems(token, type, lastKnownMessageId, limit)
			const messages = response.data.ocs.data
			const hasMoreItems = messages.length >= limit
			// loop over the response elements and add them to the store
			for (const message in messages) {

				commit('addSharedItemMessage', {
					token,
					type,
					message: messages[message],
				})
			}
			return { hasMoreItems, messages }
		} catch (error) {
			console.error(error)
			return { hasMoreItems: false, messages: [] }
		}
	},

	async getSharedItemsOverview({ commit, state }, { token }) {
		if (state.overviewLoaded[token]) {
			return
		}

		try {
			const response = await getSharedItemsOverview(token, 7)
			commit('addSharedItemsOverview', {
				token,
				data: response.data.ocs.data,
			})
		} catch (error) {
			console.error(error)
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

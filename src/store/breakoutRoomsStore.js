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
import {
	configureBreakoutRooms,
	deleteBreakoutRooms,
	getBreakoutRooms,
	startBreakoutRooms,
	stopBreakoutRooms,
	broadcastMessageToBreakoutRooms,
	getBreakoutRoomsParticipants,
	requestAssistance,
	resetRequestAssistance,
} from '../services/breakoutRoomsService.js'
import { showError } from '@nextcloud/dialogs'
import { set } from 'vue'

const state = {
	breakoutRoomsReferences: {},
}

const getters = {
	breakoutRoomsReferences: (state) => (token) => {
		if (!state.breakoutRoomsReferences?.[token]) {
			return []
		}
		return state.breakoutRoomsReferences?.[token]
	},

	hasBreakoutRooms: (state, getters, rootState) => (token) => {
		if (!state.breakoutRoomsReferences?.[token]) {
			return false
		}
		return !!state.breakoutRoomsReferences?.[token]
			.every(breakoutRoomToken => rootState.conversationsStore.conversations?.[breakoutRoomToken])
	},
}

const mutations = {
	addBreakoutRoomsReferences(state, { token, breakoutRoomsReferences }) {
		if (!state.breakoutRoomsReferences[token]) {
			set(state.breakoutRoomsReferences, token, [])
		}
		state.breakoutRoomsReferences[token] = breakoutRoomsReferences
	},

	removeReferences(state, token) {
		set(state.breakoutRoomsReferences, token, [])
	},
}

const actions = {
	async configureBreakoutRoomsAction(context, { token, mode, amount, attendeeMap }) {
		try {
			const response = await configureBreakoutRooms(token, mode, amount, attendeeMap)
			const breakoutRoomsReferences = []

			// Add breakout rooms and conversations to the conversations store
			response.data.ocs.data.forEach(conversation => {
				context.commit('addConversation', conversation)
				if (conversation.token !== token) {
					breakoutRoomsReferences.push(conversation.token)
				}
			})

			// Add breakout rooms references to this store
			context.commit('addBreakoutRoomsReferences', {
				token,
				breakoutRoomsReferences,
			})
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while creating breakout rooms'))
		}
	},

	async deleteBreakoutRoomsAction(context, { token }) {
		try {
			const response = await deleteBreakoutRooms(token)
			const conversation = response.data.ocs.data
			// Update the parent conversation with the new configuration
			context.commit('addConversation', conversation)
			// Remove references from this store
			context.commit('removeReferences', token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while deleting breakout rooms'))
		}
	},

	async getBreakoutRoomsAction(context, { token }) {
		try {
			const response = await getBreakoutRooms(token)
			context.commit('addBreakoutRoomsReferences', {
				token,
				breakoutRoomsReferences: response.data.ocs.data.map(conversation => conversation.token),
			})
		} catch (error) {
			console.error(error)
		}
	},

	async startBreakoutRoomsAction(context, token) {
		try {
			const response = await startBreakoutRooms(token)
			// Add breakout rooms and conversations to the conversations store
			response.data.ocs.data.forEach(conversation => {
				context.commit('addConversation', conversation)
			})
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while starting breakout rooms'))
		}
	},

	async stopBreakoutRoomsAction(context, token) {
		try {
			const response = await stopBreakoutRooms(token)
			// Add breakout rooms and conversations to the conversations store
			response.data.ocs.data.forEach(conversation => {
				context.commit('addConversation', conversation)
			})
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while stopping breakout rooms'))
		}
	},

	async broadcastMessageToBreakoutRoomsAction(context, { temporaryMessage }) {
		try {
			await broadcastMessageToBreakoutRooms(temporaryMessage.message, temporaryMessage.token)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while sending a message to the breakout rooms'))
		}
	},

	async getBreakoutRoomsParticipantsAction(context, { token }) {
		try {
			const response = await getBreakoutRoomsParticipants(token)
			response.data.ocs.data.forEach(participant => {
				context.dispatch('addParticipant', {
					token: participant.roomToken,
					participant,
				})
			})

		} catch (error) {
			console.error(error)
		}
	},

	async requestAssistanceAction(context, { token }) {
		try {
			const response = await requestAssistance(token)
			// Add the updated breakout room to the conversations store
			context.commit('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while requesting assistance'))
		}
	},

	async resetRequestAssistanceAction(context, { token }) {
		try {
			const response = await resetRequestAssistance(token)
			// Add the updated breakout room to the conversations store
			context.commit('addConversation', response.data.ocs.data)
		} catch (error) {
			console.error(error)
			showError(t('spreed', 'An error occurred while resetting the request for assistance'))
		}
	},
}

export default { state, getters, mutations, actions }

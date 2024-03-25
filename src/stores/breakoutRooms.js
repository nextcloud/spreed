/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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

import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import {
	configureBreakoutRooms,
	deleteBreakoutRooms,
	getBreakoutRooms,
	startBreakoutRooms,
	stopBreakoutRooms,
	broadcastMessageToBreakoutRooms,
	fetchBreakoutRoomsParticipants,
	requestAssistance,
	dismissRequestAssistance,
	reorganizeAttendees,
	switchToBreakoutRoom,
} from '../services/breakoutRoomsService.js'
import store from '../store/index.js'

export const useBreakoutRoomsStore = defineStore('breakoutRooms', {
	state: () => ({
		rooms: {},
	}),

	getters: {
		breakoutRooms: (state) => (token) => {
			return Object.values(Object(state.rooms[token]))
		},

		getParentRoomToken: (state) => (token) => {
			for (const parentRoomToken in state.rooms) {
				if (state.rooms[parentRoomToken]?.[token] !== undefined) {
					return parentRoomToken
				}
			}
		},
	},

	actions: {
		/**
		 * The breakout rooms API return an array with mixed breakout and parent rooms, we want to update
		 * breakout rooms in this store and all conversations in conversationsStore.
		 *
		 * @param {string} token the parent room token;
		 * @param {Array<object>|object} conversationOrArray a single conversation or an array of conversations.
		 *
		 */
		processConversations(token, conversationOrArray) {
			const conversations = Array.isArray(conversationOrArray) ? conversationOrArray : [conversationOrArray]
			store.dispatch('patchConversations', { conversations })
		},

		/**
		 * Purges breakout rooms from both stores.
		 *
		 * @param {string} token the parent room token;
		 */
		purgeBreakoutRoomsStore(token) {
			for (const roomToken in this.rooms[token]) {
				store.dispatch('deleteConversation', roomToken)
			}
			Vue.delete(this.rooms, token)
		},

		/**
		 * Adds a breakout room to the store.
		 *
		 * @param {string} token the parent room token;
		 * @param {object} breakoutRoom the breakout room.
		 */
		addBreakoutRoom(token, breakoutRoom) {
			if (!this.rooms[token]) {
				Vue.set(this.rooms, token, {})
			}
			Vue.set(this.rooms[token], breakoutRoom.token, breakoutRoom)
		},

		/**
		 * Creates breakout rooms for specified conversation.
		 *
		 * @param {object} payload the action payload;
		 * @param {string} payload.token the parent room token;
		 * @param {string} payload.mode the mode of the breakout rooms;
		 * @param {number} payload.amount the amount of the breakout rooms to create;
		 * @param {string} payload.attendeeMap the stringified JSON object with attendee map.
		 */
		async configureBreakoutRooms({ token, mode, amount, attendeeMap }) {
			try {
				const response = await configureBreakoutRooms(token, mode, amount, attendeeMap)
				this.processConversations(token, response.data.ocs.data)

				// Get the participants of the breakout rooms
				await this.fetchBreakoutRoomsParticipants(token)

				// Open the sidebar and switch to the breakout rooms tab
				emit('spreed:select-active-sidebar-tab', 'breakout-rooms')
				store.dispatch('showSidebar')
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while creating breakout rooms'))
			}
		},

		/**
		 * Reassign participants to another breakout rooms.
		 *
		 * @param {object} payload the action payload;
		 * @param {string} payload.token the parent room token;
		 * @param {string} payload.attendeeMap the stringified JSON object with attendee map.
		 */
		async reorganizeAttendees({ token, attendeeMap }) {
			try {
				const response = await reorganizeAttendees(token, attendeeMap)
				this.processConversations(token, response.data.ocs.data)

				// Get the participants of the breakout rooms
				await this.fetchBreakoutRoomsParticipants(token)

			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while re-ordering the attendees'))
			}
		},

		/**
		 * Deletes configured breakout rooms for a given parent room token.
		 *
		 * @param {string} token the parent room token.
		 */
		async deleteBreakoutRooms(token) {
			try {
				const response = await deleteBreakoutRooms(token)
				// Update returned parent conversation
				this.processConversations(token, response.data.ocs.data)
				// Remove breakout rooms from this store
				this.purgeBreakoutRoomsStore(token)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while deleting breakout rooms'))
			}
		},

		/**
		 * Get configured breakout rooms for a given parent room token.
		 *
		 * @param {string} token the parent room token.
		 */
		async getBreakoutRooms(token) {
			try {
				const response = await getBreakoutRooms(token)
				this.processConversations(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * Start a breakout rooms session for a given parent room token.
		 *
		 * @param {string} token the parent room token.
		 */
		async startBreakoutRooms(token) {
			try {
				const response = await startBreakoutRooms(token)
				this.processConversations(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while starting breakout rooms'))
			}
		},

		/**
		 * Stop a breakout rooms session for a given parent room token.
		 *
		 * @param {string} token the parent room token.
		 */
		async stopBreakoutRooms(token) {
			try {
				const response = await stopBreakoutRooms(token)
				this.processConversations(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while stopping breakout rooms'))
			}
		},

		/**
		 * Send a message to all breakout rooms for a given parent room token.
		 *
		 * @param {object} payload the action payload;
		 * @param {string} payload.token the parent room token;
		 * @param {string} payload.message the message text.
		 */
		async broadcastMessageToBreakoutRooms({ token, message }) {
			try {
				await broadcastMessageToBreakoutRooms(token, message)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while sending a message to the breakout rooms'))
			}
		},

		/**
		 * Update participants in breakout rooms for a given token.
		 *
		 * @param {string} token the parent room token.
		 */
		async fetchBreakoutRoomsParticipants(token) {
			try {
				const response = await fetchBreakoutRoomsParticipants(token)
				const splittedParticipants = response.data.ocs.data.reduce((acc, participant) => {
					if (!acc[participant.roomToken]) {
						acc[participant.roomToken] = []
					}
					acc[participant.roomToken].push(participant)
					return acc
				}, {})

				Object.entries(splittedParticipants).forEach(([token, newParticipants]) => {
					store.dispatch('patchParticipants', { token, newParticipants, hasUserStatuses: false })
				})
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * Notify moderators when raise a hand in a breakout room with given token.
		 *
		 * @param {string} token the breakout room token.
		 */
		async requestAssistance(token) {
			try {
				const response = await requestAssistance(token)
				const parentToken = response.data.ocs.data.objectId
				this.processConversations(parentToken, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while requesting assistance'))
			}
		},

		/**
		 * Dismiss a notification about raised hand for a breakout room with given token.
		 *
		 * @param {string} token the breakout room token.
		 */
		async dismissRequestAssistance(token) {
			try {
				const response = await dismissRequestAssistance(token)
				const parentToken = response.data.ocs.data.objectId
				this.processConversations(parentToken, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while resetting the request for assistance'))
			}
		},

		/**
		 * Switch between breakout rooms if participant is allowed to choose the room freely
		 *
		 * @param {object} payload the action payload;
		 * @param {string} payload.token the parent room token;
		 * @param {string} payload.target the breakout room token.
		 */
		async switchToBreakoutRoom({ token, target }) {
			try {
				const response = await switchToBreakoutRoom(token, target)
				this.processConversations(token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while joining breakout room'))
			}
		},
	}
})

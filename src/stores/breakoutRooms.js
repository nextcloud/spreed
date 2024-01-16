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
	getBreakoutRoomsParticipants,
	requestAssistance,
	resetRequestAssistance,
	reorganizeAttendees,
	switchToBreakoutRoom,
} from '../services/breakoutRoomsService.js'
import store from '../store/index.js'

export const useBreakoutRoomsStore = defineStore('breakoutRooms', {
	state: () => ({
		breakoutRooms: {},
	}),

	getters: {
		breakoutRooms: (state) => (token) => {
			return state.breakoutRooms[token] ?? []
		},

		getParentRoomToken: (state) => (token) => {
			for (const parentRoomToken in state.breakoutRooms) {
				if (state.breakoutRooms[parentRoomToken].find(breakoutRoom => breakoutRoom.token === token)) {
					return parentRoomToken
				}
			}
		},
	},

	actions: {
		/**
		 * The breakout rooms api return an array with mixed breakout rooms and "parent" conversations, we want to add the
		 * breakout rooms to this store and update the parent conversations in the conversations store.
		 *
		 * @param {Array} conversationsList the array of mixed breakout rooms and "parent" conversation
		 * @param {string }parentRoomToken the parent room token;
		 */
		processConversations(conversationsList, parentRoomToken) {
			conversationsList.forEach(conversation => {
				if (conversation.token === parentRoomToken) {
					store.commit('addConversation', conversation)
				} else {
					this.addBreakoutRoom({
						parentRoomToken,
						breakoutRoom: conversation,
					})
				}
			})
		},

		/**
		 * Adds a breakout room to the store.
		 *
		 * @param {object} payload the action payload;
		 * @param {string} payload.parentRoomToken the parent room token;
		 * @param {object} payload.breakoutRoom the breakout room;
		 */
		addBreakoutRoom({ parentRoomToken, breakoutRoom }) {
			if (!this.breakoutRooms[parentRoomToken]) {
				Vue.set(this.breakoutRooms, parentRoomToken, [])
			}
			// The breakout room to be added is first removed if it exists already.
			this.breakoutRooms[parentRoomToken] = this.breakoutRooms[parentRoomToken].filter(current => current.token !== breakoutRoom.token)
			Vue.set(this.breakoutRooms, parentRoomToken, [...this.breakoutRooms[parentRoomToken], breakoutRoom])
		},

		/**
		 * Deletes all breakout rooms for a given parent room token.
		 *
		 * @param {string} parentRoomToken the parent room token;
		 */
		deleteBreakoutRooms(parentRoomToken) {
			Vue.delete(this.breakoutRooms, parentRoomToken)
		},

		async configureBreakoutRoomsAction({ token, mode, amount, attendeeMap }) {
			try {
				const response = await configureBreakoutRooms(token, mode, amount, attendeeMap)
				// Get the participants of the breakout rooms
				this.getBreakoutRoomsParticipantsAction({ token })

				this.processConversations(response.data.ocs.data, token)

				// Open the sidebar and switch to the breakout rooms tab
				emit('spreed:select-active-sidebar-tab', 'breakout-rooms')
				store.dispatch('showSidebar')
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while creating breakout rooms'))
			}
		},

		async reorganizeAttendeesAction({ token, attendeeMap }) {
			try {
				const response = await reorganizeAttendees(token, attendeeMap)
				// Get the participants of the breakout rooms
				this.getBreakoutRoomsParticipantsAction({ token })

				this.processConversations(response.data.ocs.data, token)

			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while re-ordering the attendees'))
			}
		},

		async deleteBreakoutRoomsAction({ token }) {
			try {
				const response = await deleteBreakoutRooms(token)
				const conversation = response.data.ocs.data

				// Add the updated parent conversation to the conversations store
				store.commit('addConversation', conversation)

				// Remove breakout rooms from this store
				this.deleteBreakoutRooms(token)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while deleting breakout rooms'))
			}
		},

		async getBreakoutRoomsAction({ token }) {
			try {
				const response = await getBreakoutRooms(token)

				this.processConversations(response.data.ocs.data, token)

			} catch (error) {
				console.error(error)
			}
		},

		async startBreakoutRoomsAction(token) {
			try {
				const response = await startBreakoutRooms(token)

				this.processConversations(response.data.ocs.data, token)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while starting breakout rooms'))
			}
		},

		async stopBreakoutRoomsAction(token) {
			try {
				const response = await stopBreakoutRooms(token)

				this.processConversations(response.data.ocs.data, token)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while stopping breakout rooms'))
			}
		},

		async broadcastMessageToBreakoutRoomsAction({ token, message }) {
			try {
				await broadcastMessageToBreakoutRooms(token, message)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while sending a message to the breakout rooms'))
			}
		},

		async getBreakoutRoomsParticipantsAction({ token }) {
			try {
				const response = await getBreakoutRoomsParticipants(token)
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

		async requestAssistanceAction({ token }) {
			try {
				const response = await requestAssistance(token)
				// Add the updated parent conversation to the conversations store
				store.commit('addConversation', response.data.ocs.data)
				this.addBreakoutRoom({
					parentRoomToken: response.data.ocs.data.objectId,
					breakoutRoom: response.data.ocs.data,
				})
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while requesting assistance'))
			}
		},

		async resetRequestAssistanceAction({ token }) {
			try {
				const response = await resetRequestAssistance(token)
				// Add the updated parent conversation to the conversations store
				store.commit('addConversation', response.data.ocs.data)
				this.addBreakoutRoom({
					parentRoomToken: response.data.ocs.data.objectId,
					breakoutRoom: response.data.ocs.data,
				})
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while resetting the request for assistance'))
			}
		},

		async switchToBreakoutRoomAction({ token, target }) {
			try {
				const response = await switchToBreakoutRoom(token, target)

				// A single breakout room (the target one) is returned, so it needs
				// to be wrapper in an array.
				this.processConversations([response.data.ocs.data], token)
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while joining breakout room'))
			}
		},
	}
})

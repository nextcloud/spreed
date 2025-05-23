/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { showError } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import { useSidebarStore } from './sidebar.ts'
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
} from '../services/breakoutRoomsService.ts'
import store from '../store/index.js'
import type {
	Conversation,
	Participant,
	BreakoutRoom,
	broadcastChatMessageParams,
	configureBreakoutRoomsParams,
	reorganizeAttendeesParams,
	switchToBreakoutRoomParams,
} from '../types/index.ts'

type Payload<T> = T & { token: string }
type State = {
	rooms: Record<string, Record<string, BreakoutRoom>>
}
export const useBreakoutRoomsStore = defineStore('breakoutRooms', {
	state: (): State => ({
		rooms: {},
	}),

	getters: {
		breakoutRooms: (state) => (token: string): BreakoutRoom[] => {
			const roomsArray: BreakoutRoom[] = Object.values(Object(state.rooms[token]))
			return roomsArray.sort((roomA, roomB) => roomA.id - roomB.id)
		},

		getParentRoomToken: (state) => (token: string): string | undefined => {
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
		 * @param token the parent room token;
		 * @param conversationOrArray a single conversation or an array of conversations.
		 *
		 */
		processConversations(token: string, conversationOrArray: Conversation | Conversation[]) {
			const conversations = Array.isArray(conversationOrArray) ? conversationOrArray : [conversationOrArray]
			store.dispatch('patchConversations', { conversations })
		},

		/**
		 * Purges breakout rooms from both stores.
		 *
		 * @param token the parent room token;
		 */
		purgeBreakoutRoomsStore(token: string) {
			for (const roomToken in this.rooms[token]) {
				store.dispatch('deleteConversation', roomToken)
			}
			Vue.delete(this.rooms, token)
		},

		/**
		 * Adds a breakout room to the store.
		 *
		 * @param token the parent room token;
		 * @param breakoutRoom the breakout room.
		 */
		addBreakoutRoom(token: string, breakoutRoom: BreakoutRoom) {
			if (!this.rooms[token]) {
				Vue.set(this.rooms, token, {})
			}
			Vue.set(this.rooms[token], breakoutRoom.token, breakoutRoom)
		},

		/**
		 * Creates breakout rooms for specified conversation.
		 *
		 * @param payload the action payload;
		 * @param payload.token the parent room token;
		 * @param payload.mode the mode of the breakout rooms;
		 * @param payload.amount the amount of the breakout rooms to create;
		 * @param payload.attendeeMap the stringified JSON object with attendee map.
		 */
		async configureBreakoutRooms({ token, mode, amount, attendeeMap }: Payload<configureBreakoutRoomsParams>) {
			try {
				const response = await configureBreakoutRooms(token, mode, amount, attendeeMap)
				this.processConversations(token, response.data.ocs.data)

				// Get the participants of the breakout rooms
				await this.fetchBreakoutRoomsParticipants(token)

				// Open the sidebar and switch to the breakout rooms tab
				const sidebarStore = useSidebarStore()
				sidebarStore.showSidebar({ activeTab: 'breakout-rooms' })
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'An error occurred while creating breakout rooms'))
			}
		},

		/**
		 * Reassign participants to another breakout rooms.
		 *
		 * @param payload the action payload;
		 * @param payload.token the parent room token;
		 * @param payload.attendeeMap the stringified JSON object with attendee map.
		 */
		async reorganizeAttendees({ token, attendeeMap }: Payload<reorganizeAttendeesParams>) {
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
		 * @param token the parent room token.
		 */
		async deleteBreakoutRooms(token: string) {
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
		 * @param token the parent room token.
		 */
		async getBreakoutRooms(token: string) {
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
		 * @param token the parent room token.
		 */
		async startBreakoutRooms(token: string) {
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
		 * @param token the parent room token.
		 */
		async stopBreakoutRooms(token: string) {
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
		 * @param payload the action payload;
		 * @param payload.token the parent room token;
		 * @param payload.message the message text.
		 */
		async broadcastMessageToBreakoutRooms({ token, message }: Payload<broadcastChatMessageParams>) {
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
		 * @param token the parent room token.
		 */
		async fetchBreakoutRoomsParticipants(token: string) {
			try {
				const response = await fetchBreakoutRoomsParticipants(token)
				const splittedParticipants = response.data.ocs.data.reduce((acc: Record<string, Participant[]>, participant) => {
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
		 * @param token the breakout room token.
		 */
		async requestAssistance(token: string) {
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
		 * @param token the breakout room token.
		 */
		async dismissRequestAssistance(token: string) {
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
		 * @param payload the action payload;
		 * @param payload.token the parent room token;
		 * @param payload.target the breakout room token.
		 */
		async switchToBreakoutRoom({ token, target }: Payload<switchToBreakoutRoomParams>) {
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

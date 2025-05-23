/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import type { AxiosError } from '@nextcloud/axios'
import { generateUrl, getBaseUrl } from '@nextcloud/router'

import { CONVERSATION } from '../constants.ts'
import {
	initializeCalDavClient,
	getPersonalCalendars,
	getDefaultCalendarUri,
	convertUrlToUri,
} from '../services/CalDavClient.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getUserProfile } from '../services/coreService.ts'
import {
	getMutualEvents,
	getUpcomingEvents,
	getUserAbsence,
	scheduleMeeting,
} from '../services/groupwareService.ts'
import type {
	ApiErrorResponse,
	Conversation,
	DavCalendar,
	DashboardEvent,
	OutOfOfficeResult,
	UpcomingEvent,
	UserProfileData,
	scheduleMeetingParams,
} from '../types/index.ts'

type State = {
	absence: Record<string, OutOfOfficeResult>
	calendars: Record<string, DavCalendar & { uri: string }>
	defaultCalendarUri: string | null
	upcomingEvents: Record<string, UpcomingEvent[]>
	mutualEvents: Record<string, DashboardEvent[]>
	supportProfileInfo: boolean
	profileInfo: Record<string, UserProfileData>
}

const supportsMutualEvents = hasTalkFeature('local', 'mutual-calendar-events')

export const useGroupwareStore = defineStore('groupware', {
	state: (): State => ({
		absence: {},
		calendars: {},
		defaultCalendarUri: null,
		upcomingEvents: {},
		mutualEvents: {},
		supportProfileInfo: true,
		profileInfo: {},
	}),

	getters: {
		getAllEvents: (state) => (token: string) => {
			return state.upcomingEvents[token] ?? []
		},
		getNextEvent: (state) => (token: string) => {
			return state.upcomingEvents[token]?.[0]
		},
		writeableCalendars: (state) => {
			return Object.values(state.calendars).filter((calendar) => {
				return calendar.isWriteable() && calendar.components.includes('VEVENT')
			})
		},
	},

	actions: {
		/**
		 * Fetch an absence status for user and save to store
		 * @param payload action payload
		 * @param payload.token The conversation token
		 * @param payload.userId The id of user
		 */
		async getUserAbsence({ token, userId }: { token: string, userId: string }) {
			try {
				const response = await getUserAbsence(userId)
				Vue.set(this.absence, token, response.data.ocs.data)
				return this.absence[token]
			} catch (error) {
				if ((error as AxiosError)?.response?.status === 404) {
					Vue.set(this.absence, token, null)
					return null
				}
				console.error(error)
			}
		},

		/**
		 * Fetch upcoming events for conversation and save to store
		 * @param token The conversation token
		 */
		async getUpcomingEvents(token: string) {
			const location = generateUrl('call/{token}', { token }, { baseURL: getBaseUrl() })
			try {
				const response = await getUpcomingEvents(location)
				Vue.set(this.upcomingEvents, token, response.data.ocs.data.events)
			} catch (error) {
				console.error(error)
			}
		},

		async getDefaultCalendarUri() {
			try {
				await initializeCalDavClient()
				Vue.set(this, 'defaultCalendarUri', getDefaultCalendarUri())
			} catch (error) {
				console.error(error)
			}
		},

		async getPersonalCalendars() {
			try {
				await initializeCalDavClient()
				const calendars = await getPersonalCalendars()
				calendars.forEach((calendar) => {
					const calendarWithUri = Object.assign(calendar, { uri: convertUrlToUri(calendar.url) })
					Vue.set(this.calendars, calendarWithUri.uri, calendarWithUri)
				})
			} catch (error) {
				console.error(error)
			}
		},

		async scheduleMeeting(token: string, payload: scheduleMeetingParams) {
			await scheduleMeeting(token, payload)
			// Fetch updated list of events for this conversation
			await this.getUpcomingEvents(token)
		},

		/**
		 * Drop an absence status from the store
		 * @param token The conversation token
		 */
		removeUserAbsence(token: string) {
			if (this.absence[token]) {
				Vue.delete(this.absence, token)
			}
		},

		/**
		 * Drop upcoming events from the store
		 * @param token The conversation token
		 */
		removeUpcomingEvents(token: string) {
			if (this.upcomingEvents[token]) {
				Vue.delete(this.upcomingEvents, token)
			}
		},

		/**
		 * Request and parse profile information
		 * @param conversation The conversation object
		 */
		async getUserProfileInformation(conversation: Conversation) {
			if (!this.supportProfileInfo || !conversation.name
				|| conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE) {
				Vue.delete(this.profileInfo, conversation.token)
				return
			}

			// FIXME cache results for 6/24 hours and do not fetch again
			try {
				const response = await getUserProfile(conversation.name)
				Vue.set(this.profileInfo, conversation.token, response.data.ocs.data)
			} catch (error) {
				if ((error as ApiErrorResponse)?.response?.status === 405) {
					// Method does not exist on current server version
					// Skip further requests
					this.supportProfileInfo = false
				} else {
					console.error(error)
				}
			}
		},

		/**
		 * Request and parse profile information
		 * @param conversation The conversation object
		 */
		async getUserMutualEvents(conversation: Conversation) {
			if (!supportsMutualEvents || !conversation.token
				|| conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE) {
				return
			}

			// FIXME cache results for 6/24 hours and do not fetch again
			try {
				const response = await getMutualEvents(conversation.token)
				Vue.set(this.mutualEvents, conversation.token, response.data.ocs.data)
			} catch (error) {
				console.error(error)
			}
		},

		/**
		 * Clears store for a deleted conversation
		 * @param token The conversation token
		 */
		purgeGroupwareStore(token: string) {
			this.removeUserAbsence(token)
			this.removeUpcomingEvents(token)
		},
	},
})

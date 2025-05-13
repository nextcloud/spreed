/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getDashboardEventRooms } from '../services/dashboardService.ts'
import { getUpcomingReminders } from '../services/remindersService.js'
import type { DashboardEventRoom, UpcomingReminder } from '../types/index.ts'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')

type State = {
	eventrooms: DashboardEventRoom[],
	upcomingReminders: UpcomingReminder[],
	eventRoomsInitialised: boolean
	upcomingRemindersInitialised: boolean,
}
export const useTalkDashboardStore = defineStore('talkdashboard', {
	state: (): State => ({
		eventrooms: [],
		upcomingReminders: [],
		eventRoomsInitialised: false,
		upcomingRemindersInitialised: false,
	}),

	actions: {
		async fetchDashboardEventRooms() {
			try {
				const response = await getDashboardEventRooms()
				Vue.set(this, 'eventrooms', response.data.ocs.data)
				this.eventRoomsInitialised = true
			} catch (error) {
				console.error('Error fetching dashboard event rooms:', error)
				throw error
			}
		},

		async fetchUpcomingReminders() {
			try {
				if (!supportsUpcomingReminders) {
					return []
				}
				const response = await getUpcomingReminders()
				Vue.set(this, 'upcomingReminders', response.data.ocs.data)
				this.upcomingRemindersInitialised = true
			} catch (error) {
				console.error('Error fetching upcoming reminders:', error)
				throw error
			}
		}
	},
})

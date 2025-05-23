/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DashboardEventRoom, UpcomingReminder } from '../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import Vue from 'vue'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getDashboardEventRooms } from '../services/dashboardService.ts'
import { getUpcomingReminders, removeMessageReminder } from '../services/remindersService.js'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')

type State = {
	eventRooms: DashboardEventRoom[]
	upcomingReminders: UpcomingReminder[]
	eventRoomsInitialised: boolean
	upcomingRemindersInitialised: boolean
}
export const useDashboardStore = defineStore('dashboard', {
	state: (): State => ({
		eventRooms: [],
		upcomingReminders: [],
		eventRoomsInitialised: false,
		upcomingRemindersInitialised: false,
	}),

	actions: {
		async fetchDashboardEventRooms() {
			try {
				const response = await getDashboardEventRooms()
				Vue.set(this, 'eventRooms', response.data.ocs.data)
				this.eventRoomsInitialised = true
			} catch (error) {
				console.error('Error fetching dashboard event rooms:', error)
				showError(t('spreed', 'Error fetching upcoming events'))
			}
		},

		async fetchUpcomingReminders() {
			try {
				if (!supportsUpcomingReminders) {
					return
				}
				const response = await getUpcomingReminders()
				Vue.set(this, 'upcomingReminders', response.data.ocs.data)
				this.upcomingRemindersInitialised = true
			} catch (error) {
				console.error('Error fetching upcoming reminders:', error)
				showError(t('spreed', 'Error fetching upcoming reminders'))
			}
		},

		async removeReminder(token: string, messageId: number) {
			try {
				await removeMessageReminder(token, messageId)
				Vue.set(this, 'upcomingReminders', this.upcomingReminders.filter((reminder) => {
					return reminder.messageId !== messageId
				}))
				showSuccess(t('spreed', 'A reminder was successfully removed'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Error occurred when removing a reminder'))
			}
		},
	},
})

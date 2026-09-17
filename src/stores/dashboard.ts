/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { DashboardEventRoom, DashboardReminder, NotificationsNotification, UpcomingReminder } from '../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { defineStore } from 'pinia'
import { ATTENDEE } from '../constants.ts'
import { hasNotificationsFeature, hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { getDashboardEventRooms } from '../services/dashboardService.ts'
import { dismissNotification, getReminderNotifications } from '../services/notificationsService.ts'
import { getUpcomingReminders, removeMessageReminder } from '../services/remindersService.js'
import { convertToUnix } from '../utils/formattedTime.ts'

const supportsUpcomingReminders = hasTalkFeature('local', 'upcoming-reminders')
// Reminders that already triggered are shown instead of upcoming ones, which the user delayed on purpose
export const supportsTriggeredReminders = hasNotificationsFeature('list-filter')

/**
 * Convert a reminder notification into the shape the upcoming reminders endpoint returns.
 *
 * The object id of a parsed Talk notification is `token/messageId` (and `token/messageId/threadId`
 * for threads), except in sensitive conversations, where neither the message id nor a preview of
 * the message is handed to the client.
 *
 * @param notification A notification with the object type `reminder`
 */
function parseReminderNotification(notification: NotificationsNotification): DashboardReminder {
	const [roomToken, messageId] = notification.object_id.split('/')
	const parameters = Array.isArray(notification.subjectRichParameters) ? {} : notification.subjectRichParameters
	const actor = parameters.user ?? parameters.guest

	return {
		notificationId: notification.notification_id,
		roomToken,
		messageId: Number(messageId ?? 0),
		actorId: actor?.id ?? '',
		actorType: actor?.type === 'guest' ? ATTENDEE.ACTOR_TYPE.GUESTS : ATTENDEE.ACTOR_TYPE.USERS,
		actorDisplayName: actor?.name ?? notification.subject,
		message: notification.messageRich,
		messageParameters: Array.isArray(notification.messageRichParameters) ? {} : notification.messageRichParameters,
		reminderTimestamp: convertToUnix(new Date(notification.datetime)),
	}
}

type State = {
	eventRooms: DashboardEventRoom[]
	reminders: DashboardReminder[]
	eventRoomsInitialised: boolean
	remindersInitialised: boolean
}
export const useDashboardStore = defineStore('dashboard', {
	state: (): State => ({
		eventRooms: [],
		reminders: [],
		eventRoomsInitialised: false,
		remindersInitialised: false,
	}),

	actions: {
		async fetchDashboardEventRooms() {
			try {
				const response = await getDashboardEventRooms()
				this.eventRooms = response.data.ocs.data
				this.eventRoomsInitialised = true
			} catch (error) {
				console.error('Error fetching dashboard event rooms:', error)
				showError(t('spreed', 'Error fetching upcoming events'))
			}
		},

		async fetchReminders() {
			if (supportsTriggeredReminders) {
				await this.fetchTriggeredReminders()
			} else {
				await this.fetchUpcomingReminders()
			}
		},

		async fetchTriggeredReminders() {
			try {
				const response = await getReminderNotifications()
				// The endpoint answers 204 without a body when no app registered a notifier
				const notifications = response.data ? response.data.ocs.data : []
				this.reminders = notifications.map(parseReminderNotification)
				this.remindersInitialised = true
			} catch (error) {
				console.error('Error fetching reminder notifications:', error)
				showError(t('spreed', 'Error fetching reminders'))
			}
		},

		async fetchUpcomingReminders() {
			try {
				if (!supportsUpcomingReminders) {
					return
				}
				const response = await getUpcomingReminders()
				this.reminders = response.data.ocs.data.map((reminder: UpcomingReminder) => ({ ...reminder, notificationId: null }))
				this.remindersInitialised = true
			} catch (error) {
				console.error('Error fetching upcoming reminders:', error)
				showError(t('spreed', 'Error fetching upcoming reminders'))
			}
		},

		/**
		 * Clear a reminder shown on the dashboard. A reminder that already triggered is not stored
		 * as a reminder anymore, so its notification is dismissed instead.
		 *
		 * @param token The conversation token
		 * @param messageId The id of the message the reminder is set on
		 * @param notificationId The id of the notification, when the reminder already triggered
		 */
		async removeReminder(token: string, messageId: number, notificationId: number | null = null) {
			try {
				if (notificationId !== null) {
					await dismissNotification(notificationId)
					this.reminders = this.reminders.filter((reminder) => {
						return reminder.notificationId !== notificationId
					})
				} else {
					await removeMessageReminder(token, messageId)
					this.reminders = this.reminders.filter((reminder) => {
						return reminder.messageId !== messageId
					})
				}
				showSuccess(t('spreed', 'A reminder was successfully removed'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Error occurred when removing a reminder'))
			}
		},
	},
})

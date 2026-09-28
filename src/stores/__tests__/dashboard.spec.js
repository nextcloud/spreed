/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ATTENDEE } from '../../constants.ts'
import { generateOCSErrorResponse, generateOCSResponse } from '../../test-helpers.js'

vi.mock('../../services/CapabilitiesManager.ts', () => ({
	hasNotificationsFeature: vi.fn(),
	hasTalkFeature: vi.fn(),
}))
vi.mock('../../services/notificationsService.ts', () => ({
	dismissNotification: vi.fn(),
	getReminderNotifications: vi.fn(),
}))
vi.mock('../../services/remindersService.js', () => ({
	getUpcomingReminders: vi.fn(),
	removeMessageReminder: vi.fn(),
}))
vi.mock('../../services/dashboardService.ts', () => ({
	getDashboardEventRooms: vi.fn(),
}))

/**
 * Load a fresh dashboard store, as the supported reminder source is read from the capabilities
 * once when the module is evaluated.
 *
 * @param {boolean} supportsListFilter Whether the notifications app can filter by app and object
 */
async function setupStore(supportsListFilter) {
	vi.resetModules()
	const { hasNotificationsFeature, hasTalkFeature } = await import('../../services/CapabilitiesManager.ts')
	hasTalkFeature.mockReturnValue(true)
	hasNotificationsFeature.mockReturnValue(supportsListFilter)

	const notificationsService = await import('../../services/notificationsService.ts')
	const remindersService = await import('../../services/remindersService.js')
	const { useDashboardStore } = await import('../dashboard.ts')

	setActivePinia(createPinia())
	return { store: useDashboardStore(), notificationsService, remindersService }
}

describe('dashboardStore', () => {
	const reminderNotification = {
		notification_id: 9,
		app: 'spreed',
		user: 'remindertester',
		datetime: '2026-09-03T15:39:03+00:00',
		object_type: 'reminder',
		object_id: 'u8u7if2f/3',
		subject: 'Reminder: Other Person in private conversation',
		message: 'Hello there, remember this one',
		link: 'http://localhost/index.php/call/u8u7if2f#message_3',
		subjectRichParameters: {
			user: { type: 'user', id: 'reminderother', name: 'Other Person' },
			call: { type: 'call', id: '1', name: 'Other Person' },
		},
		messageRich: 'Hello there, remember this one',
		messageRichParameters: [],
	}

	const upcomingReminder = {
		messageId: 3,
		roomToken: 'u8u7if2f',
		actorId: 'reminderother',
		actorType: ATTENDEE.ACTOR_TYPE.USERS,
		actorDisplayName: 'Other Person',
		message: 'Hello there, remember this one',
		messageParameters: {},
		reminderTimestamp: 1788449943,
	}

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		vi.clearAllMocks()
	})

	describe('fetching reminders', () => {
		it('parses triggered reminders out of the notifications of the user', async () => {
			// Arrange
			const { store, notificationsService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce(generateOCSResponse({ payload: [reminderNotification] }))

			// Act
			await store.fetchReminders()

			// Assert
			expect(notificationsService.getReminderNotifications).toHaveBeenCalled()
			expect(store.remindersInitialised).toBe(true)
			expect(store.reminders).toEqual([{
				notificationId: 9,
				roomToken: 'u8u7if2f',
				messageId: 3,
				actorId: 'reminderother',
				actorType: ATTENDEE.ACTOR_TYPE.USERS,
				actorDisplayName: 'Other Person',
				message: 'Hello there, remember this one',
				messageParameters: {},
				reminderTimestamp: 1788449943,
			}])
		})

		it('reads the message id and thread id out of the object id', async () => {
			// Arrange
			const { store, notificationsService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce(generateOCSResponse({ payload: [{ ...reminderNotification, object_id: 'u8u7if2f/3/2' }] }))

			// Act
			await store.fetchReminders()

			// Assert
			expect(store.reminders[0].roomToken).toBe('u8u7if2f')
			expect(store.reminders[0].messageId).toBe(3)
		})

		it('falls back to the subject when a sensitive conversation hides the message', async () => {
			// Arrange
			const { store, notificationsService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce(generateOCSResponse({
				payload: [{
					...reminderNotification,
					object_id: 'u8u7if2f',
					subject: 'Reminder in a private conversation',
					subjectRichParameters: [],
					messageRich: '',
					messageRichParameters: [],
				}],
			}))

			// Act
			await store.fetchReminders()

			// Assert
			expect(store.reminders[0].messageId).toBe(0)
			expect(store.reminders[0].actorDisplayName).toBe('Reminder in a private conversation')
			expect(store.reminders[0].messageParameters).toEqual({})
		})

		it('shows nothing when no app registered a notifier and the endpoint answers 204', async () => {
			// Arrange
			const { store, notificationsService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce({ status: 204, data: '' })

			// Act
			await store.fetchReminders()

			// Assert
			expect(store.reminders).toEqual([])
			expect(store.remindersInitialised).toBe(true)
		})

		it('falls back to upcoming reminders without the notifications capability', async () => {
			// Arrange
			const { store, notificationsService, remindersService } = await setupStore(false)
			remindersService.getUpcomingReminders.mockResolvedValueOnce(generateOCSResponse({ payload: [upcomingReminder] }))

			// Act
			await store.fetchReminders()

			// Assert
			expect(notificationsService.getReminderNotifications).not.toHaveBeenCalled()
			expect(store.reminders).toEqual([{ ...upcomingReminder, notificationId: null }])
		})
	})

	describe('removing reminders', () => {
		it('dismisses the notification of a reminder that already triggered', async () => {
			// Arrange
			const { store, notificationsService, remindersService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce(generateOCSResponse({ payload: [reminderNotification] }))
			await store.fetchReminders()

			// Act
			await store.removeReminder('u8u7if2f', 3, 9)

			// Assert
			expect(notificationsService.dismissNotification).toHaveBeenCalledWith(9)
			expect(remindersService.removeMessageReminder).not.toHaveBeenCalled()
			expect(store.reminders).toEqual([])
		})

		it('deletes the reminder itself when it did not trigger yet', async () => {
			// Arrange
			const { store, notificationsService, remindersService } = await setupStore(false)
			remindersService.getUpcomingReminders.mockResolvedValueOnce(generateOCSResponse({ payload: [upcomingReminder] }))
			await store.fetchReminders()

			// Act
			await store.removeReminder('u8u7if2f', 3)

			// Assert
			expect(remindersService.removeMessageReminder).toHaveBeenCalledWith('u8u7if2f', 3)
			expect(notificationsService.dismissNotification).not.toHaveBeenCalled()
			expect(store.reminders).toEqual([])
		})

		it('keeps the list untouched when dismissing fails', async () => {
			// Arrange
			const { store, notificationsService } = await setupStore(true)
			notificationsService.getReminderNotifications.mockResolvedValueOnce(generateOCSResponse({ payload: [reminderNotification] }))
			await store.fetchReminders()
			const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {})
			notificationsService.dismissNotification.mockRejectedValueOnce(generateOCSErrorResponse({ payload: null, status: 404 }))

			// Act
			await store.removeReminder('u8u7if2f', 3, 9)

			// Assert
			expect(store.reminders).toHaveLength(1)
			consoleErrorSpy.mockRestore()
		})
	})
})

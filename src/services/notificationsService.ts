/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { getReminderNotificationsResponse } from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Fetches the reminder notifications of the user, that is the reminders which already triggered
 * and were not dismissed yet. Requires the `list-filter` capability of the notifications app.
 */
async function getReminderNotifications(): getReminderNotificationsResponse {
	return axios.get(generateOcsUrl('apps/notifications/api/v2/notifications'), {
		params: {
			app: 'spreed',
			objectType: 'reminder',
		},
	})
}

/**
 * Dismisses a single notification of the user
 *
 * @param notificationId The id of the notification
 */
async function dismissNotification(notificationId: number) {
	return axios.delete(generateOcsUrl('apps/notifications/api/v2/notifications/{notificationId}', {
		notificationId,
	}))
}

export {
	dismissNotification,
	getReminderNotifications,
}

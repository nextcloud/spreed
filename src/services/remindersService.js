/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Sets the reminder with defined timestamp on the message for the user
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @return {object} The axios response
 */
async function getMessageReminder(token, messageId) {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}))
}

/**
 * Sets the reminder with defined timestamp on the message for the user
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @param {number} timestamp The timestamp of reminder (in seconds)
 * @return {object} The axios response
 */
async function setMessageReminder(token, messageId, timestamp) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}), { timestamp })
}

/**
 * Removes the reminder from the message
 *
 * @param {string} token The conversation token
 * @param {number} messageId The id of selected message
 * @return {object} The axios response
 */
async function removeMessageReminder(token, messageId) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}))
}

/**
 * Fetches reminders list of all conversations
 *
 */
async function getUpcomingReminders() {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/chat/upcoming-reminders'))
}

export {
	getMessageReminder,
	getUpcomingReminders,
	removeMessageReminder,
	setMessageReminder,
}

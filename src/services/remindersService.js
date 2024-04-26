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
const getMessageReminder = async function(token, messageId) {
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
const setMessageReminder = async function(token, messageId, timestamp) {
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
const removeMessageReminder = async function(token, messageId) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/chat/{token}/{messageId}/reminder', {
		token,
		messageId,
	}))
}

export {
	getMessageReminder,
	setMessageReminder,
	removeMessageReminder,
}

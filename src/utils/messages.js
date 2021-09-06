/**
 * @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @copyright Copyright (c) 2021 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

import { ATTENDEE } from '../constants'
import moment from '@nextcloud/moment'

/**
 * Compare two messages to decide if they should be grouped
 *
 * @param {object} message1 The new message
 * @param {string} message1.id The ID of the new message
 * @param {string} message1.actorType Actor type of the new message
 * @param {string} message1.actorId Actor id of the new message
 * @param {string} message1.actorDisplayName Actor displayname of the new message
 * @param {string} message1.systemMessage System message content of the new message
 * @param {number} message1.timestamp Timestamp of the new message
 * @param {null|object} message2 The previous message
 * @param {string} message2.id The ID of the second message
 * @param {string} message2.actorType Actor type of the previous message
 * @param {string} message2.actorId Actor id of the previous message
 * @param {string} message2.actorDisplayName Actor display name of previous message
 * @param {string} message2.systemMessage System message content of the previous message
 * @param {number} message2.timestamp Timestamp of the second message
 * @return {boolean} Boolean if the messages should be grouped or not
 */
const messagesShouldBeGroupedByAuthor = function(message1, message2) {
	if (!message2) {
		return false // No previous message
	}

	if (message1.actorType === ATTENDEE.ACTOR_TYPE_BOTS // Don't group messages of commands and bots
				&& message1.actorId !== ATTENDEE.CHANGELOG_BOT_ID) { // Apart from the changelog bot
		return false
	}

	const message1IsSystem = message1.systemMessage.length !== 0
	const message2IsSystem = message2.systemMessage.length !== 0

	if (message1IsSystem !== message2IsSystem) {
		// Only group system messages with each others
		return false
	}

	if (!message1IsSystem // System messages are grouped independent from author
				&& ((message1.actorType !== message2.actorType // Otherwise the type and id need to match
					|| message1.actorId !== message2.actorId)
				|| (message1.actorType === ATTENDEE.ACTOR_TYPE.BRIDGED // Or, if the message is bridged, display names also need to match
					&& message1.actorDisplayName !== message2.actorDisplayName))) {
		return false
	}

	return !this.messagesHaveDifferentDate(message1, message2) // Posted on the same day
}

/**
 * Check if 2 messages are from the same date
 *
 * @param {object} message1 The new message
 * @param {string} message1.id The ID of the new message
 * @param {number} message1.timestamp Timestamp of the new message
 * @param {null|object} message2 The previous message
 * @param {string} message2.id The ID of the second message
 * @param {number} message2.timestamp Timestamp of the second message
 * @return {boolean} Boolean if the messages have the same date
 */
const messagesHaveSameDate = function(message1, message2) {
	return !message2 // There is no previous message
		|| this.getDateOfMessage(message1).format('YYYY-MM-DD') === this.getDateOfMessage(message2).format('YYYY-MM-DD')
}

/**
 * Generate the date header between the messages
 *
 * @param {object} message The message object
 * @param {string} message.id The ID of the message
 * @param {number} message.timestamp Timestamp of the message
 * @return {string} Translated string of "<Today>, <November 11th, 2019>", "<3 days ago>, <November 8th, 2019>"
 */
const generateDateSeparator = function(message) {
	const date = this.getDateOfMessage(message)
	const dayOfYear = date.format('YYYY-DDD')
	let relativePrefix = date.fromNow()

	// Use the relative day for today and yesterday
	const dayOfYearToday = moment().format('YYYY-DDD')
	if (dayOfYear === dayOfYearToday) {
		relativePrefix = t('spreed', 'Today')
	} else {
		const dayOfYearYesterday = moment().subtract(1, 'days').format('YYYY-DDD')
		if (dayOfYear === dayOfYearYesterday) {
			relativePrefix = t('spreed', 'Yesterday')
		}
	}

	// <Today>, <November 11th, 2019>
	return t('spreed', '{relativeDate}, {absoluteDate}', {
		relativeDate: relativePrefix,
		// 'LL' formats a localized date including day of month, month
		// name and year
		absoluteDate: date.format('LL'),
	}, undefined, {
		escape: false, // French "Today" has a ' in it
	})
}

export {
	messagesShouldBeGroupedByAuthor,
	messagesHaveSameDate,
	generateDateSeparator,
}

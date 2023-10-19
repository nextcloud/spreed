/**
 * @copyright Copyright (c) 2023 <dorra.jaoued7@gmail.com>
 *
 * @author Dorra Jaouad <dorra.jaoued7@gmail.com>
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
import { CONVERSATION } from '../constants.js'

/**
 * check if the conversation has unread messages
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function hasUnreadMessages(conversation) {
	return conversation.unreadMessages > 0
}

/**
 * check if the conversation has unread mentions
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function hasUnreadMentions(conversation) {
	return conversation.unreadMention
		|| conversation.unreadMentionDirect
		|| (conversation.unreadMessages > 0
			&& (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE || conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER))
}

/**
 * apply the active filter
 *
 * @param {string} filter the filter option
 * @param {object} conversation conversation object
 */
export function filterFunction(filter, conversation) {
	if (filter === 'unread') {
		return hasUnreadMessages(conversation)
	} else if (filter === 'mentions') {
		return hasUnreadMentions(conversation)
	}
}

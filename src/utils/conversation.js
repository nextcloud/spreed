/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

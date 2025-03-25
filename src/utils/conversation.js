/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { CONVERSATION, PARTICIPANT } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

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
 * check if the conversation has ongoing call
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function hasCall(conversation) {
	return conversation.hasCall && conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON
}

/**
 * check if the conversation is archived
 *
 * @param {object} conversation conversation object
 * @param {boolean} showArchived whether current filtered list is of archived conversations
 * @return {boolean}
 */
export function shouldIncludeArchived(conversation, showArchived) {
	return !supportsArchive || (conversation.isArchived === showArchived)
}

/**
 * apply the active filter
 *
 * @param {object} conversation conversation object
 * @param {Array} filters the filter option
 */
export function filterConversation(conversation, filters) {
	return filters.length === 0
		|| ((!filters.includes('unread') || (filters.includes('unread') && hasUnreadMessages(conversation)))
		&& (!filters.includes('mentions') || (filters.includes('mentions') && hasUnreadMentions(conversation))))
}

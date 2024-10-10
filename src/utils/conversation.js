/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { CONVERSATION, PARTICIPANT } from '../constants.js'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'

const supportsArchive = hasTalkFeature('local', 'archived-conversations')

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
	return conversation.hasCall && (!isArchived(conversation) || conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON)
}

/**
 * check if the conversation is archived
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function isArchived(conversation) {
	return conversation.isArchived
}

/**
 * check if the conversation passes default validation:
 * - non-archived
 * - archived, but have an unread message with notification triggered
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function isDefault(conversation) {
	return !isArchived(conversation)
		|| (conversation.notificationLevel === PARTICIPANT.NOTIFY.ALWAYS && hasUnreadMessages(conversation))
		|| (conversation.notificationLevel === PARTICIPANT.NOTIFY.MENTION && hasUnreadMentions(conversation))
}

/**
 * apply the active filter
 *
 * @param {string} filter the filter option
 * @param {string} archived the archived filter option
 * @param {object} conversation conversation object
 */
export function filterFunction(filter, archived, conversation) {
	const shouldIncludeArchived = supportsArchive
		? (archived ? isArchived(conversation) : isDefault(conversation))
		: true

	if (filter === 'unread') {
		return shouldIncludeArchived && hasUnreadMessages(conversation)
	} else if (filter === 'mentions') {
		return shouldIncludeArchived && hasUnreadMentions(conversation)
	} else {
		return shouldIncludeArchived
	}
}

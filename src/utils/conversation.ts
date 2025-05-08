/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ONE_HOUR_IN_MS } from './formattedTime.ts'
import { CONVERSATION, PARTICIPANT } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import type { EventTimeRange, Conversation } from '../types/index.ts'

type Filter = 'unread' | 'mentions' | 'events'

const supportsArchive = hasTalkFeature('local', 'archived-conversations-v2')

/**
 * check if the conversation has unread messages
 *
 * @param conversation conversation object
 */
export function hasUnreadMessages(conversation: Conversation): boolean {
	return conversation.unreadMessages > 0
}

/**
 * check if the conversation has unread mentions
 *
 * @param conversation conversation object
 */
export function hasUnreadMentions(conversation: Conversation): boolean {
	return conversation.unreadMention
		|| conversation.unreadMentionDirect
		|| (conversation.unreadMessages > 0
			&& (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE || conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER))
}

/**
 * check if the conversation has ongoing call
 *
 * @param conversation conversation object
 */
export function hasCall(conversation: Conversation): boolean {
	return conversation.hasCall && conversation.notificationCalls === PARTICIPANT.NOTIFY_CALLS.ON
}

/**
 * check if the conversation is an event conversation
 *
 * @param conversation conversation object
 */
export function isEvent(conversation: Conversation): boolean {
	return conversation.objectType === CONVERSATION.OBJECT_TYPE.EVENT
}

/**
 * check if the conversation is archived
 *
 * @param {object} conversation conversation object
 * @param showArchived whether current filtered list is of archived conversation
 */
export function shouldIncludeArchived(conversation: Conversation, showArchived: boolean): boolean {
	return !supportsArchive || (conversation.isArchived === showArchived)
}

/**
 * Returns the start and end time of the event conversation
 *
 * @param conversation conversation object
 * @return start and end time in milliseconds
 */
export function getEventTimeRange(conversation: Conversation): EventTimeRange {
	if (!isEvent(conversation) || !conversation.objectId) {
		return { start: null, end: null }
	}
	const parts = conversation.objectId.split('#')
	if (parts.length !== 2) {
		return { start: null, end: null }
	}

	const [start, end] = parts.map(time => Number(time) * 1000)
	if (isNaN(start) || isNaN(end)) {
		return { start: null, end: null }
	}

	return { start, end }
}

/**
 * check if the conversation is not an event conversation or if it is, check if it is happening in 16 hours
 *
 * @param {object} conversation conversation object
 */
export function shouldIncludeEvents(conversation: Conversation): boolean {
	return !isEvent(conversation)
	|| (conversation.objectId?.includes('#') && shouldEventBeVisible(conversation))
}

/**
 * check if the conversation is happening in 16 hours
 *
 * @param {object} conversation conversation object
 */
export function shouldEventBeVisible(conversation: Conversation): boolean {
	const startTime = getEventTimeRange(conversation).start
	if (!startTime) {
		return false
	}
	return startTime - Date.now() < 16 * ONE_HOUR_IN_MS
}

/**
 * apply the active filter
 *
 * @param conversation conversation object
 * @param filters the filter option
 */
export function filterConversation(conversation: Conversation, filters: Filter[]): boolean {
	if (filters.length === 0) {
		return shouldIncludeEvents(conversation)
	}
	return (!filters.includes('unread') || hasUnreadMessages(conversation))
		&& (!filters.includes('mentions') || hasUnreadMentions(conversation))
		&& (!filters.includes('events') || isEvent(conversation))
}

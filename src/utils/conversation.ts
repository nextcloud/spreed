/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { ONE_HOUR_IN_MS } from './formattedTime.ts'
import { CONVERSATION, PARTICIPANT } from '../constants.ts'
import { hasTalkFeature } from '../services/CapabilitiesManager.ts'
import { EventTimeRange } from '../types/index.ts'

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
 * check if the conversation is an event conversation
 *
 * @param {object} conversation conversation object
 * @return {boolean}
 */
export function isEvent(conversation) {
	return conversation.objectType === CONVERSATION.OBJECT_TYPE.EVENT
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
 * Returns the start and end time of the event conversation
 *
 * @param {object} conversation conversation object
 * @return {EventTimeRange} start and end time in milliseconds
 */
export function getEventTimeRange(conversation) {
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
 * @return {boolean}
 */
export function shouldIncludeEvents(conversation) {
	return !isEvent(conversation)
	|| (conversation.objectId?.includes('#') && shouldEventBeVisible(conversation))
}

/**
 * check if the conversation is happening in 16 hours
 *
 * @param {object} conversation conversation object
 */
export function shouldEventBeVisible(conversation) {
	return isEvent(conversation)
		&& getEventTimeRange(conversation).start - Date.now() < 16 * ONE_HOUR_IN_MS
}

/**
 * apply the active filter
 *
 * @param {object} conversation conversation object
 * @param {Array} filters the filter option
 */
export function filterConversation(conversation, filters) {
	if (filters.length === 0) {
		return shouldIncludeEvents(conversation)
	}
	return (!filters.includes('unread') || hasUnreadMessages(conversation))
		&& (!filters.includes('mentions') || hasUnreadMentions(conversation))
		&& (!filters.includes('events') || isEvent(conversation))
}

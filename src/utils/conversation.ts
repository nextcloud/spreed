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
const supportsAvatar = hasTalkFeature('local', 'avatar')

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

/**
 * check if the conversation is archived
 *
 * @param conversation conversation object
 * @param forceFallback whether fallback should be forced
 */
export function getFallbackIconClass(conversation: Conversation, forceFallback: boolean): string | undefined {
	if (conversation.isDummyConversation) {
		// Prevent a 404 when trying to load an avatar before the conversation data is actually loaded
		return conversation.type === CONVERSATION.TYPE.PUBLIC ? 'icon-public' : 'icon-contacts'
	}

	if (!supportsAvatar || forceFallback) {
		if (conversation.objectType === CONVERSATION.OBJECT_TYPE.FILE
			|| conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF) {
			return 'icon-file'
		} else if (conversation.objectType === CONVERSATION.OBJECT_TYPE.VIDEO_VERIFICATION) {
			return 'icon-password'
		} else if (conversation.objectType === CONVERSATION.OBJECT_TYPE.EMAIL) {
			return 'icon-mail'
		} else if (conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_LEGACY
			|| conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_PERSISTENT
			|| conversation.objectType === CONVERSATION.OBJECT_TYPE.PHONE_TEMPORARY) {
			return 'icon-phone'
		} else if (conversation.objectType === CONVERSATION.OBJECT_TYPE.EVENT) {
			return 'icon-event'
		} else if (conversation.objectType === CONVERSATION.OBJECT_TYPE.CIRCLES) {
			return 'icon-team'
		} else if (conversation.type === CONVERSATION.TYPE.CHANGELOG) {
			return 'icon-changelog'
		} else if (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER) {
			return 'icon-user'
		} else if (conversation.type === CONVERSATION.TYPE.GROUP) {
			return 'icon-contacts'
		} else if (conversation.type === CONVERSATION.TYPE.PUBLIC) {
			return 'icon-public'
		}
		return undefined
	}

	if (conversation.token) {
		// Existing conversations use the /avatar endpoint… Always!
		return undefined
	}

	if (conversation.objectType === CONVERSATION.OBJECT_TYPE.CIRCLES) {
		// Team icon for group conversation suggestions
		return 'icon-team'
	}

	if (conversation.type === CONVERSATION.TYPE.GROUP) {
		// Group icon for group conversation suggestions
		return 'icon-contacts'
	}

	// Fall-through for other conversation suggestions to user-avatar handling
	return undefined
}

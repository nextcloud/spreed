/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage, PinnedChatMessage } from '../types/index.ts'

import { MESSAGE, SHARED_ITEM } from '../constants.ts'
import { isFileShareMessage } from '../utils/message.ts'

/**
 * Derives item type from message object for preview rendering
 *
 * @param message message object
 * @param [key] key of the message parameter to check ('object', 'file', 'file-1', …)
 */
export function getItemTypeFromMessage(message: ChatMessage, key?: string): typeof SHARED_ITEM.TYPES[keyof typeof SHARED_ITEM.TYPES] {
	if (message.messageParameters?.object && (!key || key === 'object')) {
		const objectType = message.messageParameters.object.type
		if (objectType === SHARED_ITEM.OBJECT_TYPE.LOCATION) {
			return SHARED_ITEM.TYPES.LOCATION
		} else if (objectType === SHARED_ITEM.OBJECT_TYPE.DECK_CARD) {
			return SHARED_ITEM.TYPES.DECK_CARD
		} else if (objectType === SHARED_ITEM.OBJECT_TYPE.POLL) {
			return SHARED_ITEM.TYPES.POLL
		} else {
			return SHARED_ITEM.TYPES.OTHER
		}
	}

	const fileKey = key ?? 'file'
	if (message.messageParameters?.[fileKey]) {
		const messageType = message.messageType
		const mimetype = message.messageParameters[fileKey].mimetype || ''
		if (messageType === MESSAGE.TYPE.RECORD_AUDIO || messageType === MESSAGE.TYPE.RECORD_VIDEO) {
			return SHARED_ITEM.TYPES.RECORDING
		} else if (messageType === MESSAGE.TYPE.VOICE_MESSAGE) {
			return SHARED_ITEM.TYPES.VOICE
		} else if (mimetype.startsWith('audio/')) {
			return SHARED_ITEM.TYPES.AUDIO
		} else if (mimetype.startsWith('image/') || mimetype.startsWith('video/')) {
			return SHARED_ITEM.TYPES.MEDIA
		} else {
			return SHARED_ITEM.TYPES.FILE
		}
	}

	return SHARED_ITEM.TYPES.OTHER
}

/**
 * Validates whether a shared item has the required messageParameters for its type.
 * Only valid items should be stored and rendered.
 *
 * @param type shared item type
 * @param message message to validate
 * @return true if the message has valid messageParameters for its type
 */
export function isValidSharedItem(type: string, message: ChatMessage | PinnedChatMessage): boolean {
	// Pinned messages have a different structure
	if (type === SHARED_ITEM.TYPES.PINNED) {
		return true
	}

	// Items that require messageParameters.object
	if ([
		SHARED_ITEM.TYPES.LOCATION,
		SHARED_ITEM.TYPES.DECK_CARD,
		SHARED_ITEM.TYPES.POLL,
		SHARED_ITEM.TYPES.OTHER,
	].includes(type)) {
		return !!(message.messageParameters?.object)
	}

	// Items that require at least one shared file
	if ([
		SHARED_ITEM.TYPES.FILE,
		SHARED_ITEM.TYPES.AUDIO,
		SHARED_ITEM.TYPES.MEDIA,
		SHARED_ITEM.TYPES.RECORDING,
		SHARED_ITEM.TYPES.VOICE,
	].includes(type)) {
		return isFileShareMessage(message)
	}

	// At least one truthy property should be present
	return !!(message.messageParameters?.object) || isFileShareMessage(message)
}

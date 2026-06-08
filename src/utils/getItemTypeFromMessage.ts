/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ChatMessage, PinnedChatMessage } from '../types/index.ts'

import { MESSAGE, SHARED_ITEM } from '../constants.ts'

/**
 *
 * @param message
 */
export function getItemTypeFromMessage(message: ChatMessage): string {
	if (message.messageParameters?.object) {
		if (message.messageParameters.object.type === 'geo-location') {
			return SHARED_ITEM.TYPES.LOCATION
		} else if (message.messageParameters.object.type === 'deck-card') {
			return SHARED_ITEM.TYPES.DECK_CARD
		} else if (message.messageParameters.object.type === 'talk-poll') {
			return SHARED_ITEM.TYPES.POLL
		} else {
			return SHARED_ITEM.TYPES.OTHER
		}
	} else if (message.messageParameters?.file) {
		const messageType = message.messageType
		const mimetype = message.messageParameters.file.mimetype || ''
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
	} else {
		return SHARED_ITEM.TYPES.OTHER
	}
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

	// Items that require messageParameters.file
	if ([
		SHARED_ITEM.TYPES.FILE,
		SHARED_ITEM.TYPES.AUDIO,
		SHARED_ITEM.TYPES.MEDIA,
		SHARED_ITEM.TYPES.RECORDING,
		SHARED_ITEM.TYPES.VOICE,
	].includes(type)) {
		return !!(message.messageParameters?.file)
	}

	// At least one truthy property should be present
	return !!(message.messageParameters?.object) || !!(message.messageParameters?.file)
}

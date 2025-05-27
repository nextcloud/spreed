import type { ChatMessage } from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { MESSAGE, SHARED_ITEM } from '../constants.ts'

export const getItemTypeFromMessage = function(message: ChatMessage): string {
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

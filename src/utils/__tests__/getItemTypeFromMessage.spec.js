/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { describe, expect, it } from 'vitest'
import { MESSAGE, SHARED_ITEM } from '../../constants.ts'
import { getItemTypeFromMessage } from '../getItemTypeFromMessage.ts'

describe('getItemTypeFromMessage', () => {
	it('should return the correct item type for a messages', () => {
		const messages = {
			1: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { object: { type: 'geo-location' } } },
			2: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { object: { type: 'deck-card' } } },
			3: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { object: { type: 'talk-poll' } } },
			4: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { object: { type: 'some-type' } } },
			5: { messageType: MESSAGE.TYPE.RECORD_AUDIO, messageParameters: { file: { mimetype: 'audio/mp3' } } },
			6: { messageType: MESSAGE.TYPE.RECORD_VIDEO, messageParameters: { file: { mimetype: 'video/mp4' } } },
			7: { messageType: MESSAGE.TYPE.VOICE_MESSAGE, messageParameters: { file: { mimetype: 'audio/mp3' } } },
			8: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { file: { mimetype: 'audio/mp3' } } },
			9: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { file: { mimetype: 'image/jpg' } } },
			10: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { file: { mimetype: 'video/mp4' } } },
			11: { messageType: MESSAGE.TYPE.COMMENT, messageParameters: { file: { mimetype: 'text/markdown' } } },
			12: { messageType: MESSAGE.TYPE.COMMENT, message: 'simple message' },
		}

		const outputTypes = {
			1: SHARED_ITEM.TYPES.LOCATION,
			2: SHARED_ITEM.TYPES.DECK_CARD,
			3: SHARED_ITEM.TYPES.POLL,
			4: SHARED_ITEM.TYPES.OTHER,
			5: SHARED_ITEM.TYPES.RECORDING,
			6: SHARED_ITEM.TYPES.RECORDING,
			7: SHARED_ITEM.TYPES.VOICE,
			8: SHARED_ITEM.TYPES.AUDIO,
			9: SHARED_ITEM.TYPES.MEDIA,
			10: SHARED_ITEM.TYPES.MEDIA,
			11: SHARED_ITEM.TYPES.FILE,
			12: SHARED_ITEM.TYPES.OTHER,
		}

		for (const i in messages) {
			const type = i + ': ' + getItemTypeFromMessage(messages[i])
			expect(type).toBe(i + ': ' + outputTypes[i])
		}
	})
})

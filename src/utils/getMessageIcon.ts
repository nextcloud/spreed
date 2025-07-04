/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { ComponentPublicInstance } from 'vue'
import type { Conversation } from '../types/index.ts'

import IconCardTextOutline from 'vue-material-design-icons/CardTextOutline.vue'
import IconContactsOutline from 'vue-material-design-icons/ContactsOutline.vue'
import IconFileOutline from 'vue-material-design-icons/FileOutline.vue'
import IconImageOutline from 'vue-material-design-icons/ImageOutline.vue'
import IconMapMarkerOutline from 'vue-material-design-icons/MapMarkerOutline.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconMovieOutline from 'vue-material-design-icons/MovieOutline.vue'
import IconMusicNoteOutline from 'vue-material-design-icons/MusicNoteOutline.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import { MESSAGE } from '../constants.ts'

export const getMessageIcon = (lastMessage: Conversation['lastMessage']): ComponentPublicInstance | null => {
	if (!lastMessage || Array.isArray(lastMessage)) {
		return null
	}
	const file = lastMessage.messageParameters?.file
	if (file) {
		if (file.mimetype?.startsWith('video')) {
			return IconMovieOutline // Media - Videos
		}
		if (file.mimetype?.startsWith('image')) {
			return IconImageOutline // Media - Images
		}
		if (file.mimetype?.startsWith('audio')) {
			return lastMessage.messageType === MESSAGE.TYPE.VOICE_MESSAGE
				? IconMicrophoneOutline // Voice messages
				: IconMusicNoteOutline // Media - Audio
		}
		if (file.mimetype === 'text/vcard') {
			return IconContactsOutline // Contacts
		}
		return IconFileOutline // Files
	}

	const object = lastMessage.messageParameters?.object
	if (object) {
		if (object?.type === 'talk-poll') {
			return IconPoll // Polls
		}
		if (object?.type === 'deck-card') {
			return IconCardTextOutline // Deck cards
		}
		if (object?.type === 'geo-location') {
			return IconMapMarkerOutline // Locations
		}
	}

	return null
}

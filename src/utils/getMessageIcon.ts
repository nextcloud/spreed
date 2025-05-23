/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import type { ComponentPublicInstance } from 'vue'
import type { Conversation } from '../types/index.ts'

import IconCardText from 'vue-material-design-icons/CardText.vue'
import IconContacts from 'vue-material-design-icons/Contacts.vue'
import IconFile from 'vue-material-design-icons/File.vue'
import IconImage from 'vue-material-design-icons/Image.vue'
import IconMapMarker from 'vue-material-design-icons/MapMarker.vue'
import IconMicrophone from 'vue-material-design-icons/Microphone.vue'
import IconMovie from 'vue-material-design-icons/Movie.vue'
import IconMusicNote from 'vue-material-design-icons/MusicNote.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'

export const getMessageIcon = (lastMessage: Conversation['lastMessage']): ComponentPublicInstance | null => {
	if (!lastMessage || Array.isArray(lastMessage)) {
		return null
	}
	const file = lastMessage.messageParameters?.file
	if (file) {
		if (file.mimetype?.startsWith('video')) {
			return IconMovie // Media - Videos
		}
		if (file.mimetype?.startsWith('image')) {
			return IconImage // Media - Images
		}
		if (file.mimetype?.startsWith('audio')) {
			return lastMessage.messageType === 'voice-message'
				? IconMicrophone // Voice messages
				: IconMusicNote // Media - Audio
		}
		if (file.mimetype === 'text/vcard') {
			return IconContacts // Contacts
		}
		return IconFile // Files
	}

	const object = lastMessage.messageParameters?.object
	if (object) {
		if (object?.type === 'talk-poll') {
			return IconPoll // Polls
		}
		if (object?.type === 'deck-card') {
			return IconCardText // Deck cards
		}
		if (object?.type === 'geo-location') {
			return IconMapMarker // Locations
		}
	}

	return null
}

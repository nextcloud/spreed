/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mdiCardText, mdiContacts, mdiFile, mdiImage, mdiMapMarker, mdiMicrophone, mdiMovie, mdiMusicNote, mdiPoll } from '@mdi/js'

import type { Conversation } from '../types/index.ts'

const iconSvgTemplate = (path: string) => {
	const svgStyle = 'margin-block: calc((1lh - 16px)/2); vertical-align: bottom;'
	return `<svg xmlns="http://www.w3.org/2000/svg" style="${svgStyle}" fill="currentColor" width="16" height="16" viewBox="0 0 24 24"><path d="${path}"></path></svg>`
}

export const getMessageIcon = (lastMessage: Conversation['lastMessage']): string => {
	if (Array.isArray(lastMessage)) {
		return ''
	}
	const file = lastMessage?.messageParameters?.file
	if (file) {
		if (file.mimetype?.startsWith('video')) {
			return iconSvgTemplate(mdiMovie) // Media - Videos
		}
		if (file.mimetype?.startsWith('image')) {
			return iconSvgTemplate(mdiImage) // Media - Images
		}
		if (file.mimetype?.startsWith('audio')) {
			return lastMessage.messageType === 'voice-message'
				? iconSvgTemplate(mdiMicrophone) // Voice messages
				: iconSvgTemplate(mdiMusicNote) // Media - Audio
		}
		if (file.mimetype === 'text/vcard') {
			return iconSvgTemplate(mdiContacts) // Contacts
		}
		return iconSvgTemplate(mdiFile) // Files
	}

	const object = lastMessage.messageParameters?.object
	if (object) {
		if (object?.type === 'talk-poll') {
			return iconSvgTemplate(mdiPoll) // Polls
		}
		if (object?.type === 'deck-card') {
			return iconSvgTemplate(mdiCardText) // Deck cards
		}
		if (object?.type === 'geo-location') {
			return iconSvgTemplate(mdiMapMarker) // Locations
		}
	}

	return ''
}

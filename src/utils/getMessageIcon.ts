/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Conversation } from '../types/index.ts'

// SVG paths copied from https://raw.githubusercontent.com/Templarian/MaterialDesign-JS/master/mdi.js
const mdiCardText = 'M20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20M5,13V15H16V13H5M5,9V11H19V9H5Z'
const mdiContacts = 'M20,0H4V2H20V0M4,24H20V22H4V24M20,4H4A2,2 0 0,0 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6A2,2 0 0,0 20,4M12,6.75A2.25,2.25 0 0,1 14.25,9A2.25,2.25 0 0,1 12,11.25A2.25,2.25 0 0,1 9.75,9A2.25,2.25 0 0,1 12,6.75M17,17H7V15.5C7,13.83 10.33,13 12,13C13.67,13 17,13.83 17,15.5V17Z'
const mdiFile = 'M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z'
const mdiImage = 'M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z'
const mdiMapMarker = 'M12,11.5A2.5,2.5 0 0,1 9.5,9A2.5,2.5 0 0,1 12,6.5A2.5,2.5 0 0,1 14.5,9A2.5,2.5 0 0,1 12,11.5M12,2A7,7 0 0,0 5,9C5,14.25 12,22 12,22C12,22 19,14.25 19,9A7,7 0 0,0 12,2Z'
const mdiMicrophone = 'M12,2A3,3 0 0,1 15,5V11A3,3 0 0,1 12,14A3,3 0 0,1 9,11V5A3,3 0 0,1 12,2M19,11C19,14.53 16.39,17.44 13,17.93V21H11V17.93C7.61,17.44 5,14.53 5,11H7A5,5 0 0,0 12,16A5,5 0 0,0 17,11H19Z'
const mdiMovie = 'M18,4L20,8H17L15,4H13L15,8H12L10,4H8L10,8H7L5,4H4A2,2 0 0,0 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V4H18Z'
const mdiMusicNote = 'M12 3V13.55C11.41 13.21 10.73 13 10 13C7.79 13 6 14.79 6 17S7.79 21 10 21 14 19.21 14 17V7H18V3H12Z'
const mdiPoll = 'M3,22V8H7V22H3M10,22V2H14V22H10M17,22V14H21V22H17Z'

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

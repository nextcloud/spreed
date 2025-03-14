/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { generateFilePath } from '@nextcloud/router'

/**
 * Mock participant image for placeholders
 * @param i index
 */
export function placeholderImage(i: number) {
	return generateFilePath('spreed', 'docs', 'screenshotplaceholders/placeholder-' + (i % 9) + '.jpeg')
}

/**
 * Mock participant name for placeholders
 * @param i index
 * @param showKey show key next to the name
 */
export function placeholderName(i: number, showKey: boolean = false): string {
	switch (i % 9) {
	case 0:
		return 'Sandra McKinney' + (showKey ? ` | ${i}` : '')
	case 1:
		return 'Chris Wurst' + (showKey ? ` | ${i}` : '')
	case 2:
		return 'Edeltraut Bobb' + (showKey ? ` | ${i}` : '')
	case 3:
		return 'Arthur Blitz' + (showKey ? ` | ${i}` : '')
	case 4:
		return 'Roeland Douma' + (showKey ? ` | ${i}` : '')
	case 5:
		return 'Vanessa Steg' + (showKey ? ` | ${i}` : '')
	case 6:
		return 'Emily Grant' + (showKey ? ` | ${i}` : '')
	case 7:
		return 'Tobias Kaminsky' + (showKey ? ` | ${i}` : '')
	case 8:
	default:
		return 'Adrian Ada' + (showKey ? ` | ${i}` : '')
	}
}

/**
 * Mock participant model for placeholders
 * @param i index
 */
export function placeholderModel(i: number) {
	return {
		attributes: {
			audioAvailable: [1, 2, 4, 5, 7, 8].includes(i % 9),
			audioEnabled: (i % 9) === 8,
			videoAvailable: true,
			screen: false,
			currentVolume: 0.75,
			volumeThreshold: 0.75,
			localScreen: false,
			raisedHand: {
				state: [0, 1, 6].includes(i % 9),
			},
		},
		forceMute: () => {},
		on: () => {},
		off: () => {},
		getWebRtc: () => {
			return {
				connection: {
					getSendVideoIfAvailable: () => {},
				},
			}
		},
	}
}

/**
 * Mock shared data for placeholders
 */
export function placeholderSharedData() {
	return {
		videoEnabled: {
			isVideoEnabled: () => true,
		},
		remoteVideoBlocker: {
			isVideoEnabled: () => true,
		},
		screenVisible: false,
	}
}

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { loadState } from '@nextcloud/initial-state'
import { defineStore } from 'pinia'
import { PRIVACY } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import { getTalkConfig } from '../services/CapabilitiesManager.ts'
import {
	setBlurVirtualBackground,
	setConversationsListStyle,
	setReadStatusPrivacy,
	setStartWithoutMedia,
	setTypingStatusPrivacy,
} from '../services/settingsService.ts'

/**
 * @typedef {string} Token
 */

/**
 * @typedef {object} State
 * @property {PRIVACY.PUBLIC|PRIVACY.PRIVATE} readStatusPrivacy - The overview loaded state.
 * @property {PRIVACY.PUBLIC|PRIVACY.PRIVATE} typingStatusPrivacy - The overview loaded state.
 * @property {{[key: Token]: boolean}} showMediaSettings - The shared items pool.
 */

/**
 * Store for shared items shown in RightSidebar
 *
 * @param {string} id store name
 * @param {State} options.state store state structure
 */
export const useSettingsStore = defineStore('settings', {
	state: () => ({
		readStatusPrivacy: loadState('spreed', 'read_status_privacy', PRIVACY.PRIVATE),
		typingStatusPrivacy: loadState('spreed', 'typing_privacy', PRIVACY.PRIVATE),
		showMediaSettings: {},
		startWithoutMedia: getTalkConfig('local', 'call', 'start-without-media'),
		blurVirtualBackgroundEnabled: getTalkConfig('local', 'call', 'blur-virtual-background'),
		conversationsListStyle: getTalkConfig('local', 'conversations', 'list-style'),
	}),

	getters: {
		getShowMediaSettings: (state) => (token) => {
			if (!token) {
				return true
			}

			if (state.showMediaSettings[token] !== undefined) {
				return state.showMediaSettings[token]
			}

			const storedValue = BrowserStorage.getItem('showMediaSettings_' + token)

			switch (storedValue) {
				case 'true': {
					state.showMediaSettings[token] = true
					return true
				}
				case 'false': {
					state.showMediaSettings[token] = false
					return false
				}
				case null:
				default: {
					BrowserStorage.setItem('showMediaSettings_' + token, 'true')
					state.showMediaSettings[token] = true
					return true
				}
			}
		},
	},

	actions: {
		/**
		 * Update the read status privacy for the user
		 *
		 * @param {number} privacy The new selected privacy
		 */
		async updateReadStatusPrivacy(privacy) {
			await setReadStatusPrivacy(privacy)
			this.readStatusPrivacy = privacy
		},

		/**
		 * Update the typing status privacy for the user
		 *
		 * @param {number} privacy The new selected privacy
		 */
		async updateTypingStatusPrivacy(privacy) {
			await setTypingStatusPrivacy(privacy)
			this.typingStatusPrivacy = privacy
		},

		setShowMediaSettings(token, value) {
			if (value) {
				BrowserStorage.setItem('showMediaSettings_' + token, 'true')
			} else {
				BrowserStorage.setItem('showMediaSettings_' + token, 'false')
			}
			this.showMediaSettings[token] = value
		},

		async setBlurVirtualBackgroundEnabled(value) {
			await setBlurVirtualBackground(value)
			this.blurVirtualBackgroundEnabled = value
		},

		async setStartWithoutMedia(value) {
			await setStartWithoutMedia(value)
			this.startWithoutMedia = value
		},

		async setConversationsListStyle(value) {
			await setConversationsListStyle(value)
			this.conversationsListStyle = value
		},
	},
})

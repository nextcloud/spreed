/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { loadState } from '@nextcloud/initial-state'

import { PRIVACY } from '../constants.js'
import BrowserStorage from '../services/BrowserStorage.js'
import { setReadStatusPrivacy, setTypingStatusPrivacy } from '../services/settingsService.js'

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
		showMediaSettings: {}
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
				Vue.set(state.showMediaSettings, token, true)
				return true
			}
			case 'false': {
				Vue.set(state.showMediaSettings, token, false)
				return false
			}
			case null:
			default: {
				BrowserStorage.setItem('showMediaSettings_' + token, 'true')
				Vue.set(state.showMediaSettings, token, true)
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
			Vue.set(this.showMediaSettings, token, value)
		},
	},
})

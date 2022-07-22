/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import UAParser from 'ua-parser-js'

const browserCheck = {
	methods: {
		checkBrowser() {
			console.info('Detected browser ' + this.browser.name + ' ' + this.majorVersion + ' (' + this.browser.version + ')')
			if (!this.isFullySupported) {
				showError(
					this.unsupportedWarning,
					{
						timeout: TOAST_PERMANENT_TIMEOUT,
					})
			}
		},
	},
	computed: {
		browser() {
			const parser = new UAParser()
			return parser.getBrowser()
		},

		isFirefox() {
			return this.browser.name === 'Firefox'
		},
		isChrome() {
			return this.browser.name === 'Chrome' || this.browser.name === 'Chromium'
		},
		isOpera() {
			return this.browser.name === 'Opera'
		},
		isSafari() {
			return this.browser.name === 'Safari' || this.browser.name === 'Mobile Safari'
		},
		isEdge() {
			return this.browser.name === 'Edge'
		},
		isIE() {
			return this.browser.name === 'IE' || this.browser.name === 'IEMobile'
		},

		majorVersion() {
			return parseInt(this.browser.version.split('.')[0], 10)
		},

		isFullySupported() {
			return (this.isFirefox && this.majorVersion >= 52)
			|| (this.isChrome && this.majorVersion >= 49)
			|| (this.isOpera && this.majorVersion >= 72)
			|| (this.isSafari && this.majorVersion >= 12)
			|| this.isEdge
		},
		// Disable the call button and show the tooltip
		blockCalls() {
			return (this.isFirefox && this.majorVersion < 52)
			|| (this.isChrome && this.majorVersion < 49)
			|| (this.isOpera && this.majorVersion < 72)
			|| (this.isSafari && this.majorVersion < 12)
			|| this.isIE
		},
		// Used both in the toast and in the call button tooltip
		unsupportedWarning() {
			return t('spreed', "The browser you're using is not fully supported by Nextcloud Talk. Please use the latest version of Mozilla Firefox, Microsoft Edge, Google Chrome, Opera or Apple Safari.")
		},
		// Used in CallButton.vue
		callButtonTooltipText() {
			if (this.blockCalls) {
				return this.unsupportedWarning
			} else {
				// Passing a falsy value into the content of the tooltip
				// is the only way to disable it conditionally.
				return false
			}
		},

	},
}

export default browserCheck

/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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

import { showError } from '@nextcloud/dialogs'

const browserCheck = {
	methods: {
		checkBrowser() {
			if (!this.isFullySupported) {
				showError(
					this.unsupportedWarning,
					{
						timeout: 0,
					})
			}
		},
	},
	computed: {
		isFullySupported() {
			return (this.$browserDetect.isFirefox && this.$browserDetect.meta.version >= 52)
			|| (this.$browserDetect.isChrome && this.$browserDetect.meta.version >= 49)
		},
		// Disable the call button and show the tooltip
		blockCalls() {
			return (this.$browserDetect.isFirefox && this.$browserDetect.meta.version < 52)
			|| (this.$browserDetect.isChrome && this.$browserDetect.meta.version < 49)
			|| this.$browserDetect.isIE
		},
		// Used both in the toast and in the callbutton tooltip
		unsupportedWarning() {
			return t('spreed', "The browser you're using is not fully supported by talk. Please use the latest version of Mozilla Firefox, Microsoft Edge, Google Chrome or Apple Safari.")
		},
		// Used in CallButton.vue
		callButtonTooltipText() {
			if (this.blockCalls) {
				return this.unsupportedWarning
			} else {
				// Passind a falsy value into the content of the tooltip
				// is the only way to disable it conditionally.
				return false
			}
		},

	},
}

export default browserCheck

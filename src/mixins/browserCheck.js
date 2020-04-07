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
						timeout: 3600,
						isHTML: true,
					})
			}
		},
	},
	computed: {
		isFullySupported() {
			return (this.$browserDetect.isFirefox && this.$browserDetect.meta.version >= 52)
			|| (this.$browserDetect.isChrome && this.$browserDetect.meta.version >= 49)
		},
		blockCalls() {
			return (this.$browserDetect.isFirefox && this.$browserDetect.meta.version < 52)
			|| (this.$browserDetect.isChrome && this.$browserDetect.meta.version < 49)
		},
		unsupportedWarning() {
			return t('spreed', "The browser you're using is not fully supported by talk. Please use the latest version of {firefox}, {edge}, {chrome} or {safari}.").replace('{firefox}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://www.mozilla.org/en-US/firefox/new/">Mozilla Firefox</a>').replace('{edge}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://www.microsoft.com/en-us/edge">Microsoft Edge</a>').replace('{chrome}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://www.google.com/chrome/?brand=CHBD&gclid=EAIaIQobChMIkY_v0ZDU6AIVhYXVCh0sfA7XEAAYASAAEgIKfPD_BwE&gclsrc=aw.ds">Google Chrome</a>').replace('{safari}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://support.apple.com/downloads/safari">Apple Safari</a>')
		},
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

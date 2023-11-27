/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
 * @author Grigorii K. Shartsev <me@shgk.me>
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

import UAParser from 'ua-parser-js'

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'

const parser = new UAParser()
const browser = parser.getBrowser()

const getBrowserVersion = () => {
	if (browser.version) {
		return browser.version
	}

	if (browser.name === 'Safari') {
		// Workaround for https://github.com/faisalman/ua-parser-js/issues/599
		const match = parser.getUA().match(' Version/([0-9.,]+) ')
		if (match) {
			return match[1]
		}
	}

	return undefined
}

export const isFirefox = browser.name === 'Firefox'
export const isChrome = browser.name === 'Chrome' || browser.name === 'Chromium'
export const isOpera = browser.name === 'Opera'
export const isSafari = browser.name === 'Safari' || browser.name === 'Mobile Safari'
export const isEdge = browser.name === 'Edge'
export const isBrave = browser.name === 'Brave'
export const isIE = browser.name === 'IE' || browser.name === 'IEMobile'
export const isYandex = browser.name === 'Yandex'

export const browserVersion = getBrowserVersion()
export const majorVersion = browserVersion ? parseInt(browserVersion.split('.')[0], 10) : 0

/**
 * Is the browser fully supported by Talk
 */
export const isFullySupported = (isFirefox && majorVersion >= 52)
	|| (isChrome && majorVersion >= 49)
	|| (isOpera && majorVersion >= 72)
	|| (isSafari && majorVersion >= 12)
	|| isEdge
	|| isBrave
	|| isYandex

/**
 * Are calls should be blocked due to browser incompatibility
 */
export const blockCalls = (isFirefox && majorVersion < 52)
	|| (isChrome && majorVersion < 49)
	|| (isOpera && majorVersion < 72)
	|| (isSafari && majorVersion < 12)
	|| isIE

/**
 * Reusable error message for unsupported browsers
 */
export const unsupportedWarning = t('spreed', "The browser you're using is not fully supported by Nextcloud Talk. Please use the latest version of Mozilla Firefox, Microsoft Edge, Google Chrome, Opera or Apple Safari.")

/**
 * Show an error toast if the browser is not fully supported
 */
export function checkBrowser() {
	console.info('Detected browser ' + browser.name + ' ' + majorVersion + ' (' + browserVersion + ')')
	if (!isFullySupported) {
		showError(unsupportedWarning, { timeout: TOAST_PERMANENT_TIMEOUT })
	}
}

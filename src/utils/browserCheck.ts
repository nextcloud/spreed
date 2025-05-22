/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { UAParser } from 'ua-parser-js'

import { showError, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

const parser = new UAParser()
const browser = parser.getBrowser()
const os = parser.getOS()

/**
 * Per-OS flags
 */

export const isMac = os.name === 'macOS'

/**
 * Per-browser flags and a major version
 */

export const isFirefox = browser.name === 'Firefox' || browser.name === 'Mobile Firefox'
export const isChrome = browser.name === 'Chrome' || browser.name === 'Chromium' || browser.name === 'Mobile Chrome'
export const isOpera = browser.name === 'Opera'
export const isSafari = browser.name === 'Safari' || browser.name === 'Mobile Safari'
export const isEdge = browser.name === 'Edge'
export const isBrave = browser.name === 'Brave'
export const isIE = browser.name === 'IE' || browser.name === 'IEMobile'
export const isYandex = browser.name === 'Yandex'

export const majorVersion = browser.major ? parseInt(browser.major, 10) : 0

/**
 * Is the browser Chromium-based
 */
export const isChromium = isChrome
	|| isOpera
	|| isSafari
	|| isEdge
	|| isBrave
	|| isYandex

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
	console.info('Detected browser ' + browser.name + ' ' + majorVersion + ' (' + browser.version + ')')
	if (!isFullySupported) {
		showError(unsupportedWarning, { timeout: TOAST_PERMANENT_TIMEOUT })
	}
}

/**
 * Check if the browser supports WebRTC Encoded Transform (i.e. Firefox).
 */
export function supportsEncodedTransform() {
	return (window.RTCRtpScriptTransform && window.RTCRtpSender && 'transform' in RTCRtpSender.prototype)
}

/**
 * Check if the browser supports insertable streams (i.e. Chromium).
 */
export function supportsInsertableStreams() {
	if (!(window.RTCRtpReceiver && 'createEncodedStreams' in RTCRtpReceiver.prototype && window.RTCRtpSender && 'createEncodedStreams' in RTCRtpSender.prototype)) {
		return false
	}

	// Feature-detect transferable streams which we need to operate in a worker.
	// See https://groups.google.com/a/chromium.org/g/blink-dev/c/1LStSgBt6AM/m/hj0odB8pCAAJ
	const stream = new ReadableStream()
	try {
		window.postMessage(stream, '*', [stream])
		return true
	} catch {
		return false
	}
}

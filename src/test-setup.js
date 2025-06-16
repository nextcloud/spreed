/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import { mockedCapabilities } from './__mocks__/capabilities.ts'

import 'regenerator-runtime/runtime'

jest.mock('extendable-media-recorder', () => ({
	MediaRecorder: jest.fn(),
	register: jest.fn(),
}))

jest.mock('extendable-media-recorder-wav-encoder', () => ({
	connect: jest.fn(),
}))

jest.mock('@nextcloud/initial-state', () => ({
	loadState: jest.fn().mockImplementation((app, key, fallback) => {
		return fallback
	}),
}))

jest.mock('@nextcloud/upload', () => ({
	getUploader: jest.fn(),
}))

jest.mock('@nextcloud/capabilities', () => ({
	getCapabilities: jest.fn(() => mockedCapabilities),
}))

HTMLAudioElement.prototype.setSinkId = jest.fn()

window.IntersectionObserver = jest.fn(() => ({
	observe: jest.fn(),
	unobserve: jest.fn(),
	disconnect: jest.fn(),
}))

window._oc_webroot = '/nc-webroot' // used by getRootUrl() | since @nextcloud/router 2.2.1

global.appName = 'spreed'

global.OC = {
	requestToken: '123',
	coreApps: [
		'core',
	],
	config: {
		modRewriteWorking: true,
	},
	dialogs: {
	},
	isUserAdmin() {
		return true
	},
	getLanguage() {
		return 'en-GB'
	},
	getLocale() {
		return 'en_GB'
	},

	MimeType: {
		getIconUrl: jest.fn(),
	},

	PERMISSION_NONE: 0,
	PERMISSION_READ: 1,
	PERMISSION_UPDATE: 2,
	PERMISSION_CREATE: 4,
	PERMISSION_DELETE: 8,
	PERMISSION_SHARE: 16,
	PERMISSION_ALL: 31,
}
global.OCA = {
	Talk: {
	},
}
global.OCP = {
	Accessibility: {
		disableKeyboardShortcuts: () => false,
	},
}
global.IS_DESKTOP = false

/**
 * Polyfill for Blob.prototype.arrayBuffer
 * Required as jsdom breaks Nodejs's native Blob
 *
 * @see https://github.com/jsdom/jsdom/issues/2555
 */
function myArrayBuffer() {
	// this: File or Blob
	return new Promise((resolve) => {
		const fr = new FileReader()
		fr.onload = () => {
			resolve(fr.result)
		}
		fr.readAsArrayBuffer(this)
	})
}

global.Blob.prototype.arrayBuffer = Blob.prototype.arrayBuffer || myArrayBuffer

global.BroadcastChannel = jest.fn(() => ({
	postMessage: jest.fn(),
	addEventListener: jest.fn(),
}))

global.ResizeObserver = jest.fn(() => ({
	observe: jest.fn(),
	unobserve: jest.fn(),
	disconnect: jest.fn(),
}))

global.structuredClone = jest.fn((val) => JSON.parse(JSON.stringify(val)))

// Work around missing "URL.createObjectURL" (which is used in the code but not
// relevant for the tests) in jsdom: https://github.com/jsdom/jsdom/issues/1721
window.URL.createObjectURL = jest.fn()
window.URL.revokeObjectURL = jest.fn()

Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

// Disable Vue's production tip and devtools console messages
Vue.config.productionTip = false
Vue.config.devtools = false

// Make Jest fail on errors or warnings (like a11y warning from nextcloud/vue library)
const originalWarn = global.console.warn
console.warn = function(message) {
	originalWarn.apply(console, arguments)
	throw (message instanceof Error ? message : new Error(message))
}

const originalError = global.console.error
console.error = function(message) {
	originalError.apply(console, arguments)
	throw (message instanceof Error ? message : new Error(message))
}

// Disable console.debug messages for the sake of cleaner test output
// Comment this line if required to see debug messages locally
console.debug = jest.fn()

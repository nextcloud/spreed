/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, setActivePinia } from 'pinia'
import { vi } from 'vitest'
import { mockedCapabilities } from './__mocks__/capabilities.ts'

import 'regenerator-runtime/runtime'

vi.mock('extendable-media-recorder', () => ({
	MediaRecorder: vi.fn(),
	register: vi.fn(),
}))

vi.mock('extendable-media-recorder-wav-encoder', () => ({
	connect: vi.fn(),
}))

vi.mock('@nextcloud/dialogs', () => ({
	showInfo: vi.fn(),
	showSuccess: vi.fn(),
	showError: vi.fn(),
	showWarning: vi.fn(),
	TOAST_PERMANENT_TIMEOUT: -1,
}))

vi.mock('@nextcloud/initial-state', () => ({
	loadState: vi.fn().mockImplementation((app, key, fallback) => {
		return fallback
	}),
}))

vi.mock('@nextcloud/upload', () => ({
	getUploader: vi.fn(),
}))

vi.mock('@nextcloud/capabilities', () => ({
	getCapabilities: vi.fn(() => mockedCapabilities),
}))

HTMLAudioElement.prototype.setSinkId = vi.fn()

window.IntersectionObserver = vi.fn(() => ({
	observe: vi.fn(),
	unobserve: vi.fn(),
	disconnect: vi.fn(),
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
		getIconUrl: vi.fn(),
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

global.BroadcastChannel = vi.fn(() => ({
	postMessage: vi.fn(),
	addEventListener: vi.fn(),
}))

global.ResizeObserver = vi.fn(() => ({
	observe: vi.fn(),
	unobserve: vi.fn(),
	disconnect: vi.fn(),
}))

global.structuredClone = vi.fn((val) => JSON.parse(JSON.stringify(val)))

// Work around missing "URL.createObjectURL" (which is used in the code but not
// relevant for the tests) in jsdom: https://github.com/jsdom/jsdom/issues/1721
window.URL.createObjectURL = vi.fn()
window.URL.revokeObjectURL = vi.fn()

// Make test fail on errors or warnings (like a11y warning from nextcloud/vue library)
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
console.debug = vi.fn()

// Set up Pinia for state management in tests
setActivePinia(createPinia())

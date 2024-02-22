/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

// eslint-disable-next-line
import 'regenerator-runtime/runtime'
import Vue from 'vue'

import { translate, translatePlural } from '@nextcloud/l10n'

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

// Work around missing "URL.createObjectURL" (which is used in the code but not
// relevant for the tests) in jsdom: https://github.com/jsdom/jsdom/issues/1721
window.URL.createObjectURL = jest.fn()
window.URL.revokeObjectURL = jest.fn()

global.t = translate
global.n = translatePlural

Vue.prototype.t = global.t
Vue.prototype.n = global.n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

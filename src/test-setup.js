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

jest.mock('extendable-media-recorder', () => ({
	MediaRecorder: jest.fn(),
	register: jest.fn(),
}))

jest.mock('extendable-media-recorder-wav-encoder', () => ({
	connect: jest.fn(),
}))

global.appName = 'spreed'

global.OC = {
	requestToken: '123',
	webroot: '/nc-webroot',
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

// Work around missing "URL.createObjectURL" (which is used in the code but not
// relevant for the tests) in jsdom: https://github.com/jsdom/jsdom/issues/1721
window.URL.createObjectURL = jest.fn()
window.URL.revokeObjectURL = jest.fn()

// TODO: use nextcloud-l10n lib once https://github.com/nextcloud/nextcloud-l10n/issues/271 is solved
global.t = jest.fn().mockImplementation((app, text) => text)
global.n = jest.fn().mockImplementation((app, text) => text)

Vue.prototype.t = global.t
Vue.prototype.n = global.n
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA
Vue.prototype.FEATURE_FLAGS = {}

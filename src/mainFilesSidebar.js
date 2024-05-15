/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import VueObserveVisibility from 'vue-observe-visibility'
import VueShortKey from 'vue3-shortkey'

import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import FilesSidebarCallViewApp from './FilesSidebarCallViewApp.vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'

import './init.js'
import store from './store/index.js'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

// Leaflet icon patch
import 'leaflet/dist/leaflet.css'
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package

// eslint-disable-next-line
import 'leaflet-defaulticon-compatibility'

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

const pinia = createPinia()

store.dispatch('setMainContainerSelector', '.talkChatTab')

const newCallView = () => createApp(FilesSidebarCallViewApp)
	.use(store)
	.use(pinia)
	.use(VueObserveVisibility)
	.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
	.use(NextcloudGlobalsVuePlugin)

const newTab = () => createApp(FilesSidebarTabApp)
	.use(store)
	.use(pinia)
	.use(VueObserveVisibility)
	.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
	.use(NextcloudGlobalsVuePlugin)

if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}
Object.assign(window.OCA.Talk, {
	fileInfo: null,
	newCallView,
	newTab,
	store,
})

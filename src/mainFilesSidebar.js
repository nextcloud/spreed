/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { createApp, reactive } from 'vue'
import FilesSidebarCallViewApp from './FilesSidebarCallViewApp.vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

import './init.js'
// Leaflet icon patch
import 'leaflet/dist/leaflet.css'
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package
import 'leaflet-defaulticon-compatibility'

// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = getCSPNonce()

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

const newCallView = () => createApp(FilesSidebarCallViewApp)
	.use(store)
	.use(pinia)
	.use(NextcloudGlobalsVuePlugin)

const newTab = () => createApp(FilesSidebarTabApp)
	.use(store)
	.use(pinia)
	.use(NextcloudGlobalsVuePlugin)

if (!window.OCA.Talk) {
	window.OCA.Talk = reactive({})
}
Object.assign(window.OCA.Talk, {
	fileInfo: null,
	newCallView,
	newTab,
	store,
})

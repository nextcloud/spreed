/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'
import Vuex from 'vuex'

import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import FilesSidebarCallViewApp from './FilesSidebarCallViewApp.vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'

import './init.js'
import store from './store/index.js'

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

Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(PiniaVuePlugin)
Vue.use(Vuex)

const pinia = createPinia()

const newCallView = () => new Vue({
	store,
	pinia,
	render: h => h(FilesSidebarCallViewApp),
})

const newTab = () => new Vue({
	store,
	pinia,
	id: 'talk-chat-tab',
	render: h => h(FilesSidebarTabApp),
})

if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}
Object.assign(window.OCA.Talk, {
	fileInfo: null,
	newCallView,
	newTab,
	store,
})

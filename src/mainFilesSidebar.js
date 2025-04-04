/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'
import Vuex from 'vuex'

import FilesSidebarCallViewApp from './FilesSidebarCallViewApp.vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'

import './init.js'
import PrivateTalk from './mainFilesSidebarLoader.js'
import store from './store/index.js'
import FilesSidebarCallView from './views/FilesSidebarCallView.js'

// Leaflet icon patch
import 'leaflet/dist/leaflet.css'
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package

// eslint-disable-next-line
import 'leaflet-defaulticon-compatibility'

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

Object.assign(window.OCA.Talk, {
	newCallView,
	newTab,
	store,
})

export const mountSidebar = (mountEl) => {
	if (OCA.Files?.Sidebar) {
		OCA.Files.Sidebar.registerSecondaryView(new FilesSidebarCallView())
		PrivateTalk.tabInstance = OCA.Talk.newTab()
		PrivateTalk.tabInstance.$mount(mountEl)
	}
}

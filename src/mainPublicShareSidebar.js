/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue, { reactive } from 'vue'
import Vuex from 'vuex'

import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import PublicShareSidebar from './PublicShareSidebar.vue'
import PublicShareSidebarTrigger from './PublicShareSidebarTrigger.vue'

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

/**
 *
 */
function adjustLayout() {
	document.querySelector('#app-content').appendChild(document.querySelector('footer'))

	const talkSidebarElement = document.createElement('div')
	talkSidebarElement.setAttribute('id', 'talk-sidebar')
	document.querySelector('#content').appendChild(talkSidebarElement)
}

adjustLayout()

// An "isOpen" boolean should be passed to the component, but as it is a
// primitive it would not be reactive; it needs to be wrapped in an object and
// that object passed to the component to get reactivity.
const sidebarState = reactive({
	isOpen: false,
})

// Open the sidebar by default based on the window width using the same
// threshold as in the main Talk UI (in Talk 7).
if (window.innerWidth > 1111) {
	sidebarState.isOpen = true
}

/**
 *
 */
function addTalkSidebarTrigger() {
	const talkSidebarTriggerElement = document.createElement('button')
	talkSidebarTriggerElement.setAttribute('id', 'talk-sidebar-trigger')

	// The ".header-right" element may not exist in the public share page if
	// there are no header actions.
	if (!document.querySelector('.header-right')) {
		const headerRightElement = document.createElement('div')
		headerRightElement.setAttribute('class', 'header-right')
		document.querySelector('#header').appendChild(headerRightElement)
	}

	document.querySelector('.header-right').appendChild(talkSidebarTriggerElement)

	const talkSidebarTriggerVm = new Vue({
		propsData: {
			sidebarState,
		},
		...PublicShareSidebarTrigger,
	})
	talkSidebarTriggerVm.$on('click', () => {
		sidebarState.isOpen = !sidebarState.isOpen
	})
	talkSidebarTriggerVm.$mount('#talk-sidebar-trigger')
}

addTalkSidebarTrigger()

/**
 *
 */
function getShareToken() {
	const shareTokenElement = document.getElementById('sharingToken')
	return shareTokenElement.value
}

const talkSidebarVm = new Vue({
	store,
	pinia,
	id: 'talk-chat-tab',
	propsData: {
		shareToken: getShareToken(),
		state: sidebarState,
	},
	...PublicShareSidebar,
})
talkSidebarVm.$mount(document.querySelector('#talk-sidebar'))

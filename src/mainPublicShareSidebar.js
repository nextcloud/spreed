/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { getSharingToken } from '@nextcloud/sharing/public'
import { createApp, reactive } from 'vue'
import PublicShareSidebar from './PublicShareSidebar.vue'
import PublicShareSidebarTrigger from './PublicShareSidebarTrigger.vue'
import { createMemoryRouter } from './router/router.ts'
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
 * Mount the Talk sidebar toggle button to the header.
 */
function addTalkSidebarTrigger() {
	const talkSidebarTriggerElement = document.createElement('div')
	talkSidebarTriggerElement.setAttribute('id', 'talk-sidebar-trigger')
	// The ".header-end" element should exist (/server/core/templates/layout.public.php)
	const mountPoint = document.querySelector('.header-end') ?? document.getElementById('header')
	mountPoint.appendChild(talkSidebarTriggerElement)

	createApp(PublicShareSidebarTrigger, {
		sidebarState,
		onClick: () => {
			sidebarState.isOpen = !sidebarState.isOpen
		},
	}).mount('#talk-sidebar-trigger')
}

addTalkSidebarTrigger()

/**
 * Mount the Talk sidebar next to the main content.
 */
function addTalkSidebar() {
	const talkSidebarElement = document.createElement('div')
	talkSidebarElement.setAttribute('id', 'talk-sidebar')
	document.getElementById('content-vue').appendChild(talkSidebarElement)

	const router = createMemoryRouter()

	createApp(PublicShareSidebar, {
		shareToken: getSharingToken(),
		state: sidebarState,
	})
		.use(pinia)
		.use(store)
		.use(router)
		.use(NextcloudGlobalsVuePlugin)
		.mount(document.querySelector('#talk-sidebar'))
}

addTalkSidebar()

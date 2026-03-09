/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'
import { createApp } from 'vue'
import PublicShareAuthRequestPasswordButton from './PublicShareAuthRequestPasswordButton.vue'
import PublicShareAuthSidebar from './PublicShareAuthSidebar.vue'
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

/**
 * Add mount containers for 'Request password' button and Talk sidebar
 */
function adjustLayout() {
	const requestPasswordElement = document.createElement('div')
	requestPasswordElement.setAttribute('id', 'talk-public-share-auth')
	document.getElementById('guest-content-vue').appendChild(requestPasswordElement)

	const talkSidebarElement = document.createElement('div')
	talkSidebarElement.setAttribute('id', 'talk-public-share-auth-sidebar')
	document.body.appendChild(talkSidebarElement)
}

adjustLayout()

const router = createMemoryRouter()

createApp(PublicShareAuthRequestPasswordButton)
	.use(pinia)
	.use(store)
	.use(router)
	.use(NextcloudGlobalsVuePlugin)
	.mount('#talk-public-share-auth')

createApp(PublicShareAuthSidebar)
	.use(pinia)
	.use(store)
	.use(router)
	.use(NextcloudGlobalsVuePlugin)
	.mount('#talk-public-share-auth-sidebar')

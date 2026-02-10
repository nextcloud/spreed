/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { emit, subscribe } from '@nextcloud/event-bus'
import { generateFilePath } from '@nextcloud/router'
import { createApp, reactive, watch } from 'vue'
import App from './App.vue'
import { createTalkRouter } from './router/router.ts'
import { SettingsAPI } from './services/SettingsAPI.ts'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { useSidebarStore } from './stores/sidebar.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

import './init.js'
// Leaflet icon patch
import 'leaflet/dist/leaflet.css'
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package
import 'leaflet-defaulticon-compatibility'

if (!IS_DESKTOP) {
	// CSP config for webpack dynamic chunk loading
	__webpack_nonce__ = getCSPNonce()

	// Correct the root of the app for chunk loading
	// OC.linkTo matches the apps folders
	// OC.generateUrl ensure the index.php (or not)
	// We do not want the index.php since we're loading files
	__webpack_public_path__ = generateFilePath('spreed', '', 'js/')
}

const router = createTalkRouter()

const instance = createApp(App, { fileInfo: null })
	.use(store)
	.use(pinia)
	.use(router)
	.use(NextcloudGlobalsVuePlugin)
	.mount('#content')

window.store = store

// Setup Viewer to be used with Talk sidebar
/**
 *
 * @param sidebarElement
 * @param resolve
 */
function waitForSidebarToBeOpen(sidebarElement, resolve) {
	if ('ontransitionend' in sidebarElement) {
		const resolveOnceSidebarWidthHasChanged = (event) => {
			if (!['min-width', 'width', 'max-width', 'margin-right'].includes(event.propertyName)) {
				return
			}

			sidebarElement.removeEventListener('transitionend', resolveOnceSidebarWidthHasChanged)

			emit('files:sidebar:opened')

			resolve()
		}

		sidebarElement.addEventListener('transitionend', resolveOnceSidebarWidthHasChanged)
	} else {
		const animationQuickValue = getComputedStyle(document.documentElement).getPropertyValue('--animation-quick')

		// The browser does not support the "ontransitionend" event, so just
		// wait a few milliseconds more than the duration of the transition.
		setTimeout(() => {
			console.debug('ontransitionend is not supported; the sidebar should have been fully shown by now')

			emit('files:sidebar:opened')

			resolve()
		}, Number.parseInt(animationQuickValue) + 200)
	}
}

subscribe('viewer:sidebar:open', (node) => {
	const sidebarStore = useSidebarStore()
	// The sidebar is already open, so this can return immediately.
	if (sidebarStore.show) {
		emit('files:sidebar:opened')

		return
	}

	sidebarStore.showSidebar()

	const sidebarElement = document.getElementById('app-sidebar') ?? document.getElementById('app-sidebar-vue')

	// The Viewer adjusts its width to the sidebar width once the sidebar has
	// been opened. The sidebar opens with an animation, so a delay is needed
	// before the width can be properly adjusted.
	return new Promise((resolve, reject) => {
		waitForSidebarToBeOpen(sidebarElement, resolve)
	})
})

// make the instance available to global components that might run on the same page
if (!window.OCA.Talk) {
	window.OCA.Talk = reactive({})
}
OCA.Talk.instance = instance
OCA.Talk.Settings = SettingsAPI

export default instance

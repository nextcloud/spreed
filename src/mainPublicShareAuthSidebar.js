/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createPinia } from 'pinia'
import { createApp } from 'vue'
import VueObserveVisibility from 'vue-observe-visibility'
import VueShortKey from 'vue3-shortkey'

import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import PublicShareAuthRequestPasswordButton from './PublicShareAuthRequestPasswordButton.vue'
import PublicShareAuthSidebar from './PublicShareAuthSidebar.vue'

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
store.dispatch('setMainContainerSelector', '#talk-sidebar')

/**
 * Wraps all the body contents in its own container.
 *
 * The root element of the layout needs to be flex, but the body element can not
 * be in order to properly place the autocompletion panel using an absolute
 * position.
 */
function wrapBody() {
	const bodyElement = document.querySelector('body')
	const bodyWrapperElement = document.createElement('div')

	while (bodyElement.childNodes.length) {
		bodyWrapperElement.appendChild(bodyElement.childNodes[0])
	}

	while (bodyElement.classList.length) {
		bodyWrapperElement.classList.add(bodyElement.classList.item(0))
		bodyElement.classList.remove(bodyElement.classList.item(0))
	}

	bodyWrapperElement.setAttribute('id', bodyElement.getAttribute('id'))
	bodyElement.removeAttribute('id')

	bodyElement.appendChild(bodyWrapperElement)
}

/**
 *
 */
function adjustLayout() {
	const contentElement = document.createElement('div')
	contentElement.setAttribute('id', 'content')
	document.querySelector('body').appendChild(contentElement)

	contentElement.appendChild(document.querySelector('.wrapper'))
	contentElement.appendChild(document.querySelector('footer'))

	const requestPasswordElement = document.createElement('div')
	requestPasswordElement.setAttribute('id', 'request-password')
	document.querySelector('.guest-box').appendChild(requestPasswordElement)

	const talkSidebarElement = document.createElement('div')
	talkSidebarElement.setAttribute('id', 'talk-sidebar')
	document.querySelector('body').appendChild(talkSidebarElement)

	wrapBody()

	document.querySelector('body').classList.add('talk-sidebar-enabled')
}

adjustLayout()

/**
 *
 */
function getShareToken() {
	const shareTokenElement = document.getElementById('sharingToken')
	return shareTokenElement.value
}

createApp(PublicShareAuthRequestPasswordButton, {
	shareToken: getShareToken(),
})
	.use(pinia)
	.use(store)
	.use(VueObserveVisibility)
	.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
	.use(NextcloudGlobalsVuePlugin)
	.mount('#request-password')

createApp(PublicShareAuthSidebar)
	.use(pinia)
	.use(store)
	.use(VueObserveVisibility)
	.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
	.use(NextcloudGlobalsVuePlugin)
	.mount(document.querySelector('#talk-sidebar'))

/**
 * @copyright Copyright (c) 2020 Daniel Calviño Sánchez <danxuliu@gmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { createPinia, PiniaVuePlugin } from 'pinia'
import Vue from 'vue'
import VueObserveVisibility from 'vue-observe-visibility'
import vOutsideEvents from 'vue-outside-events'
import VueShortKey from 'vue-shortkey'
import Vuex from 'vuex'

import { getRequestToken } from '@nextcloud/auth'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'

import PublicShareAuthRequestPasswordButton from './PublicShareAuthRequestPasswordButton.vue'
import PublicShareAuthSidebar from './PublicShareAuthSidebar.vue'

import './init.js'
import store from './store/index.js'

import '@nextcloud/dialogs/dist/index.css'
// Leaflet icon patch
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package
import 'leaflet/dist/leaflet.css'

// eslint-disable-next-line
import 'leaflet-defaulticon-compatibility'

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
// __webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
//__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(PiniaVuePlugin)
Vue.use(Vuex)
Vue.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
Vue.use(vOutsideEvents)
Vue.use(VueObserveVisibility)

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

const requestPasswordVm = new Vue({
	store,
	pinia,
	id: 'talk-video-verification',
	propsData: {
		shareToken: getShareToken(),
	},
	...PublicShareAuthRequestPasswordButton,
})
requestPasswordVm.$mount('#request-password')

const talkSidebarVm = new Vue({
	store,
	pinia,
	...PublicShareAuthSidebar,
})
talkSidebarVm.$mount(document.querySelector('#talk-sidebar'))

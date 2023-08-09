/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
import VueRouter from 'vue-router'
import VueShortKey from 'vue-shortkey'
import Vuex from 'vuex'

import { getRequestToken } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { translate, translatePlural } from '@nextcloud/l10n'
import { generateFilePath } from '@nextcloud/router'

import { options as TooltipOptions } from '@nextcloud/vue/dist/Directives/Tooltip.js'

import App from './App.vue'

import './init.js'
import router from './router/router.js'
import store from './store/index.js'

import '@nextcloud/dialogs/dist/index.css'
// Leaflet icon patch
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package
import 'leaflet/dist/leaflet.css'

// eslint-disable-next-line
import 'leaflet-defaulticon-compatibility'

if (!IS_DESKTOP) {
	// CSP config for webpack dynamic chunk loading
	// eslint-disable-next-line
	// __webpack_nonce__ = btoa(getRequestToken())

	// Correct the root of the app for chunk loading
	// OC.linkTo matches the apps folders
	// OC.generateUrl ensure the index.php (or not)
	// We do not want the index.php since we're loading files
	// eslint-disable-next-line
	// __webpack_public_path__ = generateFilePath('spreed', '', 'js/')
}

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(PiniaVuePlugin)
Vue.use(Vuex)
Vue.use(VueRouter)
Vue.use(VueObserveVisibility)
Vue.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
Vue.use(vOutsideEvents)

const pinia = createPinia()

TooltipOptions.container = '#content-vue'
store.dispatch('setMainContainerSelector', '#content-vue')

const instance = new Vue({
	el: '#content',
	store,
	pinia,
	router,
	propsData: {
		fileInfo: null,
	},
	render: h => h(App),
})

window.store = store

// Setup OCA.Files.Sidebar to be used by the viewer
window.OCA.Files = {}

const Sidebar = function() {
	this.state = {
		file: '',
	}

	store.watch(
		(state, getters) => {
			return getters.getSidebarStatus
		},
		(sidebarShown) => {
			if (!sidebarShown) {
				this.state.file = ''
			}
		}
	)
}

const waitForSidebarToBeOpen = function(sidebarElement, resolve) {
	if ('ontransitionend' in sidebarElement) {
		const resolveOnceSidebarWidthHasChanged = (event) => {
			if (event.propertyName !== 'min-width' && event.propertyName !== 'width' && event.propertyName !== 'max-width') {
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

Sidebar.prototype.open = function(path) {
	// The sidebar is already open, so this can return immediately.
	if (this.state.file) {
		emit('files:sidebar:opened')

		return
	}

	store.commit('showSidebar')
	this.state.file = path

	const sidebarElement = document.getElementById('app-sidebar')

	// The Viewer adjusts its width to the sidebar width once the sidebar has
	// been opened. The sidebar opens with an animation, so a delay is needed
	// before the width can be properly adjusted.
	return new Promise((resolve, reject) => {
		waitForSidebarToBeOpen(sidebarElement, resolve)
	})
}
Sidebar.prototype.close = function() {
	store.dispatch('hideSidebar')
	this.state.file = ''
}
Sidebar.prototype.setFullScreenMode = function(isFullScreen) {
	// Sidebar style is not changed in Talk when the viewer is opened; this is
	// needed only for compatibility with OCA.Files.Sidebar interface.
}

Object.assign(window.OCA.Files, {
	Sidebar: new Sidebar(),
})

// make the instance available to global components that might run on the same page
if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}
OCA.Talk.instance = instance

export default instance

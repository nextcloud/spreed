/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
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

import Vue from 'vue'
import Recording from './Recording.vue'

// Store
import Vuex from 'vuex'
import store from './store/index.js'

// Router
import VueRouter from 'vue-router'
import router from './router/router.js'

// Utils
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

// Directives
import { translate, translatePlural } from '@nextcloud/l10n'
import VueObserveVisibility from 'vue-observe-visibility'
import VueShortKey from 'vue-shortkey'
import vOutsideEvents from 'vue-outside-events'
import { options as TooltipOptions } from '@nextcloud/vue/dist/Directives/Tooltip.js'

// Styles
import '@nextcloud/dialogs/dist/index.css'
import 'leaflet/dist/leaflet.css'

// Leaflet icon patch
import 'leaflet-defaulticon-compatibility/dist/leaflet-defaulticon-compatibility.webpack.css' // Re-uses images from ~leaflet package
// eslint-disable-next-line
import 'leaflet-defaulticon-compatibility'

import {
	signalingGetSettingsForRecording,
	signalingJoinCallForRecording,
	signalingKill,
} from './utils/webrtc/index.js'

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(Vuex)
Vue.use(VueRouter)
Vue.use(VueObserveVisibility)
Vue.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
Vue.use(vOutsideEvents)

TooltipOptions.container = '#call-container'
store.dispatch('setMainContainerSelector', '#call-container')

window.store = store

if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}

const instance = new Vue({
	el: '#content',
	store,
	router,
	render: h => h(Recording),
})

// make the instance available to global components that might run on the same page
OCA.Talk.instance = instance

// Expose functions to be called by the recording server
OCA.Talk.signalingGetSettingsForRecording = signalingGetSettingsForRecording
OCA.Talk.signalingJoinCallForRecording = signalingJoinCallForRecording
OCA.Talk.signalingKill = signalingKill

export default instance

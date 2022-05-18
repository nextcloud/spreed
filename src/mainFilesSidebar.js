/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
import VueObserveVisibility from 'vue-observe-visibility'
import FilesSidebarCallViewApp from './FilesSidebarCallViewApp.vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'
import './init.js'

// Store
import Vuex from 'vuex'
import store from './store/index.js'

// Utils
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

// Directives
import { translate, translatePlural } from '@nextcloud/l10n'
import VueShortKey from 'vue-shortkey'
import vOutsideEvents from 'vue-outside-events'

// Styles
import '@nextcloud/dialogs/styles/toast.scss'
import 'leaflet/dist/leaflet.css'

// Leaflet icon patch
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

Vue.prototype.t = translate
Vue.prototype.n = translatePlural
Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

Vue.use(Vuex)
Vue.use(VueShortKey, { prevent: ['input', 'textarea', 'div'] })
Vue.use(vOutsideEvents)
Vue.use(VueObserveVisibility)

store.dispatch('setMainContainerSelector', '.talkChatTab')

const newCallView = () => new Vue({
	store,
	render: h => h(FilesSidebarCallViewApp),
})

const newTab = () => new Vue({
	store,
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

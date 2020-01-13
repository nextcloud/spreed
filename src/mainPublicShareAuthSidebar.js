/**
 * @copyright Copyright (c) 2020 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
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
import PublicShareAuthRequestPasswordButton from './PublicShareAuthRequestPasswordButton'
import PublicShareAuthSidebar from './PublicShareAuthSidebar'

// Store
import Vuex from 'vuex'
import store from './store'

// Utils
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

// Directives
import { translate, translatePlural } from '@nextcloud/l10n'

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

function adjustLayout() {
	const contentElement = document.createElement('div')
	contentElement.setAttribute('id', 'content')
	document.querySelector('body').append(contentElement)

	contentElement.append(document.querySelector('.wrapper'))
	contentElement.append(document.querySelector('footer'))

	const requestPasswordElement = document.createElement('div')
	requestPasswordElement.setAttribute('id', 'request-password')
	document.querySelector('main').append(requestPasswordElement)

	const talkSidebarElement = document.createElement('div')
	talkSidebarElement.setAttribute('id', 'talk-sidebar')
	document.querySelector('body').append(talkSidebarElement)

	document.querySelector('body').classList.add('talk-sidebar-enabled')
}

adjustLayout()

function getShareToken() {
	const shareTokenElement = document.getElementById('sharingToken')
	return shareTokenElement.value
}

const requestPasswordVm = new Vue({
	store,
	propsData: {
		shareToken: getShareToken(),
	},
	...PublicShareAuthRequestPasswordButton,
})
requestPasswordVm.$mount('#request-password')

const talkSidebarVm = new Vue({
	store,
	...PublicShareAuthSidebar,
})
talkSidebarVm.$mount(document.querySelector('#talk-sidebar'))

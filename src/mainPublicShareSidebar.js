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
import PublicShareSidebar from './PublicShareSidebar'

// Store
import Vuex from 'vuex'
import store from './store'

// Utils
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

// Directives
import { translate, translatePlural } from '@nextcloud/l10n'
import VueShortKey from 'vue-shortkey'

// Styles
import '@nextcloud/dialogs/styles/toast.scss'

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
const sidebarState = {
	isOpen: false,
}

// Open the sidebar by default based on the window width using the same
// threshold as in the main Talk UI (in Talk 7).
if (window.innerWidth > 1111) {
	sidebarState.isOpen = true
}

function addTalkSidebarTrigger() {
	const talkSidebarTriggerElement = document.createElement('button')
	talkSidebarTriggerElement.setAttribute('id', 'talk-sidebar-trigger')
	talkSidebarTriggerElement.setAttribute('class', 'icon-menu-people-white')
	talkSidebarTriggerElement.addEventListener('click', () => {
		sidebarState.isOpen = !sidebarState.isOpen
	})

	// The ".header-right" element may not exist in the public share page if
	// there are no header actions.
	if (!document.querySelector('.header-right')) {
		const headerRightElement = document.createElement('div')
		headerRightElement.setAttribute('class', 'header-right')
		document.querySelector('#header').appendChild(headerRightElement)
	}

	document.querySelector('.header-right').appendChild(talkSidebarTriggerElement)
}

addTalkSidebarTrigger()

function getShareToken() {
	const shareTokenElement = document.getElementById('sharingToken')
	return shareTokenElement.value
}

const talkSidebarVm = new Vue({
	store,
	id: 'talk-chat-tab',
	propsData: {
		shareToken: getShareToken(),
		state: sidebarState,
	},
	...PublicShareSidebar,
})
talkSidebarVm.$mount(document.querySelector('#talk-sidebar'))

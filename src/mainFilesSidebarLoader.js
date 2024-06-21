/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'

import { getRequestToken } from '@nextcloud/auth'
import { generateFilePath } from '@nextcloud/router'

import FilesSidebarTabLoader from './FilesSidebarTabLoader.vue'

// CSP config for webpack dynamic chunk loading
// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

Vue.prototype.OC = OC
Vue.prototype.OCA = OCA

const loaderTab = () => new Vue({
	id: 'talk-chat-tab',
	render: h => h(FilesSidebarTabLoader),
})

const isEnabled = function(fileInfo) {
	if (fileInfo && !fileInfo.isDirectory()) {
		return true
	}

	const token = OCA.Talk.store?.getters.getToken()

	// If the Talk tab can not be displayed then the current conversation is
	// left; this must be done here because "setFileInfo" will not get
	// called with the new file if the tab can not be displayed.
	if (token) {
		OCA.Talk.store?.dispatch('leaveConversation', { token })
	}

	OCA.Talk.store?.dispatch('updateTokenAndFileIdForToken', {
		newToken: null,
		newFileId: null,
	})

	return false
}

if (!window.OCA.Talk) {
	window.OCA.Talk = {}
}
Object.assign(window.OCA.Talk, {
	fileInfo: null,
	loaderTab,
	isFirstLoad: true,
})

// It might be enough to keep the instance only in the Tab object itself,
// without using a shared variable that can be destroyed if a new tab is
// mounted and the previous one was not destroyed yet, as the tabs seem to
// always be properly destroyed. However, this is how it is done for tabs in
// server, so it is done here too just to be safe.
const PrivateTalk = {
	tabInstance: null,
}

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
			id: 'chat',
			name: t('spreed', 'Chat'),
			icon: 'icon-talk',
			enabled: isEnabled,

			async mount(el, fileInfo, context) {
				if (PrivateTalk.tabInstance) {
					PrivateTalk.tabInstance.$destroy()
				}

				// Dirty hack to force the style on parent component
				const tabChat = document.querySelector('#tab-chat')
				tabChat.style.height = '100%'
				// Remove paddding to maximize space for the chat view
				tabChat.style.padding = '0'

				OCA.Talk.fileInfo = this.fileInfo
				if (OCA.Talk.isFirstLoad === true) {
					PrivateTalk.tabInstance = OCA.Talk.loaderTab()
				} else {
					PrivateTalk.tabInstance = OCA.Talk.newTab()
				}
				PrivateTalk.tabInstance.$mount(el)
			},
			update(fileInfo) {
				OCA.Talk.fileInfo = fileInfo
			},
			destroy() {
				OCA.Talk.fileInfo = null
				PrivateTalk.tabInstance.$destroy()
				PrivateTalk.tabInstance = null
			},
		}))
	}
})

export default PrivateTalk

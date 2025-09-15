/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import pinia from './stores/pinia.ts'
import { useTokenStore } from './stores/token.ts'

import './init.js'

/**
 *
 * @param fileInfo
 */
function isEnabled(fileInfo) {
	if (fileInfo && !fileInfo.isDirectory()) {
		return true
	}

	const tokenStore = useTokenStore(pinia)

	// If the Talk tab can not be displayed then the current conversation is
	// left; this must be done here because "setFileInfo" will not get
	// called with the new file if the tab can not be displayed.
	if (tokenStore.token) {
		OCA.Talk.store.dispatch('leaveConversation', { token: tokenStore.token })
	}

	tokenStore.updateTokenAndFileIdForToken('', null)

	return false
}

// It might be enough to keep the instance only in the Tab object itself,
// without using a shared variable that can be destroyed if a new tab is
// mounted and the previous one was not destroyed yet, as the tabs seem to
// always be properly destroyed. However, this is how it is done for tabs in
// server, so it is done here too just to be safe.
let tabInstance = null

window.addEventListener('DOMContentLoaded', () => {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(new OCA.Files.Sidebar.Tab({
			id: 'chat',
			name: t('spreed', 'Chat'),
			icon: 'icon-talk',
			enabled: isEnabled,

			async mount(el, fileInfo, context) {
				if (tabInstance) {
					tabInstance.unmount()
				}

				// Dirty hack to force the style on parent component
				el.style.height = '100%'
				// Remove padding to maximize space for the chat view
				el.style.padding = '0'

				OCA.Talk.fileInfo = this.fileInfo
				tabInstance = OCA.Talk.newTab()
				tabInstance.mount(el)
			},
			update(fileInfo) {
				OCA.Talk.fileInfo = fileInfo
			},
			destroy() {
				OCA.Talk.fileInfo = null
				tabInstance.unmount()
				tabInstance = null
			},
		}))
	}
})

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import { FileType, getSidebar } from '@nextcloud/files'
import { generateFilePath } from '@nextcloud/router'
import { defineAsyncComponent, defineCustomElement } from 'vue'
import IconTalk from '../../img/app-dark.svg?raw'

// CSP config for webpack dynamic chunk loading
__webpack_nonce__ = getCSPNonce()

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// We do not want the index.php since we're loading files
__webpack_public_path__ = generateFilePath('spreed', '', 'js/')

const TAB_TAG_NAME = 'talk-files_sidebar_tab'

const FilesSidebarLoaderApp = defineAsyncComponent(() => import('./FilesSidebar/FilesSidebarLoaderApp.vue'))

getSidebar()?.registerTab({
	id: 'chat',
	displayName: t('spreed', 'Chat'),
	iconSvgInline: IconTalk,
	order: 30,
	enabled: ({ node }) => node.type === FileType.File,
	tagName: TAB_TAG_NAME,
	onInit() {
		window.customElements.define(TAB_TAG_NAME, defineCustomElement(FilesSidebarLoaderApp, { shadowRoot: false }))
	},
})

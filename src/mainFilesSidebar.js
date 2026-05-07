/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import FilesSidebarTabApp from './FilesSidebarTabApp.vue'
import { initializeTalk } from './init.js'
import { createMemoryRouter } from './router/router.ts'
import store from './store/index.js'
import pinia from './stores/pinia.ts'
import { NextcloudGlobalsVuePlugin } from './utils/NextcloudGlobalsVuePlugin.js'

/**
 * Mount a Talk integration app
 *
 * @param container - selector or ref to mount to
 * @param rootProps - Sidebar props
 * @param token - conversation token
 */
export function mountApp(container, rootProps, token) {
	initializeTalk()

	const router = createMemoryRouter()

	const instance = createApp(FilesSidebarTabApp, { ...rootProps, token })
		.use(store)
		.use(pinia)
		.use(router)
		.use(NextcloudGlobalsVuePlugin)

	window.OCA.Talk.instance = instance
	window.OCA.Talk.unmountInstance = function() {
		instance.unmount()
		delete window.OCA.Talk.instance
		delete window.OCA.Talk.unmountInstance
		delete window.OCA.Talk
	}

	instance.mount(container)
}

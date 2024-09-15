/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'

import { emit } from '@nextcloud/event-bus'

import BrowserStorage from '../services/BrowserStorage.js'

export const useSidebarStore = defineStore('sidebar', {
	state: () => ({
		show: BrowserStorage.getItem('sidebarOpen') !== 'false',
	}),

	actions: {
		showSidebar({ activeTab, cache = true } = {}) {
			this.show = true
			if (activeTab && typeof activeTab === 'string') {
				emit('spreed:select-active-sidebar-tab', activeTab)
			}
			if (cache) {
				BrowserStorage.setItem('sidebarOpen', 'true')
			}
		},

		hideSidebar({ cache = true } = {}) {
			this.show = false
			if (cache) {
				BrowserStorage.setItem('sidebarOpen', 'false')
			}
		},
	},
})

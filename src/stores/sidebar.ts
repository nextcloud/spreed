import { emit } from '@nextcloud/event-bus'
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'
import BrowserStorage from '../services/BrowserStorage.js'

export const useSidebarStore = defineStore('sidebar', {
	state: () => ({
		show: BrowserStorage.getItem('sidebarOpen') !== 'false',
	}),

	actions: {
		showSidebar({ activeTab = '', cache = true }: { activeTab?: string, cache?: boolean } = {}) {
			this.show = true
			if (activeTab) {
				emit('spreed:select-active-sidebar-tab', activeTab)
			}
			if (cache) {
				BrowserStorage.setItem('sidebarOpen', 'true')
			}
		},

		hideSidebar({ cache = true }: { cache?: boolean } = {}) {
			this.show = false
			if (cache) {
				BrowserStorage.setItem('sidebarOpen', 'false')
			}
		},
	},
})

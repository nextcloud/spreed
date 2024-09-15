/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { defineStore } from 'pinia'

export const useSidebarStore = defineStore('sidebar', {
	state: () => ({
		show: true,
	}),

	actions: {
		showSidebar() {
			this.show = true
		},

		hideSidebar() {
			this.show = false
		},
	},
})

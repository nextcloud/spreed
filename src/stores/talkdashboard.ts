/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { getDashboardEventRooms } from '../services/dashboardService.ts'
import type { DashboardEventRoom } from '../types/index.ts'

type State = {
    eventrooms: DashboardEventRoom[]
}
export const useTalkDashboardStore = defineStore('talkdashboard', {
	state: (): State => ({
		eventrooms: [],
	}),

	actions: {
		async fetchDashboardEventRooms() {
			try {
				const response = await getDashboardEventRooms()
				this.eventrooms = response.data.ocs.data
				return this.eventrooms
			} catch (error) {
				console.error('Error fetching dashboard event rooms:', error)
			}
		},
	},
})

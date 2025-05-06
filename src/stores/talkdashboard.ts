/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import Vue from 'vue'

import { getDashboardEventRooms } from '../services/dashboardService.ts'
import type { DashboardEventRoom } from '../types/index.ts'

type State = {
	eventrooms: DashboardEventRoom[],
	eventRoomsInitialised: boolean
}
export const useTalkDashboardStore = defineStore('talkdashboard', {
	state: (): State => ({
		eventrooms: [],
		eventRoomsInitialised: false,
	}),

	actions: {
		async fetchDashboardEventRooms() {
			try {
				const response = await getDashboardEventRooms()
				Vue.set(this, 'eventrooms', response.data.ocs.data)
				this.eventRoomsInitialised = true
			} catch (error) {
				console.error('Error fetching dashboard event rooms:', error)
			}
		},
	},
})

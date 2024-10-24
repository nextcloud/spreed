/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'

import { CONVERSATION } from '../constants.js'
import BrowserStorage from '../services/BrowserStorage.js'

export const useCallViewStore = defineStore('callView', {
	state: () => ({
		forceCallView: false,
		isViewerOverlay: false,
		isGrid: false,
		isStripeOpen: true,
		isEmptyCallView: true,
		lastIsGrid: null,
		lastIsStripeOpen: null,
		presentationStarted: false,
		selectedVideoPeerId: null,
		callEndedTimeout: null,
	}),

	getters: {
		callHasJustEnded: (state) => !!state.callEndedTimeout,
	},

	actions: {
		setForceCallView(value) {
			this.forceCallView = value
		},

		setIsViewerOverlay(value) {
			this.isViewerOverlay = value
		},

		setIsEmptyCallView(value) {
			this.isEmptyCallView = value
		},

		setSelectedVideoPeerId(value) {
			this.selectedVideoPeerId = value
		},

		handleJoinCall(conversation) {
			if (!conversation) {
				return
			}
			const gridPreference = BrowserStorage.getItem(`callprefs-${conversation.token}-isgrid`)
			const isGrid = gridPreference === null
				// not defined yet, default to grid view for group/public calls, otherwise speaker view
				? [CONVERSATION.TYPE.GROUP, CONVERSATION.TYPE.PUBLIC].includes(conversation.type)
				: gridPreference === 'true'
			this.setCallViewMode({ token: conversation.token, isGrid, isStripeOpen: true })
		},

		/**
		 * Sets the current call view mode and saves it in preferences.
		 * If clearLast is false, also remembers it in separate properties.
		 *
		 * @param {object} data the wrapping object;
		 * @param {string} data.token current conversation token;
		 * @param {boolean|null} [data.isGrid=null] true for enabled grid mode, false for speaker view;
		 * @param {boolean|null} [data.isStripeOpen=null] true for visible striped mode, false for speaker view;
		 * @param {boolean} [data.clearLast=true] set false to not reset last temporary remembered state;
		 */
		setCallViewMode({ token, isGrid = null, isStripeOpen = null, clearLast = true }) {
			if (clearLast) {
				this.lastIsGrid = null
				this.lastIsStripeOpen = null
			}

			if (isGrid !== null) {
				this.lastIsGrid = this.isGrid
				BrowserStorage.setItem(`callprefs-${token}-isgrid`, isGrid)
				this.isGrid = isGrid
			}

			if (isStripeOpen !== null) {
				this.lastIsStripeOpen = this.isStripeOpen
				this.isStripeOpen = isStripeOpen
			}
		},

		/**
		 * Starts presentation mode.
		 *
		 * Switches off grid mode and closes the stripe.
		 * Remembers the call view state for after the end of the presentation.
		 * @param {string} token current conversation token.
		 */
		startPresentation(token) {
			// don't start twice, this would prevent multiple screen shares to clear the last call view state
			if (this.presentationStarted) {
				return
			}
			this.presentationStarted = true

			this.setCallViewMode({ token, isGrid: false, isStripeOpen: false, clearLast: false })
		},

		/**
		 * Stops presentation mode.
		 *
		 * Restores call view state from before starting the presentation,
		 * given that the last state was not cleared manually.
		 * @param {string} token current conversation token.
		 */
		stopPresentation(token) {
			if (!this.presentationStarted) {
				return
			}
			this.presentationStarted = false

			if (!this.isGrid && !this.isStripeOpen) {
				// User didn't pick grid view during presentation, restore previous state
				this.setCallViewMode({ token, isGrid: this.lastIsGrid, isStripeOpen: this.lastIsStripeOpen, clearLast: false })
			}
		},

		/**
		 * Checks the time difference between the current time and the call end time.
		 * Then, disable the CallButton for the remaining time until 10 seconds after the call ends.
		 * @param {number} timestamp timestamp of callEnded message (in seconds)
		 */
		setCallHasJustEnded(timestamp) {
			const timeDiff = Math.abs(Date.now() - timestamp * 1000)
			if (10000 - timeDiff < 0) {
				return
			}
			clearTimeout(this.callEndedTimeout)
			this.callEndedTimeout = setTimeout(() => {
				this.resetCallHasJustEnded()
			}, Math.max(0, 10000 - timeDiff))
		},

		resetCallHasJustEnded() {
			clearTimeout(this.callEndedTimeout)
			this.callEndedTimeout = null
		}
	},
})

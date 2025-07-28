/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Conversation } from '../types/index.ts'

import { defineStore } from 'pinia'
import { CONVERSATION } from '../constants.ts'
import BrowserStorage from '../services/BrowserStorage.js'
import {
	disableLiveTranscription,
	enableLiveTranscription,
} from '../services/liveTranscriptionService.ts'

type State = {
	forceCallView: boolean
	isViewerOverlay: boolean
	isGrid: boolean
	isStripeOpen: boolean
	isEmptyCallView: boolean
	lastIsGrid: boolean | null
	lastIsStripeOpen: boolean | null
	presentationStarted: boolean
	selectedVideoPeerId: string | null
	callEndedTimeout: NodeJS.Timeout | number | undefined
	isLiveTranscriptionEnabled: boolean
}

type CallViewModePayload = {
	token: string
	isGrid?: boolean | null
	isStripeOpen?: boolean | null
	clearLast?: boolean
}

export const useCallViewStore = defineStore('callView', {
	state: (): State => ({
		forceCallView: false,
		isViewerOverlay: false,
		isGrid: false,
		isStripeOpen: true,
		isEmptyCallView: true,
		lastIsGrid: null,
		lastIsStripeOpen: null,
		presentationStarted: false,
		selectedVideoPeerId: null,
		callEndedTimeout: undefined,
		isLiveTranscriptionEnabled: false,
	}),

	getters: {
		callHasJustEnded: (state) => !!state.callEndedTimeout,
	},

	actions: {
		setForceCallView(value: boolean) {
			this.forceCallView = value
		},

		setIsViewerOverlay(value: boolean) {
			this.isViewerOverlay = value
		},

		setIsEmptyCallView(value: boolean) {
			this.isEmptyCallView = value
		},

		setSelectedVideoPeerId(value: string | null) {
			this.selectedVideoPeerId = value
		},

		handleJoinCall(conversation: Conversation) {
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
		 * @param data the wrapping object;
		 * @param data.token current conversation token;
		 * @param data.isGrid true for enabled grid mode, false for speaker view;
		 * @param data.isStripeOpen true for visible striped mode, false for speaker view;
		 * @param data.clearLast set false to not reset last temporary remembered state;
		 */
		setCallViewMode({ token, isGrid = null, isStripeOpen = null, clearLast = true }: CallViewModePayload) {
			if (clearLast) {
				this.lastIsGrid = null
				this.lastIsStripeOpen = null
			}

			if (isGrid !== null && isGrid !== undefined) {
				this.lastIsGrid = this.isGrid
				BrowserStorage.setItem(`callprefs-${token}-isgrid`, isGrid.toString())
				this.isGrid = isGrid

				if (isGrid) {
					this.setSelectedVideoPeerId(null)
				}
			}

			if (isStripeOpen !== null && isStripeOpen !== undefined) {
				this.lastIsStripeOpen = this.isStripeOpen
				this.isStripeOpen = isStripeOpen
			}
		},

		/**
		 * Starts presentation mode.
		 *
		 * Switches off grid mode and closes the stripe.
		 * Remembers the call view state for after the end of the presentation.
		 * @param token current conversation token.
		 */
		startPresentation(token: string) {
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
		 * @param token current conversation token.
		 */
		stopPresentation(token: string) {
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
		 * @param timestamp timestamp of callEnded message (in seconds)
		 */
		setCallHasJustEnded(timestamp: number) {
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
			this.callEndedTimeout = undefined
		},

		/**
		 * @throws error if live transcription could not be enabled.
		 */
		async enableLiveTranscription(token: string) {
			try {
				await enableLiveTranscription(token)

				this.isLiveTranscriptionEnabled = true
			} catch (error) {
				console.error(error)

				throw error
			}
		},

		/**
		 * @throws error if live transcription could not be enabled.
		 */
		async disableLiveTranscription(token: string) {
			try {
				// Locally disable transcriptions even if they could not be
				// disabled in the server.
				this.isLiveTranscriptionEnabled = false

				await disableLiveTranscription(token)
			} catch (error) {
				console.error(error)

				throw error
			}
		},
	},
})

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	CONVERSATION,
} from '../constants.js'
import BrowserStorage from '../services/BrowserStorage.js'

const state = () => ({
	forceCallView: false,
	isViewerOverlay: false,
	isGrid: false,
	isStripeOpen: true,
	isEmptyCallView: true,
	lastIsGrid: null,
	lastIsStripeOpen: null,
	presentationStarted: false,
	selectedVideoPeerId: null,
	qualityWarningTooltipDismissed: false,
	participantRaisedHands: {},
	backgroundImageAverageColorCache: {},
})

const getters = {
	forceCallView: (state) => state.forceCallView,
	isViewerOverlay: (state) => state.isViewerOverlay,
	isGrid: (state) => state.isGrid,
	isStripeOpen: (state) => state.isStripeOpen,
	isEmptyCallView: (state) => state.isEmptyCallView,
	lastIsGrid: (state) => state.lastIsGrid,
	lastIsStripeOpen: (state) => state.lastIsStripeOpen,
	presentationStarted: (state) => state.presentationStarted,
	selectedVideoPeerId: (state) => {
		return state.selectedVideoPeerId
	},
	isQualityWarningTooltipDismissed: (state) => state.qualityWarningTooltipDismissed,
	participantRaisedHandList: (state) => {
		return state.participantRaisedHands
	},
	getParticipantRaisedHand: (state) => (sessionIds) => {
		for (let i = 0; i < sessionIds.length; i++) {
			if (state.participantRaisedHands[sessionIds[i]]) {
				// note: only the raised states are stored, so no need to confirm
				return state.participantRaisedHands[sessionIds[i]]
			}
		}

		return { state: false, timestamp: null }
	},
	getCachedBackgroundImageAverageColor: (state) => (videoBackgroundId) => {
		return state.backgroundImageAverageColorCache[videoBackgroundId]
	},
}

const mutations = {

	setForceCallView(state, value) {
		state.forceCallView = value
	},
	isViewerOverlay(state, value) {
		state.isViewerOverlay = value
	},
	isGrid(state, value) {
		state.isGrid = value
	},
	isStripeOpen(state, value) {
		state.isStripeOpen = value
	},
	isEmptyCallView(state, value) {
		state.isEmptyCallView = value
	},
	lastIsGrid(state, value) {
		state.lastIsGrid = value
	},
	lastIsStripeOpen(state, value) {
		state.lastIsStripeOpen = value
	},
	selectedVideoPeerId(state, value) {
		state.selectedVideoPeerId = value
	},
	presentationStarted(state, value) {
		state.presentationStarted = value
	},
	setQualityWarningTooltipDismissed(state, { qualityWarningTooltipDismissed }) {
		state.qualityWarningTooltipDismissed = qualityWarningTooltipDismissed
	},
	setParticipantHandRaised(state, { sessionId, raisedHand }) {
		if (!sessionId) {
			throw new Error('Missing or empty sessionId argument in call to setParticipantHandRaised')
		}
		if (raisedHand && raisedHand.state) {
			state.participantRaisedHands[sessionId] = raisedHand
		} else {
			delete state.participantRaisedHands[sessionId]
		}
	},
	clearParticipantHandRaised(state) {
		state.participantRaisedHands = {}
	},
	setCachedBackgroundImageAverageColor(state, { videoBackgroundId, backgroundImageAverageColor }) {
		state.backgroundImageAverageColorCache[videoBackgroundId] = backgroundImageAverageColor
	},
	clearBackgroundImageAverageColorCache(state) {
		state.backgroundImageAverageColorCache = {}
	},
}

const actions = {
	setForceCallView(context, value) {
		context.commit('setForceCallView', value)
	},

	selectedVideoPeerId(context, value) {
		context.commit('selectedVideoPeerId', value)
	},

	joinCall(context, { token }) {
		let isGrid = BrowserStorage.getItem('callprefs-' + token + '-isgrid')
		if (isGrid === null) {
			const conversationType = context.getters.conversations[token].type
			// default to grid view for group/public calls, otherwise speaker view
			isGrid = (conversationType === CONVERSATION.TYPE.GROUP
				|| conversationType === CONVERSATION.TYPE.PUBLIC)
		} else {
			// BrowserStorage.getItem returns a string instead of a boolean
			isGrid = (isGrid === 'true')
		}
		context.dispatch('setCallViewMode', { isGrid, isStripeOpen: true })

		context.commit('setQualityWarningTooltipDismissed', { qualityWarningTooltipDismissed: false })
	},

	leaveCall(context) {
		// clear raised hands as they were specific to the call
		context.commit('clearParticipantHandRaised')

		context.commit('clearBackgroundImageAverageColorCache')
	},

	/**
	 * Sets the current call view mode and saves it in preferences.
	 * If clearLast is false, also remembers it in separate properties.
	 *
	 * @param {object} context default store context;
	 * @param {object} data the wrapping object;
	 * @param {boolean|null} [data.isViewerOverlay=null] true viewer overlay mode;
	 * @param {boolean|null} [data.isGrid=null] true for enabled grid mode, false for speaker view;
	 * @param {boolean|null} [data.isStripeOpen=null] true for visible striped mode, false for speaker view;
	 * @param {boolean} [data.clearLast=true] set false to not reset last temporary remembered state;
	 */
	setCallViewMode(context, { isGrid = null, isStripeOpen = null, isViewerOverlay = null, clearLast = true }) {
		if (isViewerOverlay !== null) {
			context.commit('isViewerOverlay', isViewerOverlay)
			return
		}

		if (clearLast) {
			context.commit('lastIsGrid', null)
			context.commit('lastIsStripeOpen', null)
		}

		if (isGrid !== null) {
			context.commit('lastIsGrid', context.getters.isGrid)
			BrowserStorage.setItem('callprefs-' + context.getters.getToken() + '-isgrid', isGrid)
			context.commit('isGrid', isGrid)
		}

		if (isStripeOpen !== null) {
			context.commit('lastIsStripeOpen', context.getters.isStripeOpen)
			context.commit('isStripeOpen', isStripeOpen)
		}
	},

	setParticipantHandRaised(context, { sessionId, raisedHand }) {
		context.commit('setParticipantHandRaised', { sessionId, raisedHand })
	},

	setCachedBackgroundImageAverageColor(context, { videoBackgroundId, backgroundImageAverageColor }) {
		context.commit('setCachedBackgroundImageAverageColor', { videoBackgroundId, backgroundImageAverageColor })
	},

	/**
	 * Starts presentation mode.
	 *
	 * Switches off grid mode and closes the stripe.
	 * Remembers the call view state for after the end of the presentation.
	 *
	 * @param {object} context default store context;
	 */
	startPresentation(context) {
		// don't start twice, this would prevent multiple
		// screen shares to clear the last call view state
		if (context.getters.presentationStarted) {
			return
		}

		context.commit('presentationStarted', true)
		// switch off grid mode during presentation and collapse
		// the stripe to focus on the screen share, but continue remembering
		// the last state
		context.dispatch('setCallViewMode', {
			isGrid: false,
			isStripeOpen: false,
			clearLast: false,
		})
	},

	/**
	 * Stops presentation mode.
	 *
	 * Restores call view state from before starting the presentation, given
	 * that the last state was not cleared manually.
	 *
	 * @param {object} context default store context;
	 */
	stopPresentation(context) {
		if (!context.getters.presentationStarted) {
			return
		}
		if (!context.getters.isGrid && !context.getters.isStripeOpen) {
			// User didn't pick grid view during presentation
			// restore previous state
			context.dispatch('setCallViewMode', {
				isGrid: context.getters.lastIsGrid,
				isStripeOpen: context.getters.lastIsStripeOpen,
				clearLast: false,
			})
		}
		context.commit('presentationStarted', false)
	},

	dismissQualityWarningTooltip(context) {
		context.commit('setQualityWarningTooltipDismissed', { qualityWarningTooltipDismissed: true })
	},

	isEmptyCallView(context, value) {
		context.commit('isEmptyCallView', value)
	},
}

export default { state, mutations, getters, actions }

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { PRIVACY } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { fetchSignalingSettings } from '../../services/signalingService.js'
import { getBrowseSessionRequestConfig, resetBrowseSessionTabId } from '../../services/talkSessionUniqueTabId.ts'
import store from '../../store/index.js'
import Signaling from '../signaling.js'
import SignalingTypingHandler from '../SignalingTypingHandler.js'

/**
 * Secondary, lightweight signaling connection used to keep a conversation
 * live-updated (new messages via chat-relay, typing indicators) while it is
 * only being *browsed* during a call that is held by the primary connection in
 * another conversation.
 *
 * It intentionally never joins a call, never sets up WebRTC/media, and does not
 * leak its events onto the global EventBus (only the chat-relay related ones,
 * which are token-scoped, reach the app). The primary connection - and the
 * call it holds - is therefore never touched.
 *
 * Only the High-Performance Backend (standalone signaling) supports this. With
 * the internal signaling there is no cheap second connection, so callers should
 * fall back to the REST/polling browsing behaviour.
 */

const enableTypingIndicators = getTalkConfig('local', 'chat', 'typing-privacy') === PRIVACY.PUBLIC

let browseSignaling = null
let browseTypingHandler = null
let browseToken = null
let participantsRefreshTimeout = null
/** Serializes mount/switch/unmount so overlapping navigations cannot race. */
let pendingOperation = Promise.resolve()

/**
 * Token currently browsed with a live secondary session, or null.
 *
 * @return {string|null}
 */
function getBrowseSignalingToken() {
	return browseToken
}

/**
 * REST-join the conversation on a *separate* Talk session (secondary tab id),
 * so the primary session that holds the call is left untouched.
 *
 * @param {string} token conversation token
 * @return {Promise<string|null>} the Nextcloud session id, or null on failure
 */
async function restJoinBrowseSession(token) {
	try {
		const response = await axios.post(
			generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }),
			{ force: false },
			getBrowseSessionRequestConfig(),
		)
		return response.data.ocs.data.sessionId
	} catch (error) {
		console.error('[browseSignaling] Failed to open browse session for', token, error)
		return null
	}
}

/**
 * REST-leave the secondary Talk session.
 *
 * @param {string} token conversation token
 */
async function restLeaveBrowseSession(token) {
	try {
		await axios.delete(
			generateOcsUrl('apps/spreed/api/v4/room/{token}/participants/active', { token }),
			getBrowseSessionRequestConfig(),
		)
	} catch (error) {
		console.warn('[browseSignaling] Failed to close browse session for', token, error)
	}
}

/**
 * Tear down the current secondary session, if any.
 */
async function teardown() {
	clearTimeout(participantsRefreshTimeout)
	participantsRefreshTimeout = null

	if (browseTypingHandler) {
		browseTypingHandler.destroy()
		browseTypingHandler = null
	}

	const token = browseToken
	if (browseSignaling) {
		try {
			await browseSignaling.leaveRoom(token)
		} catch (error) {
			console.warn('[browseSignaling] leaveRoom failed', error)
		}
		browseSignaling.disconnect()
		browseSignaling = null
	}

	if (token) {
		await restLeaveBrowseSession(token)
	}

	browseToken = null
	resetBrowseSessionTabId()
}

/**
 * Bring up a secondary session for the given conversation.
 *
 * @param {string} token conversation token
 * @return {Promise<boolean>} true if a live session was established, false if
 *         the caller should fall back to REST/polling browsing
 */
async function setup(token) {
	const settings = await fetchSignalingSettingsForBrowse(token)
	if (!settings) {
		return false
	}

	if (settings.signalingMode === 'internal') {
		// No High-Performance Backend: a second connection would be a second
		// long-polling loop with no chat-relay. Fall back to REST/polling.
		return false
	}

	const sessionId = await restJoinBrowseSession(token)
	if (!sessionId) {
		return false
	}

	browseSignaling = Signaling.createConnection(settings)
	// Keep the secondary connection from interfering with the primary one:
	// forward only the "supportedFeatures" event, which the chat needs to know
	// that live chat-relay is available for the browsed conversation.
	browseSignaling.setEventBusEmitAllowlist(['supportedFeatures'])

	if (enableTypingIndicators) {
		browseTypingHandler = new SignalingTypingHandler()
		browseTypingHandler.setSignaling(browseSignaling)
	}

	// The browsed conversation is not joined the normal way, so the usual
	// participant fetching (useGetParticipants) never runs for it. Load and keep
	// its participant list fresh from the browse session's own room events, so
	// participant-dependent UI works while browsing - notably the typing
	// indicator, which matches typing signals against known participant sessions.
	const refreshBrowseParticipants = () => {
		if (browseToken !== token) {
			return
		}
		clearTimeout(participantsRefreshTimeout)
		participantsRefreshTimeout = setTimeout(() => {
			if (browseToken === token) {
				store.dispatch('fetchParticipants', { token })
			}
		}, 500)
	}
	browseSignaling.on('usersInRoom', refreshBrowseParticipants)
	browseSignaling.on('usersJoined', refreshBrowseParticipants)
	browseSignaling.on('usersLeft', refreshBrowseParticipants)

	browseToken = token

	// joinRoom defers internally until the hello handshake completed.
	browseSignaling.joinRoom(token, sessionId)

	// Initial participant load (covers participants already present before we
	// joined, in case no incoming room event follows).
	store.dispatch('fetchParticipants', { token })

	return true
}

/**
 * Fetch signaling settings through the secondary Talk session.
 *
 * @param {string} token conversation token
 * @return {Promise<object|null>}
 */
async function fetchSignalingSettingsForBrowse(token) {
	try {
		const response = await fetchSignalingSettings({ token }, getBrowseSessionRequestConfig())
		const settings = response.data.ocs.data
		settings.token = token
		return settings
	} catch (error) {
		console.error('[browseSignaling] Failed to fetch signaling settings for', token, error)
		return null
	}
}

/**
 * Start (or switch to) a live secondary session for the browsed conversation.
 * Safe to call repeatedly; only one secondary session exists at a time.
 *
 * @param {string} token conversation token to browse live
 * @return {Promise<boolean>} whether a live session is active for the token
 */
function mountBrowseSignaling(token) {
	pendingOperation = pendingOperation.then(async () => {
		if (browseToken === token && browseSignaling) {
			return true
		}
		if (browseToken) {
			await teardown()
		}
		return setup(token)
	}).catch((error) => {
		console.error('[browseSignaling] mount failed', error)
		return false
	})
	return pendingOperation
}

/**
 * Tear down the secondary session (call when returning to the call
 * conversation or when the call ends).
 *
 * @return {Promise<void>}
 */
function unmountBrowseSignaling() {
	pendingOperation = pendingOperation.then(async () => {
		if (browseToken) {
			await teardown()
		}
	}).catch((error) => {
		console.error('[browseSignaling] unmount failed', error)
	})
	return pendingOperation
}

export {
	getBrowseSignalingToken,
	mountBrowseSignaling,
	unmountBrowseSignaling,
}

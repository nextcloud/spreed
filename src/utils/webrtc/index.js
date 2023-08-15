/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Axios from '@nextcloud/axios'
import { getCapabilities } from '@nextcloud/capabilities'

import { PARTICIPANT, PRIVACY, VIRTUAL_BACKGROUND } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { fetchSignalingSettings } from '../../services/signalingService.js'
import store from '../../store/index.js'
import CancelableRequest from '../cancelableRequest.js'
import Signaling from '../signaling.js'
import SignalingTypingHandler from '../SignalingTypingHandler.js'
import CallAnalyzer from './analyzers/CallAnalyzer.js'
import MediaDevicesManager from './MediaDevicesManager.js'
import CallParticipantCollection from './models/CallParticipantCollection.js'
import LocalCallParticipantModel from './models/LocalCallParticipantModel.js'
import LocalMediaModel from './models/LocalMediaModel.js'
import SentVideoQualityThrottler from './SentVideoQualityThrottler.js'
import './shims/MediaStream.js'
import './shims/MediaStreamTrack.js'
import SpeakingStatusHandler from './SpeakingStatusHandler.js'
import initWebRtc from './webrtc.js'

let webRtc = null
const callParticipantCollection = new CallParticipantCollection()
const localCallParticipantModel = new LocalCallParticipantModel()
const localMediaModel = new LocalMediaModel()
const mediaDevicesManager = new MediaDevicesManager()
let callAnalyzer = null
let sentVideoQualityThrottler = null
let speakingStatusHandler = null

// This does not really belongs here, as it is unrelated to WebRTC, but it is
// included here for the time being until signaling and WebRTC are split.
const enableTypingIndicators = getCapabilities()?.spreed?.config?.chat?.['typing-privacy'] === PRIVACY.PUBLIC
const signalingTypingHandler = enableTypingIndicators ? new SignalingTypingHandler(store) : null

let cancelFetchSignalingSettings = null
let signaling = null
let tokensInSignaling = {}

/**
 * @param {string} token The token of the conversation to get the signaling settings for
 * @param {object} options The additional options for the request
 */
async function getSignalingSettings(token, options) {
	// If getSignalingSettings is called again while a previous one was still
	// being executed the previous one is cancelled.
	if (cancelFetchSignalingSettings) {
		cancelFetchSignalingSettings('canceled')
		cancelFetchSignalingSettings = null
	}

	const { request, cancel } = CancelableRequest(fetchSignalingSettings)
	cancelFetchSignalingSettings = cancel

	let settings = null
	try {
		const response = await request({ token }, options)
		settings = response.data.ocs.data

		settings.token = token

		cancelFetchSignalingSettings = null
	} catch (exception) {
		if (Axios.isCancel(exception)) {
			console.debug('Getting the signaling settings for ' + token + ' was cancelled by a newer getSignalingSettings')
		} else {
			console.warn('Failed to get the signaling settings for ' + token)
		}
	}

	return settings
}

/**
 * @param {string} token The token of the conversation to get the signaling settings for
 * @param {string} random A string of at least 32 characters
 * @param {string} checksum The SHA-256 HMAC of random with the secret of the
 *        recording server
 */
async function signalingGetSettingsForRecording(token, random, checksum) {
	const options = {
		headers: {
			'Talk-Recording-Random': random,
			'Talk-Recording-Checksum': checksum,
		},
	}

	return getSignalingSettings(token, options)
}

/**
 * @param {string} token The token of the conversation to connect to
 */
async function connectSignaling(token) {
	const settings = await getSignalingSettings(token)
	if (!settings) {
		return
	}

	if (signaling && signaling.settings.server !== settings.server) {
		if (webRtc) {
			webRtc.disconnect()
			webRtc = null
		}
		signaling.disconnect()
		signaling = null

		tokensInSignaling = {}
	}

	if (!signaling) {
		signaling = Signaling.createConnection(settings)
		signaling.on('updateSettings', async function() {
			const settings = await getSignalingSettings(token)
			console.debug('Received updated settings', settings)
			signaling.settings = settings
		})

		signalingTypingHandler?.setSignaling(signaling)
	}

	tokensInSignaling[token] = true
}

let pendingJoinCallToken = null
let startedCall = null
let failedToStartCall = null

/**
 * @param {object} signaling The signaling object
 * @param {object} configuration Media to connect with
 * @param {boolean} silent Whether the call should trigger a notifications and
 * sound for other participants or not
 */
function startCall(signaling, configuration, silent) {
	let flags = PARTICIPANT.CALL_FLAG.IN_CALL
	if (configuration) {
		if (configuration.audio) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
		}
		if (configuration.video && signaling.getSendVideoIfAvailable()) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
		}
	}

	signaling.joinCall(pendingJoinCallToken, flags, silent).then(() => {
		startedCall(flags)
	}).catch(error => {
		failedToStartCall(error)
	})
}

/**
 *
 */
function setupWebRtc() {
	if (webRtc) {
		return
	}

	webRtc = initWebRtc(signaling, callParticipantCollection, localCallParticipantModel)
	localCallParticipantModel.setWebRtc(webRtc)
	localMediaModel.setWebRtc(webRtc)

	signaling.on('sessionId', sessionId => {
		localCallParticipantModel.setPeerId(sessionId)
	})
}

/**
 * Join the given conversation on the respective signaling server with the given sessionId
 *
 * @param {string} token Conversation to join
 * @param {string} sessionId Session id to join with
 * @return {Promise<void>}
 */
async function signalingJoinConversation(token, sessionId) {
	await connectSignaling(token)
	if (tokensInSignaling[token]) {
		await signaling.joinRoom(token, sessionId)
	}
}

/**
 * Join the call of the given conversation
 *
 * @param {string} token Conversation to join the call
 * @param {number} flags Bitwise combination of PARTICIPANT.CALL_FLAG
 * @param {boolean} silent Whether the call should trigger a notifications and
 * sound for other participants or not
 * @return {Promise<void>} Resolved with the actual flags based on the
 *          available media
 */
async function signalingJoinCall(token, flags, silent) {
	if (tokensInSignaling[token]) {
		pendingJoinCallToken = token

		setupWebRtc()

		sentVideoQualityThrottler = new SentVideoQualityThrottler(localMediaModel, callParticipantCollection, webRtc.webrtc._videoTrackConstrainer)
		speakingStatusHandler = new SpeakingStatusHandler(store, localMediaModel, callParticipantCollection)

		if (signaling.hasFeature('mcu')) {
			callAnalyzer = new CallAnalyzer(localMediaModel, localCallParticipantModel, callParticipantCollection)
		} else {
			callAnalyzer = new CallAnalyzer(localMediaModel, null, callParticipantCollection)
		}

		const _signaling = signaling

		return new Promise((resolve, reject) => {
			startedCall = resolve
			failedToStartCall = reject

			// The previous state might be wiped after the media is started, so
			// it should be saved now.
			const enableAudio = !BrowserStorage.getItem('audioDisabled_' + token)
			const enableVideo = !BrowserStorage.getItem('videoDisabled_' + token)
			const enableVirtualBackground = !!BrowserStorage.getItem('virtualBackgroundEnabled_' + token)
			const virtualBackgroundType = BrowserStorage.getItem('virtualBackgroundType_' + token)
			const virtualBackgroundBlurStrength = BrowserStorage.getItem('virtualBackgroundBlurStrength_' + token)
			const virtualBackgroundUrl = BrowserStorage.getItem('virtualBackgroundUrl_' + token)

			if (enableAudio) {
				localMediaModel.enableAudio()
			} else {
				localMediaModel.disableAudio()
			}
			if (enableVideo) {
				localMediaModel.enableVideo()
			} else {
				localMediaModel.disableVideo()
			}
			if (enableVirtualBackground) {
				localMediaModel.enableVirtualBackground()
			} else {
				localMediaModel.disableVirtualBackground()
			}
			if (virtualBackgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
				localMediaModel.setVirtualBackgroundImage(virtualBackgroundUrl)
			} else if (virtualBackgroundType === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.VIDEO) {
				localMediaModel.setVirtualBackgroundVideo(virtualBackgroundUrl)
			} else {
				localMediaModel.setVirtualBackgroundBlur(virtualBackgroundBlurStrength)
			}

			const startCallOnceLocalMediaStarted = (configuration) => {
				webRtc.off('localMediaStarted', startCallOnceLocalMediaStarted)
				webRtc.off('localMediaError', startCallOnceLocalMediaError)

				startCall(_signaling, configuration, silent)
			}
			const startCallOnceLocalMediaError = () => {
				webRtc.off('localMediaStarted', startCallOnceLocalMediaStarted)
				webRtc.off('localMediaError', startCallOnceLocalMediaError)

				startCall(_signaling, null, silent)
			}

			// ".once" can not be used, as both handlers need to be removed when
			// just one of them is executed.
			webRtc.on('localMediaStarted', startCallOnceLocalMediaStarted)
			webRtc.on('localMediaError', startCallOnceLocalMediaError)

			webRtc.startMedia(token, flags)
		})
	}
}

/**
 * Wait until the signaling connection succeed.
 *
 * If the authentication fails an error is thrown and the waiting aborted.
 *
 * @param {object} signaling the signaling to check its connection.
 */
async function signalingIsConnected(signaling) {
	let signalingConnectionSucceeded
	let signalingConnectionFailed

	const signalingConnection = new Promise((resolve, reject) => {
		signalingConnectionSucceeded = resolve
		signalingConnectionFailed = reject
	})

	const signalingConnectionSucceededOnConnect = () => {
		signaling.off('connect', signalingConnectionSucceededOnConnect)
		signaling.off('error', signalingConnectionFailedOnInvalidToken)

		signalingConnectionSucceeded()
	}

	const signalingConnectionFailedOnInvalidToken = (error) => {
		if (error.code !== 'invalid_token') {
			return
		}

		signaling.off('connect', signalingConnectionSucceededOnConnect)
		signaling.off('error', signalingConnectionFailedOnInvalidToken)

		signalingConnectionFailed(new Error('Authentication failed for signaling server: ' + signaling.settings.server))
	}

	signaling.on('connect', signalingConnectionSucceededOnConnect)
	signaling.on('error', signalingConnectionFailedOnInvalidToken)

	await signalingConnection
}

/**
 * Join the call of the given conversation for recording with an internal
 * client.
 *
 * The authentication parameters for the internal client must contain:
 * - random: string of at least 32 characters
 * - token: the SHA-256 HMAC of random with the internal secret of the signaling
 *   server
 * - backend: the URL of the Nextcloud server that the conversation belongs to
 *
 * @param {string} token Conversation to join the call
 * @param {object} settings the settings used to create the signaling connection
 * @param {object} internalClientAuthParams the authentication parameters for
 *        the internal client
 * @return {Promise<void>} Resolved with the actual flags based on the
 *          available media
 */
async function signalingJoinCallForRecording(token, settings, internalClientAuthParams) {
	mediaDevicesManager.set('audioInputId', null)
	mediaDevicesManager.set('videoInputId', null)

	settings.helloAuthParams.internal = internalClientAuthParams

	signaling = Signaling.createConnection(settings)

	await signalingIsConnected(signaling)

	// The default call flags for internal clients include audio, so they must
	// be downgraded to just "in call" to prevent other participants from trying
	// to connect to the recording participant.
	// This must be done before joining the room to ensure that other
	// participants will see the correct flags from the beginning.
	signaling.doSend({
		type: 'internal',
		internal: {
			type: 'incall',
			incall: {
				incall: PARTICIPANT.CALL_FLAG.IN_CALL,
			},
		},
	})

	// No Nextcloud session ID is needed to join the room with an internal
	// client.
	await signaling.joinRoom(token)

	pendingJoinCallToken = token

	setupWebRtc()

	const _signaling = signaling

	return new Promise((resolve, reject) => {
		startedCall = resolve
		failedToStartCall = reject

		const silent = true

		localMediaModel.disableAudio()
		localMediaModel.disableVideo()
		localMediaModel.disableVirtualBackground()

		const startCallOnceLocalMediaStarted = (configuration) => {
			webRtc.off('localMediaStarted', startCallOnceLocalMediaStarted)
			webRtc.off('localMediaError', startCallOnceLocalMediaError)

			startCall(_signaling, configuration, silent)
		}
		const startCallOnceLocalMediaError = () => {
			webRtc.off('localMediaStarted', startCallOnceLocalMediaStarted)
			webRtc.off('localMediaError', startCallOnceLocalMediaError)

			startCall(_signaling, null, silent)
		}

		// ".once" can not be used, as both handlers need to be removed when
		// just one of them is executed.
		webRtc.on('localMediaStarted', startCallOnceLocalMediaStarted)
		webRtc.on('localMediaError', startCallOnceLocalMediaError)

		webRtc.startMedia(token, PARTICIPANT.CALL_FLAG.IN_CALL)
	})
}

/**
 * Leave the call of the given conversation
 *
 * @param {string} token Conversation to leave the call
 * @param {boolean} all Whether to end the meeting for all
 * @return {Promise<void>}
 */
async function signalingLeaveCall(token, all = false) {
	sentVideoQualityThrottler.destroy()
	sentVideoQualityThrottler = null

	speakingStatusHandler.destroy()
	speakingStatusHandler = null

	callAnalyzer.destroy()
	callAnalyzer = null

	if (tokensInSignaling[token]) {
		await signaling.leaveCall(token, false, all)
	}
}

/**
 * Leave the given conversation on the respective signaling server
 *
 * @param {string} token Conversation to leave
 * @return {Promise<void>}
 */
async function signalingLeaveConversation(token) {
	if (tokensInSignaling[token]) {
		await signaling.leaveRoom(token)
	}
}

/**
 * Immediately kill the signaling connection synchronously
 * This should be called only in the unload handler
 */
function signalingKill() {
	if (signaling) {
		signaling.disconnect()
	}
}

/**
 * Sets whether the current participant is typing.
 *
 * @param {boolean} typing whether the current participant is typing.
 */
function signalingSetTyping(typing) {
	signalingTypingHandler?.setTyping(typing)
}

export {
	callParticipantCollection,
	localCallParticipantModel,
	localMediaModel,

	mediaDevicesManager,

	callAnalyzer,

	signalingGetSettingsForRecording,
	signalingJoinConversation,
	signalingJoinCall,
	signalingJoinCallForRecording,
	signalingLeaveCall,
	signalingLeaveConversation,
	signalingKill,

	signalingSetTyping,
}

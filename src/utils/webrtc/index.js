/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @license GNU AGPL version 3 or any later version
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

import Signaling from '../signaling'
import initWebRtc from './webrtc'
import CallParticipantCollection from './models/CallParticipantCollection'
import LocalCallParticipantModel from './models/LocalCallParticipantModel'
import LocalMediaModel from './models/LocalMediaModel'
import SentVideoQualityThrottler from './SentVideoQualityThrottler'
import { PARTICIPANT } from '../../constants'
import { EventBus } from '../../services/EventBus'
import { fetchSignalingSettings } from '../../services/signalingService'

let signalingToken = null
let webRtc = null
let settings = null
let currentSignalingServer = null
const callParticipantCollection = new CallParticipantCollection()
const localCallParticipantModel = new LocalCallParticipantModel()
const localMediaModel = new LocalMediaModel()
let sentVideoQualityThrottler = null

const signalingConnections = {}
const pendingSignalingConnections = {}

async function loadSignalingSettings(token) {
	const response = await fetchSignalingSettings(token)
	settings = response.data.ocs.data
}

async function connectSignaling(token) {
	if (token in signalingConnections) {
		return
	}

	if (token in pendingSignalingConnections) {
		return pendingSignalingConnections[token]
	}

	pendingSignalingConnections[token] = new Promise((resolve, reject) => {
		signalingConnections[token] = Signaling.createConnection(settings)
		EventBus.$emit('signalingConnectionEstablished')
		delete pendingSignalingConnections[token]
		resolve(signalingConnections[token])
	})

	return pendingSignalingConnections[token]
}

async function getSignaling(token) {
	if (signalingToken !== token) {
		await loadSignalingSettings(token)

		if (currentSignalingServer !== settings.url && token in signalingConnections) {
			signalingConnections[token].disconnect()
			delete signalingConnections[token]
			delete pendingSignalingConnections[token]
		}

		signalingToken = token
		currentSignalingServer = settings.url

		if (!(token in signalingConnections)) {
			await connectSignaling(token)
		}
	}

	return signalingConnections[token]
}

let currentToken = null
let startedCall = null

function startCall(token, configuration) {
	let flags = PARTICIPANT.CALL_FLAG.IN_CALL
	if (configuration) {
		if (configuration.audio) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
		}
		if (configuration.video && signalingConnections[token].getSendVideoIfAvailable()) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
		}
	}

	signalingConnections[token].joinCall(currentToken, flags)

	startedCall()
}

function setupWebRtc(token) {
	if (webRtc) {
		return
	}

	webRtc = initWebRtc(signalingConnections[token], callParticipantCollection)
	localCallParticipantModel.setWebRtc(webRtc)
	localMediaModel.setWebRtc(webRtc)

	webRtc.on('localMediaStarted', (configuration) => {
		startCall(token, configuration)
	})
	webRtc.on('localMediaError', () => {
		startCall(token, null)
	})
}

/**
 * Join the given conversation on the respective signaling server with the given sessionId
 *
 * @param {string} token Conversation to join
 * @param {string} sessionId Session id to join with
 * @returns {Promise<void>}
 */
async function signalingJoinConversation(token, sessionId) {
	await getSignaling(token)
	if (token in signalingConnections) {
		await signalingConnections[token].joinRoom(token, sessionId)
	} else {
		pendingSignalingConnections[token].then(() => {
			signalingConnections[token].joinRoom(token, sessionId)
		})
	}
}

/**
 * Join the call of the given conversation
 *
 * @param {string} token Conversation to join the call
 * @returns {Promise<void>}
 */
async function signalingJoinCall(token) {
	await getSignaling(token)

	setupWebRtc(token)

	currentToken = token

	sentVideoQualityThrottler = new SentVideoQualityThrottler(localMediaModel, callParticipantCollection)

	return new Promise((resolve, reject) => {
		startedCall = resolve

		webRtc.startMedia(token)
	})
}

/**
 * Leave the call of the given conversation
 *
 * @param {string} token Conversation to leave the call
 * @returns {Promise<void>}
 */
async function signalingLeaveCall(token) {
	sentVideoQualityThrottler.destroy()
	sentVideoQualityThrottler = null

	if (token in signalingConnections) {
		await signalingConnections[token].leaveCall(token)
	}
}

/**
 * Leave the given conversation on the respective signaling server
 *
 * @param {string} token Conversation to leave
 * @returns {Promise<void>}
 */
async function signalingLeaveConversation(token) {
	if (token in signalingConnections) {
		await signalingConnections[token].leaveRoom(token)
	}
}

/**
 * Immediately kill the signaling connection synchronously
 * This should be called only in the unload handler
 */
function signalingKill() {
	Object.keys(signalingConnections).forEach((token) => {
		signalingConnections[token].disconnect()
	})
}

export {
	callParticipantCollection,
	localCallParticipantModel,
	localMediaModel,

	signalingJoinConversation,
	signalingJoinCall,
	signalingLeaveCall,
	signalingLeaveConversation,
	signalingKill,
}

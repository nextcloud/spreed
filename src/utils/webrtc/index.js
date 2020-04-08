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

let signaling = null
let signalingToken = null
let webRtc = null
const callParticipantCollection = new CallParticipantCollection()
const localCallParticipantModel = new LocalCallParticipantModel()
const localMediaModel = new LocalMediaModel()
let sentVideoQualityThrottler = null

let pendingConnectSignaling = null

async function connectSignaling(token) {
	if (signalingToken === token) {
		if (signaling) {
			return
		}

		if (pendingConnectSignaling) {
			return pendingConnectSignaling
		}
	} else if (signaling) {
		// Changing signaling connection
		signaling.disconnect()
		signaling = null
	}

	signalingToken = token

	pendingConnectSignaling = new Promise((resolve, reject) => {
		Signaling.loadSettings(token).then(() => {
			signaling = Signaling.createConnection()

			EventBus.$emit('signalingConnectionEstablished')

			pendingConnectSignaling = null

			resolve()
		})
	})

	return pendingConnectSignaling
}

async function getSignaling(token) {
	await connectSignaling(token)

	return signaling
}

let currentToken = null
let startedCall = null

function startCall(configuration) {
	let flags = PARTICIPANT.CALL_FLAG.IN_CALL
	if (configuration) {
		if (configuration.audio) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO
		}
		if (configuration.video && signaling.getSendVideoIfAvailable()) {
			flags |= PARTICIPANT.CALL_FLAG.WITH_VIDEO
		}
	}

	signaling.joinCall(currentToken, flags)

	startedCall()
}

function setupWebRtc() {
	if (webRtc) {
		return
	}

	webRtc = initWebRtc(signaling, callParticipantCollection)
	localCallParticipantModel.setWebRtc(webRtc)
	localMediaModel.setWebRtc(webRtc)

	webRtc.on('localMediaStarted', function(configuration) {
		startCall(configuration)
	})
	webRtc.on('localMediaError', function() {
		startCall(null)
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
	await signaling.joinRoom(token, sessionId)
}

/**
 * Join the call of the given conversation
 *
 * @param {string} token Conversation to join the call
 * @returns {Promise<void>}
 */
async function signalingJoinCall(token) {
	await connectSignaling(token)

	setupWebRtc()

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

	await getSignaling(token)
	await signaling.leaveCall(token)
}

/**
 * Leave the given conversation on the respective signaling server
 *
 * @param {string} token Conversation to leave
 * @returns {Promise<void>}
 */
async function signalingLeaveConversation(token) {
	await getSignaling(token)
	await signaling.leaveRoom(token)
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

export {
	callParticipantCollection,
	localCallParticipantModel,
	localMediaModel,

	connectSignaling,

	signalingJoinConversation,
	signalingJoinCall,
	signalingLeaveCall,
	signalingLeaveConversation,
	signalingKill,
}

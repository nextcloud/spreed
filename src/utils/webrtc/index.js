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
import { PARTICIPANT } from '../../constants'
import { EventBus } from '../../services/EventBus'

let signaling = null
let webRtc = null
const callParticipantCollection = new CallParticipantCollection()
const localCallParticipantModel = new LocalCallParticipantModel()
const localMediaModel = new LocalMediaModel()

let pendingConnectSignaling = null

async function connectSignaling() {
	if (signaling) {
		return
	}

	if (pendingConnectSignaling) {
		return pendingConnectSignaling
	}

	pendingConnectSignaling = new Promise((resolve, reject) => {
		Signaling.loadSettings(null).then(() => {
			signaling = Signaling.createConnection()

			EventBus.$emit('signalingConnectionEstablished')

			pendingConnectSignaling = null

			resolve()
		})
	})

	return pendingConnectSignaling
}

async function getSignaling() {
	await connectSignaling()

	return signaling
}

function getSignalingSync() {
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

async function joinCall(token) {
	await connectSignaling()

	setupWebRtc()

	currentToken = token

	return new Promise((resolve, reject) => {
		startedCall = resolve

		webRtc.startMedia(token)
	})
}

export {
	callParticipantCollection,
	localCallParticipantModel,
	localMediaModel,
	connectSignaling,
	getSignaling,
	getSignalingSync,
	joinCall,
}

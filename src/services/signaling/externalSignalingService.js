/*
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

import { EventBus } from '../EventBus'
import { generateOcsUrl } from '@nextcloud/router'
import { PARTICIPANT } from '../../constants'

const state = {
	features: {},
	signalingUrl: '',

	socket: null,
	connected: false,
	resumeId: null,
	signalingSessionId: null,
	forceReconnect: false,

	currentCallFlags: PARTICIPANT.CALL_FLAG.IN_CALL,

	callbacks: {},
	callbackId: 1,
	pendingMessages: [],

	initialReconnectIntervalMs: 1000,
	maxReconnectIntervalMs: 16000,
	reconnectIntervalMs: 1000,
	reconnectTimer: null,

}

/**
 * Start the signaling
 *
 * @param {string} token Conversation to do the signaling on
 * @param {object} signalingServer Signaling server information
 * @param {string} signalingTicket The ticket to authenticate on the signaling server
 * @param {array} stunServers List of stun servers: {url: <String>}
 * @param {array} turnServers List of turn servers: {url: <String>, username: <String>, credential: <String>}
 */
const startExternalSignaling = function(token, signalingServer, signalingTicket, stunServers, turnServers) {
	state.signalingUrl = getPseudoRandomSignalingServer(signalingServer)

	connect()
}

const getPseudoRandomSignalingServer = function(urls) {
	if (typeof (urls) === 'string') {
		urls = [urls]
	}

	// TODO(jojo): Try other server if connection fails.
	const idx = Math.floor(Math.random() * urls.length)
	let url = urls[idx]

	// Translate into WebSockets URL
	if (url.indexOf('https://') === 0) {
		url = 'wss://' + url.substr(8)
	} else if (url.indexOf('http://') === 0) {
		url = 'ws://' + url.substr(7)
	}

	// Remove trailing slashes
	if (url[url.length - 1] === '/') {
		url = url.substr(0, url.length - 1)
	}

	return url + '/spreed'
}

const stopExternalSignaling = function() {
	disconnect()
}

/**
 * Does the signaling server support a given feature
 * @param {string} feature The feature to check for
 * @returns {boolean}
 */
const hasFeatureExternalSignaling = function(feature) {
	return state.features && state.features[feature]
}

const connect = function() {
	state.callbacks = {}
	state.callbackId = 1
	state.pendingMessages = []
	state.connected = false
	state.forceReconnect = false

	state.socket = new WebSocket(state.signalingUrl)

	state.socket.onopen = function(event) {
		console.debug('Connected', event)
		state.reconnectIntervalMs = state.initialReconnectIntervalMs
		sendHello()
	}
	state.socket.onerror = function(event) {
		console.debug('Error', event)
		reconnect()
	}
	state.socket.onclose = function(event) {
		console.debug('Close', event)
		reconnect()
	}

	state.socket.onmessage = function(event) {
		let data = event.data
		if (typeof (data) === 'string') {
			data = JSON.parse(data)
		}

		console.debug('Received', data)
		const id = data.id

		// Handle callbacks
		if (id && state.callbacks.hasOwnProperty(id)) {
			const cb = state.callbacks[id]
			delete state.callbacks[id]
			cb(data)
		}

		switch (data.type) {
		case 'hello':
			if (!id) {
				// Only process if not received as result of our "hello".
				helloResponseReceived(data)
			}
			break
		case 'room':
			// FIXME
			if (this.currentRoomToken && data.room.roomid !== this.currentRoomToken) {
				this._trigger('roomChanged', [this.currentRoomToken, data.room.roomid])
				this.joinedUsers = {}
				this.currentRoomToken = null
			} else {
				// TODO(fancycode): Only fetch properties of room that was modified.
				this.internalSyncRooms()
			}
			break
		case 'event':
			// FIXME
			this.processEvent(data)
			break
		case 'message':
			data.message.data.from = data.message.sender.sessionid
			EventBus.$emit('Signaling::message', [data.message.data])
			break
		default:
			if (!id) {
				console.debug('Ignore unknown event', data)
			}
			break
		}
	}
}

const reconnect = function() {
	if (state.reconnectTimer) {
		// Reconnection is already in progress
		return
	}

	// Wiggle interval a little bit to prevent all clients from connecting
	// simultaneously in case the server connection is interrupted.
	const interval = state.reconnectIntervalMs - (state.reconnectIntervalMs / 2) + (state.reconnectIntervalMs * Math.random())

	console.debug('Reconnect in', interval)
	// this.reconnected = true
	state.reconnectTimer = window.setTimeout(function() {
		state.reconnectTimer = null
		state.connect()
	}, interval)

	// Increase the reconnection interval
	state.reconnectIntervalMs = state.reconnectIntervalMs * 2
	if (state.reconnectIntervalMs > state.maxReconnectIntervalMs) {
		state.reconnectIntervalMs = state.maxReconnectIntervalMs
	}

	// Close any existing socket
	if (state.socket) {
		state.socket.close()
		state.socket = null
	}
}

const disconnect = function() {
	sendBye()
	if (state.socket) {
		state.socket.close()
		state.socket = null
	}
	state.signalingSessionId = ''
	state.currentCallFlags = null
}

const forceReconnect = function(newSession, flags) {
	if (flags !== undefined) {
		state.currentCallFlags = flags
	}

	if (!state.connected) {
		if (!newSession) {
			// Not connected, will do reconnect anyway.
			return
		}

		state.forceReconnect = true
		state.resumeId = null
		return
	}

	state.forceReconnect = false
	if (newSession) {
		// if (this.currentCallToken) {
		// // Mark this session as "no longer in the call".
		// this.leaveCall(this.currentCallToken, true)
		// }
		sendBye()
	}

	if (state.socket) {
		// Trigger reconnect.
		state.socket.close()
	}
}

const doSend = function(msg, callback) {
	if ((!state.connected && msg.type !== 'hello') || state.socket === null) {
		// Defer sending any messages until the hello response has been
		// received and when the socket is open
		state.pendingMessages.push([msg, callback])
		return
	}

	// Register the callback
	if (callback) {
		const id = state.callbackId++
		state.callbacks[id] = callback
		msg['id'] = '' + id
	}

	console.debug('Sending', msg)
	state.socket.send(JSON.stringify(msg))
}

const sendHello = function() {
	let msg
	if (state.resumeId) {
		console.debug('Trying to resume session', state.signalingSessionId)
		msg = {
			'type': 'hello',
			'hello': {
				'version': '1.0',
				'resumeid': state.resumeId,
			},
		}
	} else {
		// Already reconnected with a new session.
		state.forceReconnect = false
		const user = OCA.Talk.getCurrentUser() // FIXME
		const url = generateOcsUrl('apps/spreed/api/v1/signaling', 2) + 'backend'
		msg = {
			'type': 'hello',
			'hello': {
				'version': '1.0',
				'auth': {
					'url': url,
					'params': {
						'userid': user.uid,
						'ticket': state.ticket,
					},
				},
			},
		}
	}
	doSend(msg, helloResponseReceived)
}

const helloResponseReceived = function(data) {
	console.debug('Hello response received', data)
	if (data.type !== 'hello') {
		if (state.resumeId) {
			// Resuming the session failed, reconnect as new session.
			state.resumeId = null
			sendHello()
			return
		}

		// TODO(fancycode): How should this be handled better?
		console.error('Could not connect to server', data)
		reconnect()
		return
	}

	const resumedSession = !!state.resumeId
	state.connected = true
	if (state.forceReconnect && resumedSession) {
		console.debug('Perform pending forced reconnect')
		forceReconnect(true)
		return
	}
	state.signalingSessionId = data.hello.sessionid
	state.resumeId = data.hello.resumeid
	state.features = {}
	if (data.hello.server && data.hello.server.features) {
		const features = data.hello.server.features
		for (let i = 0; i < features.length; i++) {
			state.features[features[i]] = true
		}
	}

	const messages = state.pendingMessages
	state.pendingMessages.splice(0, messages.length)
	for (let i = 0; i < messages.length; i++) {
		const msg = messages[i][0]
		const callback = messages[i][1]
		doSend(msg, callback)
	}

	EventBus.$emit('Signaling::connect', [])

	doSend({
		'type': 'room',
		'room': {
			'roomid': state.token,
			'sessionid': state.sessionId, // FIXME this is not filled yet. Use actor store instead?
		},
	})
}

const sendBye = function() {
	if (state.connected) {
		doSend({
			'type': 'bye',
			'bye': {},
		})
	}
	state.resumeId = null
}

export {
	startExternalSignaling,
	hasFeatureExternalSignaling,
	stopExternalSignaling,
}

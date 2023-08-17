/**
 * @copyright Copyright (c) 2019 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @copyright Copyright (c) 2019 Ivan Sein <ivan@nextcloud.com>
 *
 * @copyright Copyright (c) 2019 Joachim Bauch <bauch@struktur.de>
 *
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @author Ivan Sein <ivan@nextcloud.com>
 *
 * @author Joachim Bauch <bauch@struktur.de>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

import axios from '@nextcloud/axios'
import {
	showError,
	showWarning,
	TOAST_PERMANENT_TIMEOUT,
} from '@nextcloud/dialogs'
import {
	generateOcsUrl,
	generateUrl,
} from '@nextcloud/router'

import { PARTICIPANT } from '../constants.js'
import { EventBus } from '../services/EventBus.js'
import { rejoinConversation } from '../services/participantsService.js'
import { pullSignalingMessages } from '../services/signalingService.js'
import store from '../store/index.js'
import CancelableRequest from './cancelableRequest.js'

const Signaling = {
	Base: {},
	Internal: {},
	Standalone: {},

	/**
	 * Creates a connection to the signaling server
	 *
	 * @param {object} settings The signaling settings
	 * @return {Standalone|Internal}
	 */
	createConnection(settings) {
		if (!settings) {
			console.error('Signaling settings are not given')
		}

		if (settings.signalingMode !== 'internal') {
			return new Signaling.Standalone(settings, settings.server)
		} else {
			return new Signaling.Internal(settings)
		}
	},
}

/**
 * @param {object} settings The signaling settings
 */
function Base(settings) {
	this.settings = settings
	this.sessionId = ''
	this.currentRoomToken = null
	this.currentCallToken = null
	this.currentCallFlags = null
	this.nextcloudSessionId = null
	this.handlers = {}
	this.features = {}
	this._sendVideoIfAvailable = true
	this.signalingConnectionTimeout = null
	this.signalingConnectionWarning = null
	this.signalingConnectionError = null
}

Signaling.Base = Base
Signaling.Base.prototype.on = function(ev, handler) {
	if (!Object.prototype.hasOwnProperty.call(this.handlers, ev)) {
		this.handlers[ev] = [handler]
	} else {
		this.handlers[ev].push(handler)
	}

	let servers = []
	switch (ev) {
	case 'stunservers':
	case 'turnservers':
		servers = this.settings[ev] || []
		if (servers.length) {
			handler(servers)
		}
		break
	}
}

Signaling.Base.prototype.off = function(ev, handler) {
	if (!Object.prototype.hasOwnProperty.call(this.handlers, ev)) {
		return
	}

	let pos = this.handlers[ev].indexOf(handler)
	while (pos !== -1) {
		this.handlers[ev].splice(pos, 1)
		pos = this.handlers[ev].indexOf(handler)
	}
}

Signaling.Base.prototype._trigger = function(ev, args) {
	let handlers = this.handlers[ev]

	if (handlers) {
		handlers = handlers.slice(0)
		for (let i = 0, len = handlers.length; i < len; i++) {
			const handler = handlers[i]
			handler.apply(handler, args)
		}
	}

	// Convert webrtc event names to kebab-case for "vue/custom-event-name-casing"
	const kebabCase = string => string
		.replace(/([a-z])([A-Z])/g, '$1-$2')
		.replace(/[\s_]+/g, '-')
		.toLowerCase()
	EventBus.$emit('signaling-' + kebabCase(ev), args)
}

Signaling.Base.prototype.isNoMcuWarningEnabled = function() {
	return !this.settings.hideWarning
}

Signaling.Base.prototype.getSessionId = function() {
	return this.sessionId
}

Signaling.Base.prototype.getCurrentCallFlags = function() {
	return this.currentCallFlags
}

Signaling.Base.prototype.disconnect = function() {
	this.sessionId = ''
	this._trigger('sessionId', [this.sessionId])
	this.currentCallToken = null
	this.currentCallFlags = null
}

Signaling.Base.prototype.hasFeature = function(feature) {
	return this.features && this.features[feature]
}

Signaling.Base.prototype.emit = function(ev, data) {
	switch (ev) {
	case 'joinRoom':
		this.joinRoom(data)
		break
	case 'joinCall':
		this.joinCall(data, arguments[2])
		break
	case 'leaveRoom':
		this.leaveCurrentRoom()
		break
	case 'leaveCall':
		this.leaveCurrentCall()
		break
	case 'message':
		this.sendCallMessage(data)
		break
	}
}

Signaling.Base.prototype.leaveCurrentRoom = function() {
	if (this.currentRoomToken) {
		this.leaveRoom(this.currentRoomToken)
		this.currentRoomToken = null
		this.nextcloudSessionId = null
	}
}

Signaling.Base.prototype.updateCurrentCallFlags = function(flags) {
	return new Promise((resolve, reject) => {
		if (this.currentCallToken) {
			this.updateCallFlags(this.currentCallToken, flags).then(() => { resolve() }).catch(reason => { reject(reason) })
		} else {
			resolve()
		}
	})
}

Signaling.Base.prototype.leaveCurrentCall = function() {
	return new Promise((resolve, reject) => {
		if (this.currentCallToken) {
			this.leaveCall(this.currentCallToken).then(() => { resolve() }).catch(reason => { reject(reason) })
			this.currentCallToken = null
			this.currentCallFlags = null
		} else {
			resolve()
		}
	})
}

Signaling.Base.prototype.joinRoom = function(token, sessionId) {
	return new Promise((resolve, reject) => {
		console.debug('Joined')
		this.currentRoomToken = token
		this.nextcloudSessionId = sessionId
		this._trigger('joinRoom', [token])
		resolve()
		if (this.currentCallToken === token) {
			// We were in this call before, join again.
			this.joinCall(token, this.currentCallFlags)
		} else {
			this.currentCallToken = null
			this.currentCallFlags = null
		}
		this._joinRoomSuccess(token, sessionId)
	})
}

Signaling.Base.prototype._leaveRoomSuccess = function(/* token */) {
	// Override in subclasses if necessary.
}

Signaling.Base.prototype.leaveRoom = function(token) {
	this.leaveCurrentCall()
		.then(() => {
			this._trigger('leaveRoom', [token])
			this._doLeaveRoom(token)

			return new Promise((resolve, reject) => {
				this._leaveRoomSuccess(token)
				resolve()
				// We left the current room.
				if (token === this.currentRoomToken) {
					this.currentRoomToken = null
					this.nextcloudSessionId = null
				}
			})
		})
}

Signaling.Base.prototype.getSendVideoIfAvailable = function() {
	return this._sendVideoIfAvailable
}

Signaling.Base.prototype.setSendVideoIfAvailable = function(sendVideoIfAvailable) {
	this._sendVideoIfAvailable = sendVideoIfAvailable
}

Signaling.Base.prototype._joinCallSuccess = function(/* token */) {
	// Override in subclasses if necessary.
}

Signaling.Base.prototype.joinCall = function(token, flags, silent) {
	return new Promise((resolve, reject) => {
		this._trigger('beforeJoinCall', [token])

		axios.post(generateOcsUrl('apps/spreed/api/v4/call/{token}', { token }), {
			flags,
			silent,
		})
			.then(function() {
				this.currentCallToken = token
				this.currentCallFlags = flags
				this._trigger('joinCall', [token])
				resolve()
				this._joinCallSuccess(token)
			}.bind(this))
			.catch(function() {
				reject(new Error())
				if (!IS_DESKTOP) {
					// Server maintenance, lobby kicked in, or room not found.
					// We first redirect to the conversation again and that
					// will then show the proper error message to the user.
					window.location = generateUrl('call/' + token)
				} else {
					// TODO: Is it true, reload is equal to generateUrl('call/' + token) here?
					// Or can we always just reload the page?
					window.location.reload()
				}
			})
	})
}

Signaling.Base.prototype._leaveCallSuccess = function(/* token */) {
	// Override in subclasses if necessary.
}

Signaling.Base.prototype.updateCallFlags = function(token, flags) {
	return new Promise((resolve, reject) => {
		if (!token) {
			reject(new Error())
			return
		}

		axios.put(generateOcsUrl('apps/spreed/api/v4/call/{token}', { token }), {
			flags,
		})
			.then(function() {
				this.currentCallFlags = flags
				this._trigger('updateCallFlags', [token, flags])
				resolve()
			}.bind(this))
			.catch(function() {
				reject(new Error())
			})
	})
}

Signaling.Base.prototype.leaveCall = function(token, keepToken, all = false) {
	return new Promise((resolve, reject) => {
		if (!token) {
			reject(new Error())
			return
		}

		this._trigger('beforeLeaveCall', [token, keepToken])

		axios.delete(generateOcsUrl('apps/spreed/api/v4/call/{token}', { token }), {
			data: {
				all,
			},
		})
			.then(function() {
				this._trigger('leaveCall', [token, keepToken])
				this._leaveCallSuccess(token)
				resolve()
				// We left the current call.
				if (!keepToken && token === this.currentCallToken) {
					this.currentCallToken = null
					this.currentCallFlags = null
				}
			}.bind(this))
			.catch(function() {
				this._trigger('leaveCall', [token, keepToken])
				reject(new Error())
				// We left the current call.
				if (!keepToken && token === this.currentCallToken) {
					this.currentCallToken = null
					this.currentCallFlags = null
				}
			}.bind(this))
	})
}

// Connection to the internal signaling server provided by the app.
/**
 * @param {object} settings The signaling settings
 */
function Internal(settings) {
	Signaling.Base.prototype.constructor.apply(this, arguments)
	this.hideWarning = settings.hideWarning
	this.spreedArrayConnection = []

	this.pullMessageErrorToast = null
	this.pullMessagesFails = 0
	this.pullMessagesRequest = null

	this.isSendingMessages = false
	this.sendInterval = window.setInterval(function() {
		this.sendPendingMessages()
	}.bind(this), 500)

	this._joinCallAgainOnceDisconnected = false
}

Internal.prototype = new Signaling.Base()
Internal.prototype.constructor = Internal
Signaling.Internal = Internal

Signaling.Internal.prototype.disconnect = function() {
	this.spreedArrayConnection = []
	if (this.sendInterval) {
		window.clearInterval(this.sendInterval)
		this.sendInterval = null
	}
	Signaling.Base.prototype.disconnect.apply(this, arguments)
}

Signaling.Internal.prototype.on = function(ev/*, handler */) {
	Signaling.Base.prototype.on.apply(this, arguments)

	switch (ev) {
	case 'connect':
		// A connection is established if we can perform a request
		// through it.
		this._sendMessageWithCallback(ev)
		break
	}
}

Signaling.Internal.prototype.forceReconnect = function(newSession, flags) {
	if (newSession) {
		console.warn('Forced reconnects with a new session are not supported in the internal signaling; same session as before will be used')
	}

	if (flags !== undefined) {
		this.currentCallFlags = flags
	}

	// FIXME Naive reconnection routine; as the same session is kept peers
	// must be explicitly ended before the reconnection is forced.
	this.leaveCall(this.currentCallToken, true).then(() => {
		this._joinCallAgainOnceDisconnected = true
	})
}

Signaling.Internal.prototype._sendMessageWithCallback = function(ev) {
	const message = [{
		ev,
	}]

	this._sendMessages(message)
		.then(function(result) {
			this._trigger(ev, [result.data.ocs.data])
		}.bind(this))
		.catch(function(err) {
			console.error(err)
			showError(t('spreed', 'Sending signaling message has failed'))
		})
}

Signaling.Internal.prototype._sendMessages = function(messages) {
	return axios.post(generateOcsUrl('apps/spreed/api/v3/signaling/{token}', { token: this.currentRoomToken }), {
		messages: JSON.stringify(messages),
	})
}

Signaling.Internal.prototype._joinRoomSuccess = function(token, sessionId) {
	this._joinCallAgainOnceDisconnected = false

	this.sessionId = sessionId
	this._trigger('sessionId', [this.sessionId])
	this._startPullingMessages()
}

Signaling.Internal.prototype._doLeaveRoom = function(token) {
	this._joinCallAgainOnceDisconnected = false
	this.pullMessagesRequest?.('canceled')
}

Signaling.Internal.prototype.sendCallMessage = function(data) {
	if (data.type === 'answer') {
		console.debug('ANSWER', data)
	} else if (data.type === 'offer') {
		console.debug('OFFER', data)
	}
	this.spreedArrayConnection.push({
		ev: 'message',
		fn: JSON.stringify(data),
		sessionId: this.sessionId,
	})
}

/**
 * @private
 */
Signaling.Internal.prototype._startPullingMessages = function() {
	const token = this.currentRoomToken
	if (!token) {
		return
	}

	// Abort ongoing request
	if (this.pullMessagesRequest !== null) {
		this.pullMessagesRequest('canceled')
	}

	// Connect to the messages endpoint and pull for new messages
	const { request, cancel } = CancelableRequest(pullSignalingMessages)
	this.pullMessagesRequest = cancel
	request(token)
		.then(function(result) {
			this.pullMessagesFails = 0
			if (this.pullMessageErrorToast) {
				this.pullMessageErrorToast.hideToast()
				this.pullMessageErrorToast = null
			}

			result.data.ocs.data.forEach(message => {
				let localParticipant

				this._trigger('onBeforeReceiveMessage', [message])
				switch (message.type) {
				case 'usersInRoom':
					this._trigger('usersInRoom', [message.data])
					this._trigger('participantListChanged')

					localParticipant = message.data.find(participant => participant.sessionId === this.sessionId)
					if (this._joinCallAgainOnceDisconnected && !localParticipant.inCall) {
						this._joinCallAgainOnceDisconnected = false
						this.joinCall(this.currentCallToken, this.currentCallFlags)
					}

					break
				case 'message':
					if (typeof (message.data) === 'string') {
						message.data = JSON.parse(message.data)
					}
					this._trigger('message', [message.data])
					break
				default:
					console.error('Unknown Signaling Message', message)
					break
				}
				this._trigger('onAfterReceiveMessage', [message])
			})
			this._startPullingMessages()
		}.bind(this))
		.catch(function(error) {
			if (token !== this.currentRoomToken) {
				// User navigated away in the meantime. Ignore
			} else if (axios.isCancel(error)) {
				console.debug('Pulling messages request was cancelled')
			} else if (error?.response?.status === 409) {
				// Participant joined a second time and this session was killed
				console.error('Session was killed but the conversation still exists')
				this._trigger('pullMessagesStoppedOnFail')

				EventBus.$emit('duplicate-session-detected')
			} else if (error?.response?.status === 404 || error?.response?.status === 403) {
				// Conversation was deleted or the user was removed
				console.error('Conversation was not found anymore')
				EventBus.$emit('deleted-session-detected')
			} else if (token) {
				if (this.pullMessagesFails === 1) {
					this.pullMessageErrorToast = showError(t('spreed', 'Lost connection to signaling server. Trying to reconnect.'), {
						timeout: TOAST_PERMANENT_TIMEOUT,
					})
				}
				if (this.pullMessagesFails === 30) {
					if (this.pullMessageErrorToast) {
						this.pullMessageErrorToast.hideToast()
					}

					// Giving up after 5 minutes
					this.pullMessageErrorToast = showError(t('spreed', 'Lost connection to signaling server. Try to reload the page manually.'), {
						timeout: TOAST_PERMANENT_TIMEOUT,
					})
					return
				}

				this.pullMessagesFails++
				// Retry to pull messages after 10 seconds
				window.setTimeout(function() {
					this._startPullingMessages()
				}.bind(this), 10000)
			}
		}.bind(this))
}

/**
 * @private
 */
Signaling.Internal.prototype.sendPendingMessages = function() {
	if (!this.spreedArrayConnection.length || this.isSendingMessages) {
		return
	}

	const pendingMessagesLength = this.spreedArrayConnection.length
	this.isSendingMessages = true

	this._sendMessages(this.spreedArrayConnection).then(function(/* result */) {
		this.spreedArrayConnection.splice(0, pendingMessagesLength)
		this.isSendingMessages = false
	}.bind(this)).catch(function(/* xhr, textStatus, errorThrown */) {
		console.error('Sending pending signaling messages has failed.')
		this.isSendingMessages = false
	}.bind(this))
}

/**
 * @param {object} settings The signaling settings
 * @param {string|string[]} urls The url of the signaling server
 */
function Standalone(settings, urls) {
	Signaling.Base.prototype.constructor.apply(this, arguments)
	if (typeof (urls) === 'string') {
		urls = [urls]
	}
	// We can connect to any of the servers.
	const idx = Math.floor(Math.random() * urls.length)
	// TODO(jojo): Try other server if connection fails.
	let url = urls[idx]
	// Make sure we are using websocket urls.
	if (url.startsWith('https://')) {
		url = 'wss://' + url.slice(8)
	} else if (url.startsWith('http://')) {
		url = 'ws://' + url.slice(7)
	}
	if (url.endsWith('/')) {
		url = url.slice(0, -1)
	}
	this.url = url + '/spreed'
	this.welcomeTimeoutMs = 3000
	this.initialReconnectIntervalMs = 1000
	this.maxReconnectIntervalMs = 16000
	this.reconnectIntervalMs = this.initialReconnectIntervalMs
	this.helloResponseErrorCount = 0
	this.ownSessionJoined = false
	this.joinedUsers = {}
	this.rooms = []
	this.connect()
}

Standalone.prototype = new Signaling.Base()
Standalone.prototype.constructor = Standalone
Signaling.Standalone = Standalone

Signaling.Standalone.prototype.reconnect = function() {
	if (this.reconnectTimer) {
		return
	}

	// Wiggle interval a little bit to prevent all clients from connecting
	// simultaneously in case the server connection is interrupted.
	const interval = this.reconnectIntervalMs - (this.reconnectIntervalMs / 2) + (this.reconnectIntervalMs * Math.random())
	console.info('Reconnect in', interval)
	this.reconnected = true
	this.reconnectTimer = window.setTimeout(function() {
		this.reconnectTimer = null
		this.connect()
	}.bind(this), interval)
	this.reconnectIntervalMs = this.reconnectIntervalMs * 2
	if (this.reconnectIntervalMs > this.maxReconnectIntervalMs) {
		this.reconnectIntervalMs = this.maxReconnectIntervalMs
	}
	if (this.socket) {
		this.socket.close()
		this.socket = null
	}
}

Signaling.Standalone.prototype.connect = function() {
	if (this.signalingConnectionError === null
		&& this.signalingConnectionWarning === null) {
		this.signalingConnectionTimeout = setTimeout(() => {
			this.signalingConnectionWarning = showWarning(t('spreed', 'Establishing signaling connection is taking longer than expected …'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		}, 2000)
	}

	console.debug('Connecting to ' + this.url + ' for ' + this.settings.token)
	this.callbacks = {}
	this.id = 1
	this.pendingMessages = []
	this.connected = false
	this._forceReconnect = false
	this._isRejoiningConversationWithNewSession = false
	this.socket = new WebSocket(this.url)
	window.signalingSocket = this.socket
	this.socket.onopen = function(event) {
		console.debug('Connected', event)
		if (this.signalingConnectionTimeout !== null) {
			clearTimeout(this.signalingConnectionTimeout)
			this.signalingConnectionTimeout = null
		}
		if (this.signalingConnectionWarning !== null) {
			this.signalingConnectionWarning.hideToast()
			this.signalingConnectionWarning = null
		}
		this.reconnectIntervalMs = this.initialReconnectIntervalMs
		if (this.settings.helloAuthParams['2.0']) {
			this.waitForWelcomeTimeout = setTimeout(this.welcomeTimeout.bind(this), this.welcomeTimeoutMs)
		} else {
			this.sendHello()
		}
	}.bind(this)
	this.socket.onerror = function(event) {
		console.error('Error', event)
		if (this.signalingConnectionTimeout !== null) {
			clearTimeout(this.signalingConnectionTimeout)
			this.signalingConnectionTimeout = null
		}
		if (this.signalingConnectionWarning !== null) {
			this.signalingConnectionWarning.hideToast()
			this.signalingConnectionWarning = null
		}
		if (this.signalingConnectionError === null) {
			this.signalingConnectionError = showError(t('spreed', 'Failed to establish signaling connection. Retrying …'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		}
		this.reconnect()
	}.bind(this)
	this.socket.onclose = function(event) {
		console.debug('Close', event)
		if (this.signalingConnectionTimeout !== null) {
			clearTimeout(this.signalingConnectionTimeout)
			this.signalingConnectionTimeout = null
		}
		if (this.signalingConnectionWarning !== null) {
			this.signalingConnectionWarning.hideToast()
			this.signalingConnectionWarning = null
		}
		if (event.code === 1001 && this.signalingConnectionError !== null) {
			this.signalingConnectionError.hideToast()
			this.signalingConnectionError = null
		}
		if (this.socket && event.code !== 1001) {
			console.debug('Reconnecting socket as the connection was closed unexpected')
			this.reconnect()
		}
	}.bind(this)
	this.socket.onmessage = function(event) {
		let data = event.data
		if (typeof (data) === 'string') {
			data = JSON.parse(data)
		}
		if (OC.debug) {
			console.debug('Received', data)
		}
		const id = data.id
		if (id && Object.prototype.hasOwnProperty.call(this.callbacks, id)) {
			const cb = this.callbacks[id]
			delete this.callbacks[id]
			cb(data)
		}
		this._trigger('onBeforeReceiveMessage', [data])
		const message = {}
		switch (data.type) {
		case 'welcome':
			this.welcomeReceived(data)
			break
		case 'hello':
			if (!id) {
				// Only process if not received as result of our "hello".
				this.helloResponseReceived(data)
			}
			break
		case 'room':
			if (this.currentRoomToken && data.room.roomid !== this.currentRoomToken) {
				this._trigger('roomChanged', [this.currentRoomToken, data.room.roomid])
				this.joinedUsers = {}
				this.currentRoomToken = null
				this.nextcloudSessionId = null
			} else {
				// TODO(fancycode): Only fetch properties of room that was modified.
				EventBus.$emit('should-refresh-conversations')
			}
			break
		case 'event':
			this.processEvent(data)
			break
		case 'message':
			data.message.data.from = data.message.sender.sessionid
			this._trigger('message', [data.message.data])
			break
		case 'control':
			message.type = 'control'
			message.payload = data.control.data
			message.from = data.control.sender.sessionid
			this._trigger('message', [message])
			break
		case 'error':
			switch (data.error.code) {
			case 'processing_failed':
				console.error('An error occurred processing the signaling message, please ask your server administrator to check the log file')
				break
			case 'token_expired':
				console.info('The signaling token is expired, need to update settings')
				this._trigger('updateSettings')
				break
			default:
				console.error('Ignore unknown error', data)
				this._trigger('error', [data.error])
				break
			}
			break
		default:
			if (!id) {
				console.error('Ignore unknown event', data)
			}
			break
		}
		this._trigger('onAfterReceiveMessage', [data])
	}.bind(this)
}

Signaling.Standalone.prototype.welcomeReceived = function(data) {
	console.debug('Welcome received', data)
	if (this.waitForWelcomeTimeout !== null) {
		clearTimeout(this.waitForWelcomeTimeout)
		this.waitForWelcomeTimeout = null
	}

	this.features = {}
	let i
	if (data.welcome && data.welcome.features) {
		const features = data.welcome.features
		for (i = 0; i < features.length; i++) {
			this.features[features[i]] = true
		}
	}

	this.sendHello()
}

Signaling.Standalone.prototype.welcomeTimeout = function() {
	console.warn('No welcome received, assuming old-style signaling server')
	this.sendHello()
}

Signaling.Standalone.prototype.sendBye = function() {
	if (this.connected) {
		this.doSend({
			type: 'bye',
			bye: {},
		})
	}
	this.resumeId = null
	this.signalingRoomJoined = null
}

Signaling.Standalone.prototype.disconnect = function() {
	this.sendBye()
	if (this.socket) {
		this.socket.close()
		this.socket = null
	}
	Signaling.Base.prototype.disconnect.apply(this, arguments)
}

Signaling.Standalone.prototype.forceReconnect = function(newSession, flags) {
	if (flags !== undefined) {
		this.currentCallFlags = flags
	}

	if (!this.connected) {
		if (!newSession) {
			// Not connected, will do reconnect anyway.
			return
		}

		this._forceReconnect = true
		this.resumeId = null
		this.signalingRoomJoined = null
		return
	}

	this._forceReconnect = false
	if (newSession) {
		if (this.currentCallToken) {
			// Mark this session as "no longer in the call".
			this.leaveCall(this.currentCallToken, true)
		}

		this._isRejoiningConversationWithNewSession = true

		rejoinConversation(this.currentRoomToken)
			.then(response => {
				store.commit('setInCall', {
					token: this.currentRoomToken,
					sessionId: this.nextcloudSessionId,
					flags: PARTICIPANT.CALL_FLAG.DISCONNECTED,
				})

				this.nextcloudSessionId = response.data.ocs.data.sessionId

				store.dispatch('setCurrentParticipant', response.data.ocs.data)
				store.commit('setInCall', {
					token: this.currentRoomToken,
					sessionId: this.nextcloudSessionId,
					flags: this.currentCallFlags,
				})

				this.sendBye()
				if (this.socket) {
					// Trigger reconnect.
					this.socket.close()
				}
			})
	} else if (this.socket) {
		// Trigger reconnect.
		this.socket.close()
	}
}

Signaling.Standalone.prototype.sendCallMessage = function(data) {
	if (data.type === 'control') {
		this.doSend({
			type: 'control',
			control: {
				recipient: {
					type: 'session',
					sessionid: data.to,
				},
				data: data.payload,
			},
		})

		return
	}

	this.doSend({
		type: 'message',
		message: {
			recipient: {
				type: 'session',
				sessionid: data.to,
			},
			data,
		},
	})
}

Signaling.Standalone.prototype.sendRoomMessage = function(data) {
	if (!this.currentCallToken) {
		console.warn('Not in a room, not sending room message', data)
		return
	}

	this.doSend({
		type: 'message',
		message: {
			recipient: {
				type: 'room',
			},
			data,
		},
	})
}

Signaling.Standalone.prototype.doSend = function(msg, callback) {
	if ((!this.connected && msg.type !== 'hello') || this.socket === null) {
		// Defer sending any messages until the hello response has been
		// received and when the socket is open
		this.pendingMessages.push([msg, callback])
		return
	}

	if (callback) {
		const id = this.id++
		this.callbacks[id] = callback
		msg.id = '' + id
	}
	if (OC.debug) {
		console.debug('Sending', msg)
	}
	this.socket.send(JSON.stringify(msg))
}

Signaling.Standalone.prototype.sendHello = function() {
	let msg
	if (this.resumeId) {
		console.debug('Trying to resume session', this.sessionId)
		msg = {
			type: 'hello',
			hello: {
				version: '1.0',
				resumeid: this.resumeId,
			},
		}
	} else {
		// Already reconnected with a new session.
		this._forceReconnect = false
		const url = generateOcsUrl('apps/spreed/api/v3/signaling/backend')
		let helloVersion
		if (this.hasFeature('hello-v2') && this.settings.helloAuthParams['2.0']) {
			helloVersion = '2.0'
		} else {
			helloVersion = '1.0'
		}
		msg = {
			type: 'hello',
			hello: {
				version: helloVersion,
				auth: {
					url,
					params: this.settings.helloAuthParams[helloVersion],
				},
			},
		}
		if (this.settings.helloAuthParams.internal) {
			msg.hello.auth.type = 'internal'
			msg.hello.auth.params = this.settings.helloAuthParams.internal
		}
	}
	this.doSend(msg, this.helloResponseReceived.bind(this))
}

Signaling.Standalone.prototype.helloResponseReceived = function(data) {
	console.debug('Hello response received', data)
	if (data.type !== 'hello') {
		if (this.resumeId) {
			// Resuming the session failed, reconnect as new session.
			this.resumeId = ''
			this.sendHello()
			return
		}

		this.helloResponseErrorCount++

		if (this.signalingConnectionError === null && this.helloResponseErrorCount < 5) {
			this.signalingConnectionError = showError(t('spreed', 'Failed to establish signaling connection. Retrying …'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		} else if (this.helloResponseErrorCount === 5) {
			// Switch to a different message as several errors in a row in hello
			// responses indicate that the signaling server might be unable to
			// connect to Nextcloud.
			if (this.signalingConnectionError) {
				this.signalingConnectionError.hideToast()
			}
			this.signalingConnectionError = showError(t('spreed', 'Failed to establish signaling connection. Something might be wrong in the signaling server configuration'), {
				timeout: TOAST_PERMANENT_TIMEOUT,
			})
		}

		// TODO(fancycode): How should this be handled better?
		console.error('Could not connect to server', data)
		this.reconnect()
		return
	}

	this.helloResponseErrorCount = 0

	if (this.signalingConnectionError !== null) {
		this.signalingConnectionError.hideToast()
		this.signalingConnectionError = null
	}

	const resumedSession = !!this.resumeId
	this.connected = true
	if (this._forceReconnect && resumedSession) {
		console.info('Perform pending forced reconnect')
		this.forceReconnect(true)
		return
	}
	this.sessionId = data.hello.sessionid
	this._trigger('sessionId', [this.sessionId])
	this.resumeId = data.hello.resumeid
	this.features = {}
	let i
	if (data.hello.server && data.hello.server.features) {
		const features = data.hello.server.features
		for (i = 0; i < features.length; i++) {
			this.features[features[i]] = true
		}
	}

	if (!this.settings.helloAuthParams.internal && (!this.hasFeature('audio-video-permissions') || !this.hasFeature('incall-all') || !this.hasFeature('switchto'))) {
		showError(
			t('spreed', 'The configured signaling server needs to be updated to be compatible with this version of Talk. Please contact your administration.'),
			{
				timeout: TOAST_PERMANENT_TIMEOUT,
			}
		)
		console.error('The configured signaling server needs to be updated to be compatible with this version of Talk. Please contact your administration.')
	}

	const messages = this.pendingMessages
	this.pendingMessages = []
	for (i = 0; i < messages.length; i++) {
		const msg = messages[i][0]
		const callback = messages[i][1]
		this.doSend(msg, callback)
	}

	this._trigger('connect')
	if (!resumedSession && this.currentRoomToken && (this.nextcloudSessionId || this.settings.helloAuthParams.internal)) {
		this.joinRoom(this.currentRoomToken, this.nextcloudSessionId)
	}
}

Signaling.Standalone.prototype.joinRoom = function(token, sessionId) {
	this.ownSessionJoined = false

	if (!this.sessionId) {
		if (this._pendingJoinRoomPromise && this._pendingJoinRoomPromise.token === token) {
			return this._pendingJoinRoomPromise
		}

		if (this._pendingJoinRoomPromise) {
			this._pendingJoinRoomPromise.reject()
		}

		let pendingJoinRoomPromiseResolve
		let pendingJoinRoomPromiseReject
		this._pendingJoinRoomPromise = new Promise((resolve, reject) => {
			// The Promise executor is run even before the Promise constructor
			// has finished, so "this._pendingJoinRoomPromise" is not available
			// yet.
			pendingJoinRoomPromiseResolve = resolve
			pendingJoinRoomPromiseReject = reject
		})
		this._pendingJoinRoomPromise.resolve = pendingJoinRoomPromiseResolve
		this._pendingJoinRoomPromise.reject = pendingJoinRoomPromiseReject
		this._pendingJoinRoomPromise.token = token

		// If we would join without a connection to the signaling server here,
		// the room would be re-joined again in the "helloResponseReceived"
		// callback, leading to two entries for anonymous participants.
		console.info('Not connected to signaling server yet, defer joining room', token)
		this.currentRoomToken = token
		this.nextcloudSessionId = sessionId
		return this._pendingJoinRoomPromise
	}

	if (this._pendingJoinRoomPromise && this._pendingJoinRoomPromise.token !== token) {
		this._pendingJoinRoomPromise.reject()
		delete this._pendingJoinRoomPromise
	}

	if (!this._pendingJoinRoomPromise) {
		return Signaling.Base.prototype.joinRoom.apply(this, arguments)
	}

	const pendingJoinRoomPromise = this._pendingJoinRoomPromise
	delete this._pendingJoinRoomPromise

	Signaling.Base.prototype.joinRoom.apply(this, arguments)
		.then(() => { pendingJoinRoomPromise.resolve() })
		.catch(reason => { pendingJoinRoomPromise.reject(reason) })

	return pendingJoinRoomPromise
}

Signaling.Standalone.prototype._joinRoomSuccess = function(token, nextcloudSessionId) {
	if (!this.sessionId) {
		console.error('No hello response received yet, not joining room', token)
		return
	}

	console.debug('Join room', token)
	this.doSend({
		type: 'room',
		room: {
			roomid: token,
			// Pass the Nextcloud session id to the signaling server. The
			// session id will be passed through to Nextcloud to check if
			// the (Nextcloud) user is allowed to join the room.
			sessionid: nextcloudSessionId,
		},
	}, function(data) {
		this.joinResponseReceived(data, token)
	}.bind(this))
}

Signaling.Standalone.prototype.joinCall = function(token, flags) {
	if (this.signalingRoomJoined !== token) {
		console.debug('Not joined room yet, not joining call', token)

		if (this.pendingJoinCall && this.pendingJoinCall.token === token) {
			return this.pendingJoinCall.promise
		} else if (this.pendingJoinCall && this.pendingJoinCall.token !== token) {
			this.pendingJoinCall.reject(new Error('Pending join call canceled for ' + this.pendingJoinCall.token))
		}

		const promise = new Promise((resolve, reject) => {
			this.pendingJoinCall = {
				token,
				flags,
				resolve,
				reject,
			}
		})

		this.pendingJoinCall.promise = promise

		return this.pendingJoinCall.promise
	}

	// When using an internal client no request is done and joining the call
	// just succeeds (the incall flags were already set when the room was
	// joined).
	if (this.settings.helloAuthParams.internal) {
		return new Promise((resolve, reject) => {
			this._trigger('beforeJoinCall', [token])

			this.currentCallToken = token
			this.currentCallFlags = flags
			this._trigger('joinCall', [token])

			resolve()
		})
	}

	return Signaling.Base.prototype.joinCall.apply(this, arguments)
}

Signaling.Standalone.prototype.joinResponseReceived = function(data, token) {
	console.debug('Joined', data, token)
	this.signalingRoomJoined = token
	if (this.pendingJoinCall && token === this.pendingJoinCall.token) {
		const pendingJoinCallResolve = this.pendingJoinCall.resolve
		const pendingJoinCallReject = this.pendingJoinCall.reject

		this.joinCall(this.pendingJoinCall.token, this.pendingJoinCall.flags).then(() => {
			pendingJoinCallResolve()
		}).catch(error => {
			pendingJoinCallReject(error)
		})

		this.pendingJoinCall = null
	}
	if (this.roomCollection) {
		// The list of rooms is not fetched from the server. Update ping
		// of joined room so it gets sorted to the top.
		this.roomCollection.forEach(function(room) {
			if (room.get('token') === token) {
				room.set('lastPing', (new Date()).getTime() / 1000)
			}
		})
		this.roomCollection.sort()
	}
}

Signaling.Standalone.prototype._doLeaveRoom = function(token) {
	console.debug('Leave room', token)
	this.doSend({
		type: 'room',
		room: {
			roomid: '',
		},
	}, function(data) {
		console.debug('Left', data)
		this.signalingRoomJoined = null
		// Any users we previously had in the room also "left" for us.
		const leftUsers = Object.keys(this.joinedUsers)
		if (leftUsers.length) {
			this._trigger('usersLeft', [leftUsers])
		}
		this.joinedUsers = {}
	}.bind(this))
}

Signaling.Standalone.prototype.processEvent = function(data) {
	switch (data.event.target) {
	case 'room':
		this.processRoomEvent(data)
		break
	case 'roomlist':
		this.processRoomListEvent(data)
		break
	case 'participants':
		this.processRoomParticipantsEvent(data)
		break
	default:
		console.error('Unsupported event target', data)
		break
	}
}

Signaling.Standalone.prototype.processRoomEvent = function(data) {
	let i
	let joinedUsers = []
	let leftSessionIds = []
	switch (data.event.type) {
	case 'join':
		joinedUsers = data.event.join || []
		if (joinedUsers.length) {
			console.debug('Users joined', joinedUsers)
			let leftUsers = {}
			if (this.reconnected) {
				this.reconnected = false
				// The browser reconnected, some of the previous sessions
				// may now no longer exist.
				leftUsers = Object.assign({}, this.joinedUsers)
			}

			let userListIsDirty = false
			for (i = 0; i < joinedUsers.length; i++) {
				this.joinedUsers[joinedUsers[i].sessionid] = true
				delete leftUsers[joinedUsers[i].sessionid]

				if (this.settings.userId && joinedUsers[i].userid === this.settings.userId) {
					if (joinedUsers[i].sessionid === this.sessionId) {
						// We are ignoring joins before we found our own message,
						// as otherwise you get the warning for your own old session immediately
						this.ownSessionJoined = true
					}
				} else {
					userListIsDirty = true
				}
			}
			leftUsers = Object.keys(leftUsers)
			if (leftUsers.length) {
				this._trigger('usersLeft', [leftUsers])

				for (i = 0; i < leftUsers.length; i++) {
					delete this.joinedUsers[leftUsers[i]]

					if (!this.settings.userId || leftUsers[i].userid !== this.settings.userId) {
						userListIsDirty = true
					}
				}
			}
			this._trigger('usersJoined', [joinedUsers])
			if (userListIsDirty) {
				this._trigger('participantListChanged')
			}
		}
		break
	case 'leave':
		leftSessionIds = data.event.leave || []
		if (leftSessionIds.length) {
			console.debug('Users left', leftSessionIds)
			for (i = 0; i < leftSessionIds.length; i++) {
				delete this.joinedUsers[leftSessionIds[i]]
			}
			this._trigger('usersLeft', [leftSessionIds])
			this._trigger('participantListChanged')
		}
		break
	case 'switchto':
		EventBus.$emit('switch-to-conversation', {
			token: data.event.switchto.roomid,
		})
		break
	case 'message':
		this.processRoomMessageEvent(data.event.message.roomid, data.event.message.data)
		break
	default:
		console.error('Unknown room event', data)
		break
	}
}

Signaling.Standalone.prototype.processRoomMessageEvent = function(token, data) {
	switch (data.type) {
	case 'chat':
		// FIXME this is not listened to
		EventBus.$emit('should-refresh-chat-messages')
		break
	case 'recording':
		EventBus.$emit('signaling-recording-status-changed', token, data.recording.status)
		break
	default:
		console.error('Unknown room message event', data)
	}
}

Signaling.Standalone.prototype.processRoomListEvent = function(data) {
	switch (data.event.type) {
	case 'delete':
		console.debug('Room list event', data)
		EventBus.$emit('should-refresh-conversations', { all: true })
		break
	case 'update':
		if (data.event.update.properties['participant-list']) {
			console.debug('Room list event for participant list', data)
			if (data.event.update.roomid === this.currentRoomToken) {
				this._trigger('participantListChanged')
			} else {
				// Participant list in another room changed, we don't really care
			}
			break
		} else {
			// Some keys do not exactly match those in the room data, so they
			// are normalized before emitting the event.
			const properties = data.event.update.properties
			const normalizedProperties = {}

			Object.keys(properties).forEach(key => {
				if (key === 'active-since') {
					return
				}

				let normalizedKey = key
				if (key === 'lobby-state') {
					normalizedKey = 'lobbyState'
				} else if (key === 'lobby-timer') {
					normalizedKey = 'lobbyTimer'
				} else if (key === 'read-only') {
					normalizedKey = 'readOnly'
				} else if (key === 'sip-enabled') {
					normalizedKey = 'sipEnabled'
				}

				normalizedProperties[normalizedKey] = properties[key]
			})

			EventBus.$emit('should-refresh-conversations', {
				token: data.event.update.roomid,
				properties: normalizedProperties,
			})
			break
		}
		// eslint-disable-next-line no-fallthrough
	case 'disinvite':
		if (data.event?.disinvite?.roomid === this.currentRoomToken) {
			if (this._isRejoiningConversationWithNewSession) {
				console.debug('Rejoining conversation with new session, "disinvite" message ignored')
				return
			}
			console.error('User or session was removed from the conversation, redirecting')
			EventBus.$emit('deleted-session-detected')
			break
		}
		// eslint-disable-next-line no-fallthrough
	default:
		console.debug('Room list event', data)
		EventBus.$emit('should-refresh-conversations')
		break
	}
}

Signaling.Standalone.prototype.processRoomParticipantsEvent = function(data) {
	switch (data.event.type) {
	case 'update':
		if (data.event.update.all) {
			// With `"all": true`
			if (data.event.update.incall === 0) {
				this._trigger('allUsersChangedInCallToDisconnected')
			} else {
				console.error('Unknown room participant event', data)
			}
		} else {
			// With updated user list
			this._trigger('usersChanged', [data.event.update.users || []])
		}
		this._trigger('participantListChanged')
		break
	case 'flags':
		this._trigger('participantFlagsChanged', [data.event.flags || []])
		break
	default:
		console.error('Unknown room participant event', data)
		break
	}
}

Signaling.Standalone.prototype.requestOffer = function(sessionid, roomType, sid = undefined) {
	if (!this.hasFeature('mcu')) {
		console.warn("Can't request an offer without a MCU.")
		return
	}

	if (typeof (sessionid) !== 'string') {
		// Got a user object.
		sessionid = sessionid.sessionId || sessionid.sessionid
	}
	console.debug('Request offer from', sessionid, sid)
	this.doSend({
		type: 'message',
		message: {
			recipient: {
				type: 'session',
				sessionid,
			},
			data: {
				type: 'requestoffer',
				roomType,
				sid,
			},
		},
	})
}

Signaling.Standalone.prototype.sendOffer = function(sessionid, roomType) {
	// TODO(jojo): This should go away and "requestOffer" should be used
	// instead by peers that want an offer by the MCU. See the calling
	// location for further details.
	if (!this.hasFeature('mcu')) {
		console.warn("Can't send an offer without a MCU.")
		return
	}

	if (typeof (sessionid) !== 'string') {
		// Got a user object.
		sessionid = sessionid.sessionId || sessionid.sessionid
	}
	console.debug('Send offer to', sessionid)
	this.doSend({
		type: 'message',
		message: {
			recipient: {
				type: 'session',
				sessionid,
			},
			data: {
				type: 'sendoffer',
				roomType,
			},
		},
	})
}

export default Signaling

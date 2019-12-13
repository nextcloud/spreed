/* global $, _ */

/* eslint-disable no-console */

import { fetchSignalingSettings } from '../services/signalingService'
import { EventBus } from '../services/EventBus'

const Signaling = {
	Base: {},
	Internal: {},
	Standalone: {},
	settings: {},

	/**
	 * Loads the signaling settings.
	 *
	 * @param {string} token Conversation token to load the signaling settings for
	 */
	async loadSettings(token) {
		const response = await fetchSignalingSettings(token)
		this.settings = response.data.ocs.data
	},

	/**
	 * Creates a connection to the signaling server
	 * @returns {Standalone|Internal}
	 */
	createConnection() {
		if (!this.settings) {
			console.error('Signaling settings are not yet loaded')
		}

		const urls = this.settings.server
		if (urls && urls.length) {
			return new Signaling.Standalone(this.settings, urls)
		} else {
			return new Signaling.Internal(this.settings)
		}
	},
}

function Base(settings) {
	this.settings = settings
	this.sessionId = ''
	this.currentRoomToken = null
	this.currentCallToken = null
	this.currentCallFlags = null
	this.handlers = {}
	this.features = {}
	this._sendVideoIfAvailable = true
}

Signaling.Base = Base
Signaling.Base.prototype.on = function(ev, handler) {
	if (!this.handlers.hasOwnProperty(ev)) {
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
			// The caller expects the handler to be called when the data
			// is available, so defer to simulate a delayed response.
			// FIXME is defer needed? _.defer(function() {
			handler(servers)
			// FIXME is defer needed? })
		}
		break
	}
}

Signaling.Base.prototype.off = function(ev, handler) {
	if (!this.handlers.hasOwnProperty(ev)) {
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
	if (!handlers) {
		return
	}

	handlers = handlers.slice(0)
	for (let i = 0, len = handlers.length; i < len; i++) {
		const handler = handlers[i]
		handler.apply(handler, args)
	}
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
	}
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

Signaling.Base.prototype.joinRoom = function(token, password) {
	return new Promise((resolve, reject) => {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants/active',
			type: 'POST',
			beforeSend: function(request) {
				request.setRequestHeader('Accept', 'application/json')
			},
			data: {
				password: password,
			},
			success: function(result) {
				console.log('Joined', result)
				this.currentRoomToken = token
				this._trigger('joinRoom', [token])
				resolve()
				if (this.currentCallToken === token) {
					// We were in this call before, join again.
					this.joinCall(token, this.currentCallFlags)
				} else {
					this.currentCallToken = null
					this.currentCallFlags = null
				}
				this._joinRoomSuccess(token, result.ocs.data.sessionId)
			}.bind(this),
			error: function(result) {
				reject(new Error())
				if (result.status === 404 || result.status === 503) {
					// Room not found or maintenance mode
					OC.redirect(OC.generateUrl('apps/spreed'))
				}

				if (result.status === 403) {
					// This should not happen anymore since we ask for the password before
					// even trying to join the call, but let's keep it for now.
					OC.dialogs.prompt(
						t('spreed', 'Please enter the password for this call'),
						t('spreed', 'Password required'),
						function(result, password) {
							if (result && password !== '') {
								this.joinRoom(token, password)
							}
						}.bind(this),
						true,
						t('spreed', 'Password'),
						true
					).then(function() {
						const $dialog = $('.oc-dialog:visible')
						$dialog.find('.ui-icon').remove()

						const $buttons = $dialog.find('button')
						$buttons.eq(0).text(t('core', 'Cancel'))
						$buttons.eq(1).text(t('core', 'Submit'))
					})
				}
			}.bind(this),
		})
	})
}

Signaling.Base.prototype._leaveRoomSuccess = function(/* token */) {
	// Override in subclasses if necessary.
}

Signaling.Base.prototype.leaveRoom = function(token) {
	this.leaveCurrentCall()

	this._trigger('leaveRoom', [token])
	this._doLeaveRoom(token)

	return new Promise((resolve, reject) => {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants/active',
			method: 'DELETE',
			async: false,
			success: function() {
				this._leaveRoomSuccess(token)
				resolve()
				// We left the current room.
				if (token === this.currentRoomToken) {
					this.currentRoomToken = null
				}
			}.bind(this),
			error: function() {
				reject(new Error())
			},
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

Signaling.Base.prototype.joinCall = function(token, flags) {
	return new Promise((resolve, reject) => {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			type: 'POST',
			data: {
				flags: flags,
			},
			beforeSend: function(request) {
				request.setRequestHeader('Accept', 'application/json')
			},
			success: function() {
				this.currentCallToken = token
				this.currentCallFlags = flags
				this._trigger('joinCall', [token])
				resolve()
				this._joinCallSuccess(token)
			}.bind(this),
			error: function() {
				reject(new Error())
				// Room not found or maintenance mode
				OC.redirect(OC.generateUrl('apps/spreed'))
			},
		})
	})
}

Signaling.Base.prototype._leaveCallSuccess = function(/* token */) {
	// Override in subclasses if necessary.
}

Signaling.Base.prototype.leaveCall = function(token, keepToken) {
	return new Promise((resolve, reject) => {
		if (!token) {
			reject(new Error())
			return
		}

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			method: 'DELETE',
			async: false,
			success: function() {
				this._trigger('leaveCall', [token, keepToken])
				this._leaveCallSuccess(token)
				resolve()
				// We left the current call.
				if (!keepToken && token === this.currentCallToken) {
					this.currentCallToken = null
					this.currentCallFlags = null
				}
			}.bind(this),
			error: function() {
				reject(new Error())
			},
		})
	})
}

// Connection to the internal signaling server provided by the app.
function Internal(settings) {
	Signaling.Base.prototype.constructor.apply(this, arguments)
	this.hideWarning = settings.hideWarning
	this.spreedArrayConnection = []

	this.pullMessagesFails = 0
	this.pullMessagesRequest = null

	this.isSendingMessages = false
	this.sendInterval = window.setInterval(function() {
		this.sendPendingMessages()
	}.bind(this), 500)
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
		console.log('Forced reconnects with a new session are not supported in the internal signaling; same session as before will be used')
	}

	if (flags !== undefined) {
		this.currentCallFlags = flags
	}

	// FIXME Naive reconnection routine; as the same session is kept peers
	// must be explicitly ended before the reconnection is forced.
	this.leaveCall(this.currentCallToken, true)
	this.joinCall(this.currentCallToken)
}

Signaling.Internal.prototype._sendMessageWithCallback = function(ev) {
	const message = [{
		ev: ev,
	}]

	this._sendMessages(message).done(function(result) {
		this._trigger(ev, [result.ocs.data])
	}.bind(this)).fail(function(/* xhr, textStatus, errorThrown */) {
		console.log('Sending signaling message with callback has failed.')
		// TODO: Add error handling
	})
}

Signaling.Internal.prototype._sendMessages = function(messages) {
	const defer = $.Deferred()
	$.ajax({
		url: OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + this.currentRoomToken,
		type: 'POST',
		data: { messages: JSON.stringify(messages) },
		beforeSend: function(request) {
			request.setRequestHeader('Accept', 'application/json')
		},
		success: function(result) {
			defer.resolve(result)
		},
		error: function(xhr, textStatus, errorThrown) {
			defer.reject(xhr, textStatus, errorThrown)
		},
	})
	return defer
}

Signaling.Internal.prototype._joinRoomSuccess = function(token, sessionId) {
	this.sessionId = sessionId
	this._startPullingMessages()
}

Signaling.Internal.prototype._doLeaveRoom = function(token) {
}

Signaling.Internal.prototype.sendCallMessage = function(data) {
	if (data.type === 'answer') {
		console.log('ANSWER', data)
	} else if (data.type === 'offer') {
		console.log('OFFER', data)
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
	if (!this.currentRoomToken) {
		return
	}

	// Abort ongoing request
	if (this.pullMessagesRequest !== null) {
		this.pullMessagesRequest.abort()
	}

	// Connect to the messages endpoint and pull for new messages
	this.pullMessagesRequest
		= $.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + this.currentRoomToken,
			type: 'GET',
			dataType: 'json',
			beforeSend: function(request) {
				request.setRequestHeader('Accept', 'application/json')
			},
			success: function(result) {
				this.pullMessagesFails = 0
				$.each(result.ocs.data, function(id, message) {
					this._trigger('onBeforeReceiveMessage', [message])
					switch (message.type) {
					case 'usersInRoom':
						this._trigger('usersInRoom', [message.data])
						this._trigger('participantListChanged')
						break
					case 'message':
						if (typeof (message.data) === 'string') {
							message.data = JSON.parse(message.data)
						}
						this._trigger('message', [message.data])
						break
					default:
						console.log('Unknown Signaling Message')
						break
					}
					this._trigger('onAfterReceiveMessage', [message])
				}.bind(this))
				this._startPullingMessages()
			}.bind(this),
			error: function(jqXHR, textStatus/*, errorThrown */) {
				if (jqXHR.status === 0 && textStatus === 'abort') {
					// Request has been aborted. Ignore.
				} else if (jqXHR.status === 404 || jqXHR.status === 403) {
					console.log('Stop pulling messages because room does not exist or is not accessible')
					this._trigger('pullMessagesStoppedOnFail')
				} else if (this.currentRoomToken) {
					if (this.pullMessagesFails >= 3) {
						console.log('Stop pulling messages after repeated failures')

						this._trigger('pullMessagesStoppedOnFail')

						return
					}

					this.pullMessagesFails++
					// Retry to pull messages after 5 seconds
					window.setTimeout(function() {
						this._startPullingMessages()
					}.bind(this), 5000)
				}
			}.bind(this),
		})
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

	this._sendMessages(this.spreedArrayConnection).done(function(/* result */) {
		this.spreedArrayConnection.splice(0, pendingMessagesLength)
		this.isSendingMessages = false
	}.bind(this)).fail(function(/* xhr, textStatus, errorThrown */) {
		console.log('Sending pending signaling messages has failed.')
		this.isSendingMessages = false
	}.bind(this))
}

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
	if (url.indexOf('https://') === 0) {
		url = 'wss://' + url.substr(8)
	} else if (url.indexOf('http://') === 0) {
		url = 'ws://' + url.substr(7)
	}
	if (url[url.length - 1] === '/') {
		url = url.substr(0, url.length - 1)
	}
	this.url = url + '/spreed'
	this.initialReconnectIntervalMs = 1000
	this.maxReconnectIntervalMs = 16000
	this.reconnectIntervalMs = this.initialReconnectIntervalMs
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
	console.log('Reconnect in', interval)
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
	console.log('Connecting to', this.url)
	this.callbacks = {}
	this.id = 1
	this.pendingMessages = []
	this.connected = false
	this._forceReconnect = false
	this.socket = new WebSocket(this.url)
	window.signalingSocket = this.socket
	this.socket.onopen = function(event) {
		console.log('Connected', event)
		this.reconnectIntervalMs = this.initialReconnectIntervalMs
		this.sendHello()
	}.bind(this)
	this.socket.onerror = function(event) {
		console.log('Error', event)
		this.reconnect()
	}.bind(this)
	this.socket.onclose = function(event) {
		console.log('Close', event)
		this.reconnect()
	}.bind(this)
	this.socket.onmessage = function(event) {
		let data = event.data
		if (typeof (data) === 'string') {
			data = JSON.parse(data)
		}
		console.log('Received', data)
		const id = data.id
		if (id && this.callbacks.hasOwnProperty(id)) {
			const cb = this.callbacks[id]
			delete this.callbacks[id]
			cb(data)
		}
		this._trigger('onBeforeReceiveMessage', [data])
		switch (data.type) {
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
			} else {
				// TODO(fancycode): Only fetch properties of room that was modified.
				EventBus.$emit('shouldRefreshConversations')
			}
			break
		case 'event':
			this.processEvent(data)
			break
		case 'message':
			data.message.data.from = data.message.sender.sessionid
			this._trigger('message', [data.message.data])
			break
		default:
			if (!id) {
				console.log('Ignore unknown event', data)
			}
			break
		}
		this._trigger('onAfterReceiveMessage', [data])
	}.bind(this)
}

Signaling.Standalone.prototype.sendBye = function() {
	if (this.connected) {
		this.doSend({
			'type': 'bye',
			'bye': {},
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
		this.sendBye()
	}
	if (this.socket) {
		// Trigger reconnect.
		this.socket.close()
	}
}

Signaling.Standalone.prototype.sendCallMessage = function(data) {
	this.doSend({
		'type': 'message',
		'message': {
			'recipient': {
				'type': 'session',
				'sessionid': data.to,
			},
			'data': data,
		},
	})
}

Signaling.Standalone.prototype.sendRoomMessage = function(data) {
	if (!this.currentCallToken) {
		console.warn('Not in a room, not sending room message', data)
		return
	}

	this.doSend({
		'type': 'message',
		'message': {
			'recipient': {
				'type': 'room',
			},
			'data': data,
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
		msg['id'] = '' + id
	}
	console.log('Sending', msg)
	this.socket.send(JSON.stringify(msg))
}

Signaling.Standalone.prototype.sendHello = function() {
	let msg
	if (this.resumeId) {
		console.log('Trying to resume session', this.sessionId)
		msg = {
			'type': 'hello',
			'hello': {
				'version': '1.0',
				'resumeid': this.resumeId,
			},
		}
	} else {
		// Already reconnected with a new session.
		this._forceReconnect = false
		const url = OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + 'backend'
		msg = {
			'type': 'hello',
			'hello': {
				'version': '1.0',
				'auth': {
					'url': url,
					'params': {
						'userid': this.settings.userId,
						'ticket': this.settings.ticket,
					},
				},
			},
		}
	}
	this.doSend(msg, this.helloResponseReceived.bind(this))
}

Signaling.Standalone.prototype.helloResponseReceived = function(data) {
	console.log('Hello response received', data)
	if (data.type !== 'hello') {
		if (this.resumeId) {
			// Resuming the session failed, reconnect as new session.
			this.resumeId = ''
			this.sendHello()
			return
		}

		// TODO(fancycode): How should this be handled better?
		console.error('Could not connect to server', data)
		this.reconnect()
		return
	}

	const resumedSession = !!this.resumeId
	this.connected = true
	if (this._forceReconnect && resumedSession) {
		console.log('Perform pending forced reconnect')
		this.forceReconnect(true)
		return
	}
	this.sessionId = data.hello.sessionid
	this.resumeId = data.hello.resumeid
	this.features = {}
	let i
	if (data.hello.server && data.hello.server.features) {
		const features = data.hello.server.features
		for (i = 0; i < features.length; i++) {
			this.features[features[i]] = true
		}
	}

	const messages = this.pendingMessages
	this.pendingMessages = []
	for (i = 0; i < messages.length; i++) {
		const msg = messages[i][0]
		const callback = messages[i][1]
		this.doSend(msg, callback)
	}

	this._trigger('connect')
	if (!resumedSession && this.currentRoomToken) {
		this.joinRoom(this.currentRoomToken)
	}
}

Signaling.Standalone.prototype.joinRoom = function(token /*, password */) {
	if (!this.sessionId) {
		// If we would join without a connection to the signaling server here,
		// the room would be re-joined again in the "helloResponseReceived"
		// callback, leading to two entries for anonymous participants.
		console.log('Not connected to signaling server yet, defer joining room', token)
		this.currentRoomToken = token
		return
	}

	return Signaling.Base.prototype.joinRoom.apply(this, arguments)
}

Signaling.Standalone.prototype._joinRoomSuccess = function(token, nextcloudSessionId) {
	if (!this.sessionId) {
		console.log('No hello response received yet, not joining room', token)
		return
	}

	console.log('Join room', token)
	this.doSend({
		'type': 'room',
		'room': {
			'roomid': token,
			// Pass the Nextcloud session id to the signaling server. The
			// session id will be passed through to Nextcloud to check if
			// the (Nextcloud) user is allowed to join the room.
			'sessionid': nextcloudSessionId,
		},
	}, function(data) {
		this.joinResponseReceived(data, token)
	}.bind(this))
}

Signaling.Standalone.prototype.joinCall = function(token, flags) {
	if (this.signalingRoomJoined !== token) {
		console.log('Not joined room yet, not joining call', token)
		this.pendingJoinCall = {
			token: token,
			flags: flags,
		}
		return
	}

	Signaling.Base.prototype.joinCall.apply(this, arguments)
}

Signaling.Standalone.prototype._joinCallSuccess = function(/* token */) {
	// Update room list to fetch modified properties.
	EventBus.$emit('shouldRefreshConversations')
}

Signaling.Standalone.prototype._leaveCallSuccess = function(/* token */) {
	// Update room list to fetch modified properties.
	EventBus.$emit('shouldRefreshConversations')
}

Signaling.Standalone.prototype.joinResponseReceived = function(data, token) {
	console.log('Joined', data, token)
	this.signalingRoomJoined = token
	if (this.pendingJoinCall && token === this.pendingJoinCall.token) {
		this.joinCall(this.pendingJoinCall.token, this.pendingJoinCall.flags)
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
	console.log('Leave room', token)
	this.doSend({
		'type': 'room',
		'room': {
			'roomid': '',
		},
	}, function(data) {
		console.log('Left', data)
		this.signalingRoomJoined = null
		// Any users we previously had in the room also "left" for us.
		const leftUsers = _.keys(this.joinedUsers)
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
		console.log('Unsupported event target', data)
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
			console.log('Users joined', joinedUsers)
			let leftUsers = {}
			if (this.reconnected) {
				this.reconnected = false
				// The browser reconnected, some of the previous sessions
				// may now no longer exist.
				leftUsers = _.extend({}, this.joinedUsers)
			}
			for (i = 0; i < joinedUsers.length; i++) {
				this.joinedUsers[joinedUsers[i].sessionid] = true
				delete leftUsers[joinedUsers[i].sessionid]
			}
			leftUsers = _.keys(leftUsers)
			if (leftUsers.length) {
				this._trigger('usersLeft', [leftUsers])
			}
			this._trigger('usersJoined', [joinedUsers])
			this._trigger('participantListChanged')
		}
		break
	case 'leave':
		leftSessionIds = data.event.leave || []
		if (leftSessionIds.length) {
			console.log('Users left', leftSessionIds)
			for (i = 0; i < leftSessionIds.length; i++) {
				delete this.joinedUsers[leftSessionIds[i]]
			}
			this._trigger('usersLeft', [leftSessionIds])
			this._trigger('participantListChanged')
		}
		break
	case 'message':
		this.processRoomMessageEvent(data.event.message.data)
		break
	default:
		console.log('Unknown room event', data)
		break
	}
}

Signaling.Standalone.prototype.processRoomMessageEvent = function(data) {
	switch (data.type) {
	case 'chat':
		// FIXME this is not listened to
		EventBus.$emit('shouldRefreshChatMessages')
		break
	default:
		console.log('Unknown room message event', data)
	}
}

Signaling.Standalone.prototype.processRoomListEvent = function(data) {
	console.log('Room list event', data)
	EventBus.$emit('shouldRefreshConversations')
}

Signaling.Standalone.prototype.processRoomParticipantsEvent = function(data) {
	switch (data.event.type) {
	case 'update':
		this._trigger('usersChanged', [data.event.update.users || []])
		this._trigger('participantListChanged')
		EventBus.$emit('shouldRefreshConversations')
		break
	default:
		console.log('Unknown room participant event', data)
		break
	}
}

Signaling.Standalone.prototype.requestOffer = function(sessionid, roomType) {
	if (!this.hasFeature('mcu')) {
		console.warn("Can't request an offer without a MCU.")
		return
	}

	if (typeof (sessionid) !== 'string') {
		// Got a user object.
		sessionid = sessionid.sessionId || sessionid.sessionid
	}
	console.log('Request offer from', sessionid)
	this.doSend({
		'type': 'message',
		'message': {
			'recipient': {
				'type': 'session',
				'sessionid': sessionid,
			},
			'data': {
				'type': 'requestoffer',
				'roomType': roomType,
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
	console.log('Send offer to', sessionid)
	this.doSend({
		'type': 'message',
		'message': {
			'recipient': {
				'type': 'session',
				'sessionid': sessionid,
			},
			'data': {
				'type': 'sendoffer',
				'roomType': roomType,
			},
		},
	})
}

export default Signaling

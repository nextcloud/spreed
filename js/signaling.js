(function(OCA, OC) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	function SignalingBase(settings) {
		this.settings = settings;
		this.sessionId = '';
		this.currentRoomToken = null;
		this.currentCallToken = null;
		this.handlers = {};
		this.features = {};
	}

	SignalingBase.prototype.on = function(ev, handler) {
		if (!this.handlers.hasOwnProperty(ev)) {
			this.handlers[ev] = [handler];
		} else {
			this.handlers[ev].push(handler);
		}

		switch (ev) {
			case 'stunservers':
			case 'turnservers':
				var servers = this.settings[ev] || [];
				if (servers.length) {
					// The caller expects the handler to be called when the data
					// is available, so defer to simulate a delayed response.
					_.defer(function() {
						handler(servers);
					});
				}
				break;
		}
	};

	SignalingBase.prototype._trigger = function(ev, args) {
		var handlers = this.handlers[ev];
		if (!handlers) {
			return;
		}

		handlers = handlers.slice(0);
		for (var i = 0, len = handlers.length; i < len; i++) {
			var handler = handlers[i];
			handler.apply(handler, args);
		}
	};

	SignalingBase.prototype.getSessionid = function() {
		return this.sessionId;
	};

	SignalingBase.prototype.disconnect = function() {
		this.sessionId = '';
		this.currentCallToken = null;
	};

	SignalingBase.prototype.hasFeature = function(feature) {
		return this.features && this.features[feature];
	};

	SignalingBase.prototype.emit = function(ev, data) {
		switch (ev) {
			case 'joinRoom':
				this.joinRoom(data);
				break;
			case 'joinCall':
				this.joinCall(data, arguments[2]);
				break;
			case 'leaveRoom':
				this.leaveCurrentRoom();
				break;
			case 'leaveCall':
				this.leaveCurrentCall();
				break;
			case 'message':
				this.sendCallMessage(data);
				break;
		}
	};

	SignalingBase.prototype.leaveCurrentRoom = function() {
		if (this.currentRoomToken) {
			this.leaveRoom(this.currentRoomToken);
			this.currentRoomToken = null;
		}
	};

	SignalingBase.prototype.leaveCurrentCall = function() {
		if (this.currentCallToken) {
			this.leaveCall(this.currentCallToken);
			this.currentCallToken = null;
		}
	};

	SignalingBase.prototype.leaveAllCalls = function() {
		// Override if necessary.
	};

	SignalingBase.prototype.setRoomCollection = function(rooms) {
		this.roomCollection = rooms;
		return this.syncRooms();
	};

	/**
	 * Sets a single room to be synced.
	 *
	 * If there is a RoomCollection set the synchronization will be performed on
	 * the RoomCollection instead and the given room will be ignored; setting a
	 * single room is intended to be used only on public pages.
	 *
	 * @param OCA.SpreedMe.Models.Room room the room to sync.
	 */
	SignalingBase.prototype.setRoom = function(room) {
		this.room = room;
		return this.syncRooms();
	};

	SignalingBase.prototype.syncRooms = function() {
		var defer = $.Deferred();
		if (this.roomCollection && oc_current_user) {
			this.roomCollection.fetch({
				success: function(data) {
					defer.resolve(data);
				}
			});
		} else if (this.room) {
			this.room.fetch({
				success: function(data) {
					defer.resolve(data);
				}
			});
		} else {
			defer.resolve([]);
		}
		return defer;
	};

	SignalingBase.prototype.joinRoom = function(token, password) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants/active',
			type: 'POST',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			data: {
				password: password
			},
			success: function (result) {
				console.log("Joined", result);
				this.currentRoomToken = token;
				this._joinRoomSuccess(token, result.ocs.data.sessionId);
			}.bind(this),
			error: function (result) {
				if (result.status === 404 || result.status === 503) {
					// Room not found or maintenance mode
					OC.redirect(OC.generateUrl('apps/spreed'));
				}

				if (result.status === 403) {
					// This should not happen anymore since we ask for the password before
					// even trying to join the call, but let's keep it for now.
					OC.dialogs.prompt(
						t('spreed', 'Please enter the password for this call'),
						t('spreed','Password required'),
						function (result, password) {
							if (result && password !== '') {
								this.joinRoom(token, password);
							}
						}.bind(this),
						true,
						t('spreed','Password'),
						true
					).then(function() {
						var $dialog = $('.oc-dialog:visible');
						$dialog.find('.ui-icon').remove();

						var $buttons = $dialog.find('button');
						$buttons.eq(0).text(t('core', 'Cancel'));
						$buttons.eq(1).text(t('core', 'Submit'));
					});
				}
			}.bind(this)
		});
	};

	SignalingBase.prototype._leaveRoomSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	SignalingBase.prototype.leaveRoom = function(token) {
		this.leaveCurrentCall();
		this._doLeaveRoom(token);

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants/active',
			method: 'DELETE',
			async: false,
			success: function () {
				this._leaveRoomSuccess(token);
				// We left the current room.
				if (token === this.currentRoomToken) {
					this.currentRoomToken = null;
				}
			}.bind(this)
		});
	};

	SignalingBase.prototype._joinCallSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	SignalingBase.prototype.joinCall = function(token, callback) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			type: 'POST',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function () {
				this.currentCallToken = token;
				this._joinCallSuccess(token);
				// We send an empty call description to simplewebrtc since
				// usersChanged (webrtc.js) will create/remove peer connections
				// with call participants
				var callDescription = {'clients': {}};
				callback('', callDescription);
			}.bind(this),
			error: function () {
				// Room not found or maintenance mode
				OC.redirect(OC.generateUrl('apps/spreed'));
			}.bind(this)
		});
	};

	SignalingBase.prototype._leaveCallSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	SignalingBase.prototype.leaveCall = function(token) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			method: 'DELETE',
			async: false,
			success: function () {
				this._leaveCallSuccess(token);
				// We left the current call.
				if (token === this.currentCallToken) {
					this.currentCallToken = null;
				}
			}.bind(this)
		});
	};

	// Connection to the internal signaling server provided by the app.
	function InternalSignaling(/*settings*/) {
		SignalingBase.prototype.constructor.apply(this, arguments);
		this.spreedArrayConnection = [];

		this.pingFails = 0;
		this.pingInterval = null;
		this.isSendingMessages = false;

		this.pullMessagesRequest = null;

		this.sendInterval = window.setInterval(function(){
			this.sendPendingMessages();
		}.bind(this), 500);
	}

	InternalSignaling.prototype = new SignalingBase();
	InternalSignaling.prototype.constructor = InternalSignaling;

	InternalSignaling.prototype.disconnect = function() {
		this.spreedArrayConnection = [];
		if (this.source) {
			this.source.close();
			this.source = null;
		}
		if (this.sendInterval) {
			window.clearInterval(this.sendInterval);
			this.sendInterval = null;
		}
		if (this.pingInterval) {
			window.clearInterval(this.pingInterval);
			this.pingInterval = null;
		}
		if (this.roomPoller) {
			window.clearInterval(this.roomPoller);
			this.roomPoller = null;
		}
		SignalingBase.prototype.disconnect.apply(this, arguments);
	};

	InternalSignaling.prototype.on = function(ev/*, handler*/) {
		SignalingBase.prototype.on.apply(this, arguments);

		switch (ev) {
			case 'connect':
				// A connection is established if we can perform a request
				// through it.
				this._sendMessageWithCallback(ev);
				break;
		}
	};

	InternalSignaling.prototype._sendMessageWithCallback = function(ev) {
		var message = [{
			ev: ev
		}];

		this._sendMessages(message).done(function(result) {
			this._trigger(ev, [result.ocs.data]);
		}.bind(this)).fail(function(/*xhr, textStatus, errorThrown*/) {
			console.log('Sending signaling message with callback has failed.');
			// TODO: Add error handling
		});
	};

	InternalSignaling.prototype._sendMessages = function(messages) {
		var defer = $.Deferred();
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'signaling',
			type: 'POST',
			data: {messages: JSON.stringify(messages)},
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function (result) {
				defer.resolve(result);
			},
			error: function (xhr, textStatus, errorThrown) {
				defer.reject(xhr, textStatus, errorThrown);
			}
		});
		return defer;
	};

	InternalSignaling.prototype._joinRoomSuccess = function(token, sessionId) {
		this.sessionId = sessionId;
		this._startPingCall();
		this._startPullingMessages();
	};

	InternalSignaling.prototype._doLeaveRoom = function(token) {
		if (token === this.currentRoomToken) {
			this._stopPingCall();
			this._closeEventSource();
		}
	};

	InternalSignaling.prototype.sendCallMessage = function(data) {
		if(data.type === 'answer') {
			console.log("ANSWER", data);
		} else if(data.type === 'offer') {
			console.log("OFFER", data);
		}
		this.spreedArrayConnection.push({
			ev: "message",
			fn: JSON.stringify(data),
			sessionId: this.sessionId
		});
	};

	InternalSignaling.prototype.setRoomCollection = function(/*rooms*/) {
		this._pollForRoomChanges();
		return SignalingBase.prototype.setRoomCollection.apply(this, arguments);
	};

	InternalSignaling.prototype.setRoom = function(/*room*/) {
		this._pollForRoomChanges();
		return SignalingBase.prototype.setRoom.apply(this, arguments);
	};

	InternalSignaling.prototype._pollForRoomChanges = function() {
		if (this.roomPoller) {
			window.clearInterval(this.roomPoller);
		}
		this.roomPoller = window.setInterval(function() {
			this.syncRooms();
		}.bind(this), 10000);
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._getCallPeers = function(token) {
		var defer = $.Deferred();
		$.ajax({
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			success: function (result) {
				var peers = result.ocs.data;
				defer.resolve(peers);
			}
		});
		return defer;
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._startPullingMessages = function() {
		// Abort ongoing request
		if (this.pullMessagesRequest !== null) {
			this.pullMessagesRequest.abort();
		}

		// Connect to the messages endpoint and pull for new messages
		this.pullMessagesRequest =
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'signaling',
			type: 'GET',
			dataType: 'json',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function (result) {
				$.each(result.ocs.data, function(id, message) {
					switch(message.type) {
						case "usersInRoom":
							this._trigger('usersInRoom', [message.data]);
							break;
						case "message":
							if (typeof(message.data) === 'string') {
								message.data = JSON.parse(message.data);
							}
							this._trigger('message', [message.data]);
							break;
						default:
							console.log('Unknown Signaling Message');
							break;
					}
				}.bind(this));
				this._startPullingMessages();
			}.bind(this),
			error: function (jqXHR, textStatus/*, errorThrown*/) {
				if (jqXHR.status === 0 && textStatus === 'abort') {
					// Resquest has been aborted. Ignore.
				} else {
					//Retry to pull messages after 5 seconds
					window.setTimeout(function() {
						this._startPullingMessages();
					}.bind(this), 5000);
				}
			}.bind(this)
		});
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._closeEventSource = function() {
		if (this.source) {
			this.source.close();
			this.source = null;
		}
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype.sendPendingMessages = function() {
		if (!this.spreedArrayConnection.length || this.isSendingMessages) {
			return;
		}

		var pendingMessagesLength = this.spreedArrayConnection.length;
		this.isSendingMessages = true;

		this._sendMessages(this.spreedArrayConnection).done(function(/*result*/) {
			this.spreedArrayConnection.splice(0, pendingMessagesLength);
			this.isSendingMessages = false;
		}.bind(this)).fail(function(/*xhr, textStatus, errorThrown*/) {
			console.log('Sending pending signaling messages has failed.');
			this.isSendingMessages = false;
		}.bind(this));
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._startPingCall = function() {
		this._pingCall();

		// Send a ping to the server all 5 seconds to ensure that the connection
		// is still alive.
		this.pingInterval = window.setInterval(function() {
			this._pingCall();
		}.bind(this), 5000);
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._stopPingCall = function() {
		if (this.pingInterval) {
			window.clearInterval(this.pingInterval);
			this.pingInterval = null;
		}
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._pingCall = function() {
		if (!this.currentRoomToken) {
			return;
		}

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + this.currentRoomToken + '/ping',
			method: 'POST'
		}).done(function() {
			this.pingFails = 0;
		}.bind(this)).fail(function(xhr) {
			// If there is an error when pinging, retry for 3 times.
			if (xhr.status !== 404 && this.pingFails < 3) {
				this.pingFails++;
				return;
			}
			OCA.SpreedMe.Calls.leaveCurrentCall(false);
		}.bind(this));
	};

	function StandaloneSignaling(settings, urls) {
		SignalingBase.prototype.constructor.apply(this, arguments);
		if (typeof(urls) === "string") {
			urls = [urls];
		}
		// We can connect to any of the servers.
		var idx = Math.floor(Math.random() * urls.length);
		// TODO(jojo): Try other server if connection fails.
		var url = urls[idx];
		// Make sure we are using websocket urls.
		if (url.indexOf("https://") === 0) {
			url = "wss://" + url.substr(8);
		} else if (url.indexOf("http://") === 0) {
			url = "ws://" + url.substr(7);
		}
		if (url[url.length - 1] === "/") {
			url = url.substr(0, url.length - 1);
		}
		this.url = url + "/spreed";
		this.initialReconnectIntervalMs = 1000;
		this.maxReconnectIntervalMs = 16000;
		this.reconnectIntervalMs = this.initialReconnectIntervalMs;
		this.joinedUsers = {};
		this.rooms = [];
		this.connect();
	}

	StandaloneSignaling.prototype = new SignalingBase();
	StandaloneSignaling.prototype.constructor = StandaloneSignaling;

	StandaloneSignaling.prototype.reconnect = function() {
		if (this.reconnectTimer) {
			return;
		}

		// Wiggle interval a little bit to prevent all clients from connecting
		// simultaneously in case the server connection is interrupted.
		var interval = this.reconnectIntervalMs - (this.reconnectIntervalMs / 2) + (this.reconnectIntervalMs * Math.random());
		console.log("Reconnect in", interval);
		this.reconnected = true;
		this.reconnectTimer = window.setTimeout(function() {
			this.reconnectTimer = null;
			this.connect();
		}.bind(this), interval);
		this.reconnectIntervalMs = this.reconnectIntervalMs * 2;
		if (this.reconnectIntervalMs > this.maxReconnectIntervalMs) {
			this.reconnectIntervalMs = this.maxReconnectIntervalMs;
		}
		if (this.socket) {
			this.socket.close();
			this.socket = null;
		}
	};

	StandaloneSignaling.prototype.connect = function() {
		console.log("Connecting to", this.url);
		this.callbacks = {};
		this.id = 1;
		this.pendingMessages = [];
		this.connected = false;
		this.socket = new WebSocket(this.url);
		window.signalingSocket = this.socket;
		this.socket.onopen = function(event) {
			console.log("Connected", event);
			this.reconnectIntervalMs = this.initialReconnectIntervalMs;
			this.sendHello();
		}.bind(this);
		this.socket.onerror = function(event) {
			console.log("Error", event);
			this.reconnect();
		}.bind(this);
		this.socket.onclose = function(event) {
			console.log("Close", event);
			this.reconnect();
		}.bind(this);
		this.socket.onmessage = function(event) {
			var data = event.data;
			if (typeof(data) === "string") {
				data = JSON.parse(data);
			}
			console.log("Received", data);
			var id = data.id;
			if (id && this.callbacks.hasOwnProperty(id)) {
				var cb = this.callbacks[id];
					delete this.callbacks[id];
				cb(data);
			}
			switch (data.type) {
				case "hello":
					if (!id) {
						// Only process if not received as result of our "hello".
						this.helloResponseReceived(data);
					}
					break;
				case "room":
					if (this.currentRoomToken && data.room.roomid !== this.currentRoomToken) {
						this._trigger('roomChanged', [this.currentRoomToken, data.room.roomid]);
						this.joinedUsers = {};
						this.currentRoomToken = null;
					} else {
						// TODO(fancycode): Only fetch properties of room that was modified.
						this.internalSyncRooms();
					}
					break;
				case "event":
					this.processEvent(data);
					break;
				case "message":
					data.message.data.from = data.message.sender.sessionid;
					this._trigger("message", [data.message.data]);
					break;
				default:
					if (!id) {
						console.log("Ignore unknown event", data);
					}
					break;
			}
		}.bind(this);
	};

	StandaloneSignaling.prototype.disconnect = function() {
		if (this.socket) {
			this.doSend({
				"type": "bye",
				"bye": {}
			});
			this.socket.close();
			this.socket = null;
		}
		SignalingBase.prototype.disconnect.apply(this, arguments);
	};

	StandaloneSignaling.prototype.sendCallMessage = function(data) {
		this.doSend({
			"type": "message",
			"message": {
				"recipient": {
					"type": "session",
					"sessionid": data.to
				},
				"data": data
			}
		});
	};

	StandaloneSignaling.prototype.doSend = function(msg, callback) {
		if (!this.connected && msg.type !== "hello") {
			// Defer sending any messages until the hello rsponse has been
			// received.
			this.pendingMessages.push([msg, callback]);
			return;
		}

		if (callback) {
			var id = this.id++;
			this.callbacks[id] = callback;
			msg["id"] = ""+id;
		}
		console.log("Sending", msg);
		this.socket.send(JSON.stringify(msg));
	};

	StandaloneSignaling.prototype.sendHello = function() {
		var msg;
		if (this.resumeId) {
			console.log("Trying to resume session", this.sessionId);
			msg = {
				"type": "hello",
				"hello": {
					"version": "1.0",
					"resumeid": this.resumeId
				}
			};
		} else {
			var user = OC.getCurrentUser();
			var url = OC.generateUrl("/ocs/v2.php/apps/spreed/api/v1/signaling/backend");
			msg = {
				"type": "hello",
				"hello": {
					"version": "1.0",
					"auth": {
						"url": OC.getProtocol() + "://" + OC.getHost() + url,
						"params": {
							"userid": user.uid,
							"ticket": this.settings.ticket
						}
					}
				}
			};
		}
		this.doSend(msg, this.helloResponseReceived.bind(this));
	};

	StandaloneSignaling.prototype.helloResponseReceived = function(data) {
		console.log("Hello response received", data);
		if (data.type !== "hello") {
			if (this.resumeId) {
				// Resuming the session failed, reconnect as new session.
				this.resumeId = '';
				this.sendHello();
				return;
			}

			// TODO(fancycode): How should this be handled better?
			console.error("Could not connect to server", data);
			this.reconnect();
			return;
		}

		var resumedSession = !!this.resumeId;
		this.connected = true;
		this.sessionId = data.hello.sessionid;
		this.resumeId = data.hello.resumeid;
		this.features = {};
		var i;
		if (data.hello.server && data.hello.server.features) {
			var features = data.hello.server.features;
			for (i = 0; i < features.length; i++) {
				this.features[features[i]] = true;
			}
		}

		var messages = this.pendingMessages;
		this.pendingMessages = [];
		for (i = 0; i < messages.length; i++) {
			var msg = messages[i][0];
			var callback = messages[i][1];
			this.doSend(msg, callback);
		}

		this._trigger("connect");
		if (this.reconnected) {
			// The list of rooms might have changed while we were not connected,
			// so perform resync once.
			this.internalSyncRooms();
		}
		if (!resumedSession && this.currentRoomToken) {
			this.joinRoom(this.currentRoomToken);
		}
	};

	StandaloneSignaling.prototype.setRoom = function(/* room */) {
		SignalingBase.prototype.setRoom.apply(this, arguments);
		return this.internalSyncRooms();
	};

	StandaloneSignaling.prototype._joinRoomSuccess = function(token, nextcloudSessionId) {
		console.log("Join room", token);
		this.doSend({
			"type": "room",
			"room": {
				"roomid": token,
				// Pass the Nextcloud session id to the signaling server. The
				// session id will be passed through to Nextcloud to check if
				// the (Nextcloud) user is allowed to join the room.
				"sessionid": nextcloudSessionId,
			}
		}, function(data) {
			this.joinResponseReceived(data, token);
		}.bind(this));
	};

	StandaloneSignaling.prototype._joinCallSuccess = function(/* token */) {
		// Update room list to fetch modified properties.
		this.internalSyncRooms();
	};

	StandaloneSignaling.prototype._leaveCallSuccess = function(/* token */) {
		// Update room list to fetch modified properties.
		this.internalSyncRooms();
	};

	StandaloneSignaling.prototype.joinResponseReceived = function(data, token) {
		console.log("Joined", data, token);
		if (this.roomCollection) {
			// The list of rooms is not fetched from the server. Update ping
			// of joined room so it gets sorted to the top.
			this.roomCollection.forEach(function(room) {
				if (room.get('token') === token) {
					room.set('lastPing', (new Date()).getTime() / 1000);
				}
			});
			this.roomCollection.sort();
		}
	};

	StandaloneSignaling.prototype._doLeaveRoom = function(token) {
		console.log("Leave room", token);
		this.doSend({
			"type": "room",
			"room": {
				"roomid": ""
			}
		}, function(data) {
			console.log("Left", data);
			// Any users we previously had in the room also "left" for us.
			var leftUsers = _.keys(this.joinedUsers);
			if (leftUsers.length) {
				this._trigger("usersLeft", [leftUsers]);
			}
			this.joinedUsers = {};
		}.bind(this));
	};

	StandaloneSignaling.prototype.processEvent = function(data) {
		switch (data.event.target) {
			case "room":
				this.processRoomEvent(data);
				break;
			case "roomlist":
				this.processRoomListEvent(data);
				break;
			case "participants":
				this.processRoomParticipantsEvent(data);
				break;
			default:
				console.log("Unsupported event target", data);
				break;
		}
	};

	StandaloneSignaling.prototype.processRoomEvent = function(data) {
		var i;
		switch (data.event.type) {
			case "join":
				var joinedUsers = data.event.join || [];
				if (joinedUsers.length) {
					console.log("Users joined", joinedUsers);
					var leftUsers = {};
					if (this.reconnected) {
						this.reconnected = false;
						// The browser reconnected, some of the previous sessions
						// may now no longer exist.
						leftUsers = _.extend({}, this.joinedUsers);
					}
					for (i = 0; i < joinedUsers.length; i++) {
						this.joinedUsers[joinedUsers[i].sessionid] = true;
						delete leftUsers[joinedUsers[i].sessionid];
					}
					leftUsers = _.keys(leftUsers);
					if (leftUsers.length) {
						this._trigger("usersLeft", [leftUsers]);
					}
					this._trigger("usersJoined", [joinedUsers]);
				}
				break;
			case "leave":
				var leftSessionIds = data.event.leave || [];
				if (leftSessionIds.length) {
					console.log("Users left", leftSessionIds);
					for (i = 0; i < leftSessionIds.length; i++) {
						delete this.joinedUsers[leftSessionIds[i]];
					}
					this._trigger("usersLeft", [leftSessionIds]);
				}
				break;
			default:
				console.log("Unknown room event", data);
				break;
		}
	};

	StandaloneSignaling.prototype.setRoomCollection = function(/* rooms */) {
		SignalingBase.prototype.setRoomCollection.apply(this, arguments);
		// Retrieve initial list of rooms for this user.
		return this.internalSyncRooms();
	};

	StandaloneSignaling.prototype.syncRooms = function() {
		if (this.pending_sync) {
			// A sync request is already in progress, don't start another one.
			return this.pending_sync;
		}

		// Never manually sync rooms, will be done based on notifications
		// from the signaling server.
		var defer = $.Deferred();
		defer.resolve(this.rooms);
		return defer;
	};

	StandaloneSignaling.prototype.internalSyncRooms = function() {
		if (this.pending_sync) {
			// A sync request is already in progress, don't start another one.
			return this.pending_sync;
		}

		var defer = $.Deferred();
		this.pending_sync = SignalingBase.prototype.syncRooms.apply(this, arguments);
		this.pending_sync.then(function(rooms) {
			this.pending_sync = null;
			this.rooms = rooms;
			defer.resolve(rooms);
		}.bind(this));
		return defer;
	};

	StandaloneSignaling.prototype.processRoomListEvent = function(data) {
		console.log("Room list event", data);
		this.internalSyncRooms();
	};

	StandaloneSignaling.prototype.processRoomParticipantsEvent = function(data) {
		switch (data.event.type) {
			case "update":
				this._trigger("usersChanged", [data.event.update.users]);
				this.internalSyncRooms();
				break;
			default:
				console.log("Unknown room participant event", data);
				break;
		}
	};

	OCA.SpreedMe.createSignalingConnection = function() {
		var settings = $("#app #signaling-settings").text();
		if (settings) {
			settings = JSON.parse(settings);
		} else {
			settings = {};
		}
		var urls = settings['server'];
		if (urls && urls.length) {
			return new StandaloneSignaling(settings, urls);
		} else {
			return new InternalSignaling(settings);
		}
	};

})(OCA, OC);

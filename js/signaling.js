/** @global console */
(function(OCA, OC, $) {
	'use strict';

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.Signaling = {
		Base: {},
		Internal: {},
		Standalone: {},

		/**
		* Loads the signaling settings.
		*
		* The signaling settings are set in the DOM element in which
		* "createConnection" expects to find them; if the DOM element already
		* exists it is assumed that the settings are already loaded.
		*
		* @return Deferred a Deferred object that will be resolved once the
		*         settings are loaded.
		*/
		loadSettings: function() {
			var deferred = $.Deferred();

			if ($('#app #signaling-settings').length > 0) {
				deferred.resolve();

				return deferred.promise();
			}

			if ($('#app').length === 0) {
				$('body').append('<div id="app"></div>');
			}
			$('#app').append('<script type="text/json" id="signaling-settings"></script>');

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + 'settings',
				type: 'GET',
				dataType: 'json',
				success: function (result) {
					$('#app #signaling-settings').text(JSON.stringify(result.ocs.data));

					deferred.resolve();
				},
				error: function (xhr, textStatus, errorThrown) {
					deferred.reject(xhr, textStatus, errorThrown);
				}
			});

			return deferred.promise();
		},

		createConnection: function() {
			var settings = $("#app #signaling-settings").text();
			if (settings) {
				settings = JSON.parse(settings);
			} else {
				settings = {};
			}
			var urls = settings.server;
			if (urls && urls.length) {
				return new OCA.Talk.Signaling.Standalone(settings, urls);
			} else {
				return new OCA.Talk.Signaling.Internal(settings);
			}
		}
	};

	function Base(settings) {
		this.settings = settings;
		this.sessionId = '';
		this.currentRoomToken = null;
		this.currentCallToken = null;
		this.currentCallFlags = null;
		this.handlers = {};
		this.features = {};
		this.pendingChatRequests = [];
		this._lastChatMessagesFetch = null;
		this.chatBatchSize = 100;
		this._sendVideoIfAvailable = true;
	}

	OCA.Talk.Signaling.Base = Base;
	OCA.Talk.Signaling.Base.prototype.on = function(ev, handler) {
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

	OCA.Talk.Signaling.Base.prototype.off = function(ev, handler) {
		if (!this.handlers.hasOwnProperty(ev)) {
			return;
		}

		var pos = this.handlers[ev].indexOf(handler);
		while (pos !== -1) {
			this.handlers[ev].splice(pos, 1);
			pos = this.handlers[ev].indexOf(handler);
		}
	};

	OCA.Talk.Signaling.Base.prototype._trigger = function(ev, args) {
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

	OCA.Talk.Signaling.Base.prototype.isNoMcuWarningEnabled = function() {
		return !this.settings.hideWarning;
	};

	OCA.Talk.Signaling.Base.prototype.getSessionid = function() {
		return this.sessionId;
	};

	OCA.Talk.Signaling.Base.prototype.getCurrentCallFlags = function() {
		return this.currentCallFlags;
	};

	OCA.Talk.Signaling.Base.prototype.disconnect = function() {
		this.sessionId = '';
		this.currentCallToken = null;
		this.currentCallFlags = null;
	};

	OCA.Talk.Signaling.Base.prototype.hasFeature = function(feature) {
		return this.features && this.features[feature];
	};

	OCA.Talk.Signaling.Base.prototype.emit = function(ev, data) {
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

	OCA.Talk.Signaling.Base.prototype.leaveCurrentRoom = function() {
		if (this.currentRoomToken) {
			this.leaveRoom(this.currentRoomToken);
			this.currentRoomToken = null;
		}
	};

	OCA.Talk.Signaling.Base.prototype.leaveCurrentCall = function() {
		if (this.currentCallToken) {
			this.leaveCall(this.currentCallToken);
			this.currentCallToken = null;
			this.currentCallFlags = null;
		}
	};

	OCA.Talk.Signaling.Base.prototype.setRoomCollection = function(rooms) {
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
	OCA.Talk.Signaling.Base.prototype.setRoom = function(room) {
		this.room = room;
		return this.syncRooms();
	};

	OCA.Talk.Signaling.Base.prototype.syncRooms = function() {
		var defer = $.Deferred();
		if (this.roomCollection && OCA.Talk.getCurrentUser().uid) {
			this.roomCollection.fetch({
				success: function(roomCollection) {
					defer.resolve(roomCollection);
				},
				error: function(roomCollection, response) {
					defer.reject(roomCollection, response);
				}
			});
		} else if (this.room) {
			this.room.fetch({
				success: function(room) {
					defer.resolve(room);
				},
				error: function(room, response) {
					defer.reject(room, response);
				}
			});
		} else {
			defer.resolve([]);
		}
		return defer;
	};

	OCA.Talk.Signaling.Base.prototype.joinRoom = function(token, password) {
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
				this._trigger('joinRoom', [token]);
				this._runPendingChatRequests();
				if (this.currentCallToken === token) {
					// We were in this call before, join again.
					this.joinCall(token, this.currentCallFlags);
				} else {
					this.currentCallToken = null;
					this.currentCallFlags = null;
				}
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

	OCA.Talk.Signaling.Base.prototype._leaveRoomSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	OCA.Talk.Signaling.Base.prototype.leaveRoom = function(token) {
		this.leaveCurrentCall();

		this._trigger('leaveRoom', [token]);
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

	OCA.Talk.Signaling.Base.prototype.getSendVideoIfAvailable = function() {
		return this._sendVideoIfAvailable;
	};

	OCA.Talk.Signaling.Base.prototype.setSendVideoIfAvailable = function(sendVideoIfAvailable) {
		this._sendVideoIfAvailable = sendVideoIfAvailable;
	};

	OCA.Talk.Signaling.Base.prototype._joinCallSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	OCA.Talk.Signaling.Base.prototype.joinCall = function(token, flags) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			type: 'POST',
			data: {
				flags: flags
			},
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function () {
				this.currentCallToken = token;
				this.currentCallFlags = flags;
				this._trigger('joinCall', [token]);
				this._joinCallSuccess(token);
			}.bind(this),
			error: function () {
				// Room not found or maintenance mode
				OC.redirect(OC.generateUrl('apps/spreed'));
			}.bind(this)
		});
	};

	OCA.Talk.Signaling.Base.prototype._leaveCallSuccess = function(/* token */) {
		// Override in subclasses if necessary.
	};

	OCA.Talk.Signaling.Base.prototype.leaveCall = function(token, keepToken) {

		if (!token) {
			return;
		}

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			method: 'DELETE',
			async: false,
			success: function () {
				this._trigger('leaveCall', [token, keepToken]);
				this._leaveCallSuccess(token);
				// We left the current call.
				if (!keepToken && token === this.currentCallToken) {
					this.currentCallToken = null;
					this.currentCallFlags = null;
				}
			}.bind(this)
		});
	};

	OCA.Talk.Signaling.Base.prototype._runPendingChatRequests = function() {
		while (this.pendingChatRequests.length) {
			var item = this.pendingChatRequests.shift();
			this._doReceiveChatMessages.apply(this, item);
		}
	};

	OCA.Talk.Signaling.Base.prototype.receiveChatMessages = function(lastKnownMessageId) {
		var defer = $.Deferred();
		if (!this.currentRoomToken) {
			// Not in a room yet, defer loading of messages.
			this.pendingChatRequests.push([defer, lastKnownMessageId]);
			return defer;
		}

		return this._doReceiveChatMessages(defer, lastKnownMessageId);
	};

	OCA.Talk.Signaling.Base.prototype._getChatRequestData = function(lastKnownMessageId) {
		return {
			lastKnownMessageId: lastKnownMessageId,
			limit: this.chatBatchSize,
			lookIntoFuture: 1
		};
	};

	OCA.Talk.Signaling.Base.prototype._doReceiveChatMessages = function(defer, lastKnownMessageId) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/chat', 2) + this.currentRoomToken,
			method: 'GET',
			data: this._getChatRequestData(lastKnownMessageId),
			beforeSend: function (request) {
				defer.notify(request);
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function (data, status, request) {
				if (status === "notmodified") {
					defer.resolve(null, request);
				} else {
					defer.resolve(data.ocs.data, request);
				}
			}.bind(this),
			error: function (result) {
				defer.reject(result);
			}
		});
		return defer;
	};

	OCA.Talk.Signaling.Base.prototype.startReceiveMessages = function(lastKnownMessageId) {
		this._waitTimeUntilRetry = 1;
		this.receiveMessagesAgain = true;
		this.lastKnownMessageId = lastKnownMessageId;

		this._receiveChatMessages();
	};

	OCA.Talk.Signaling.Base.prototype.stopReceiveMessages = function() {
		this.receiveMessagesAgain = false;
		if (this._lastChatMessagesFetch !== null) {
			this._lastChatMessagesFetch.abort();
		}
	};

	OCA.Talk.Signaling.Base.prototype._receiveChatMessages = function() {
		if (this._lastChatMessagesFetch !== null) {
			// Another request is currently in progress.
			return;
		}

		this.receiveChatMessages(this.lastKnownMessageId)
			.progress(this._messagesReceiveStart.bind(this))
			.done(this._messagesReceiveSuccess.bind(this))
			.fail(this._messagesReceiveError.bind(this));
	};

	OCA.Talk.Signaling.Base.prototype._messagesReceiveStart = function(xhr) {
		this._lastChatMessagesFetch = xhr;
	};

	OCA.Talk.Signaling.Base.prototype._messagesReceiveSuccess = function(messages, xhr) {
		var lastKnownMessageId = xhr.getResponseHeader("X-Chat-Last-Given");
		if (lastKnownMessageId !== null) {
			this.lastKnownMessageId = lastKnownMessageId;
		}

		this._lastChatMessagesFetch = null;

		this._waitTimeUntilRetry = 1;

		// Fetch more messages if PHP backend, or if the returned status is not
		// "304 Not modified" (as in that case there could be more messages that
		// need to be fetched).
		if (this.receiveMessagesAgain || xhr.status !== 304) {
			this._receiveChatMessages();
		}

		if (messages && messages.length) {
			this._trigger("chatMessagesReceived", [messages]);
		}
	};

	OCA.Talk.Signaling.Base.prototype._retryChatLoadingOnError = function() {
		return this.receiveMessagesAgain;
	};

	OCA.Talk.Signaling.Base.prototype._messagesReceiveError = function(/* result */) {
		this._lastChatMessagesFetch = null;

		if (this._retryChatLoadingOnError()) {
			_.delay(_.bind(this._receiveChatMessages, this), this._waitTimeUntilRetry * 1000);

			// Increase the wait time until retry to at most 64 seconds.
			if (this._waitTimeUntilRetry < 64) {
				this._waitTimeUntilRetry *= 2;
			}
		}
	};

	// Connection to the internal signaling server provided by the app.
	function Internal(settings) {
		OCA.Talk.Signaling.Base.prototype.constructor.apply(this, arguments);
		this.hideWarning = settings.hideWarning;
		this.spreedArrayConnection = [];

		this.pullMessagesFails = 0;
		this.pullMessagesRequest = null;

		this.isSendingMessages = false;
		this.sendInterval = window.setInterval(function(){
			this.sendPendingMessages();
		}.bind(this), 500);
	}

	Internal.prototype = new OCA.Talk.Signaling.Base();
	Internal.prototype.constructor = Internal;
	OCA.Talk.Signaling.Internal = Internal;

	OCA.Talk.Signaling.Internal.prototype.disconnect = function() {
		this.spreedArrayConnection = [];
		if (this.sendInterval) {
			window.clearInterval(this.sendInterval);
			this.sendInterval = null;
		}
		if (this.roomPoller) {
			window.clearInterval(this.roomPoller);
			this.roomPoller = null;
		}
		OCA.Talk.Signaling.Base.prototype.disconnect.apply(this, arguments);
	};

	OCA.Talk.Signaling.Internal.prototype.on = function(ev/*, handler*/) {
		OCA.Talk.Signaling.Base.prototype.on.apply(this, arguments);

		switch (ev) {
			case 'connect':
				// A connection is established if we can perform a request
				// through it.
				this._sendMessageWithCallback(ev);
				break;
		}
	};

	OCA.Talk.Signaling.Internal.prototype.forceReconnect = function(newSession, flags) {
		if (newSession) {
			console.log('Forced reconnects with a new session are not supported in the internal signaling; same session as before will be used');
		}

		if (flags !== undefined) {
			this.currentCallFlags = flags;
		}

		// FIXME Naive reconnection routine; as the same session is kept peers
		// must be explicitly ended before the reconnection is forced.
		this.leaveCall(this.currentCallToken, true);
		this.joinCall(this.currentCallToken);
	};

	OCA.Talk.Signaling.Internal.prototype._sendMessageWithCallback = function(ev) {
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

	OCA.Talk.Signaling.Internal.prototype._sendMessages = function(messages) {
		var defer = $.Deferred();
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + this.currentRoomToken,
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

	OCA.Talk.Signaling.Internal.prototype._joinRoomSuccess = function(token, sessionId) {
		this.sessionId = sessionId;
		this._startPullingMessages();
	};

	OCA.Talk.Signaling.Internal.prototype._doLeaveRoom = function(token) {
		if (token === this.currentRoomToken && !this.roomCollection) {
			window.clearInterval(this.roomPoller);
			this.roomPoller = null;
		}
	};

	OCA.Talk.Signaling.Internal.prototype.sendCallMessage = function(data) {
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

	OCA.Talk.Signaling.Internal.prototype.setRoomCollection = function(/*rooms*/) {
		this._pollForRoomChanges();
		return OCA.Talk.Signaling.Base.prototype.setRoomCollection.apply(this, arguments);
	};

	OCA.Talk.Signaling.Internal.prototype.setRoom = function(/*room*/) {
		this._pollForRoomChanges();
		return OCA.Talk.Signaling.Base.prototype.setRoom.apply(this, arguments);
	};

	OCA.Talk.Signaling.Internal.prototype._pollForRoomChanges = function() {
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
	OCA.Talk.Signaling.Internal.prototype._startPullingMessages = function() {
		if (!this.currentRoomToken) {
			return;
		}

		// Abort ongoing request
		if (this.pullMessagesRequest !== null) {
			this.pullMessagesRequest.abort();
		}

		// Connect to the messages endpoint and pull for new messages
		this.pullMessagesRequest =
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + this.currentRoomToken,
			type: 'GET',
			dataType: 'json',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function (result) {
				this.pullMessagesFails = 0;
				$.each(result.ocs.data, function(id, message) {
					this._trigger('onBeforeReceiveMessage', [message]);
					switch(message.type) {
						case "usersInRoom":
							this._trigger('usersInRoom', [message.data]);
							this._trigger('participantListChanged');
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
					this._trigger('onAfterReceiveMessage', [message]);
				}.bind(this));
				this._startPullingMessages();
			}.bind(this),
			error: function (jqXHR, textStatus/*, errorThrown*/) {
				if (jqXHR.status === 0 && textStatus === 'abort') {
					// Request has been aborted. Ignore.
				} else if (jqXHR.status === 404 || jqXHR.status === 403) {
					console.log('Stop pulling messages because room does not exist or is not accessible');
					this._trigger('pullMessagesStoppedOnFail');
				} else if (this.currentRoomToken) {
					if (this.pullMessagesFails >= 3) {
						console.log('Stop pulling messages after repeated failures');

						this._trigger('pullMessagesStoppedOnFail');

						return;
					}

					this.pullMessagesFails++;
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
	OCA.Talk.Signaling.Internal.prototype.sendPendingMessages = function() {
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

	function Standalone(settings, urls) {
		OCA.Talk.Signaling.Base.prototype.constructor.apply(this, arguments);
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
		window.setInterval(function() {
			// Update the room list all 30 seconds to check for new messages and
			// mentions as well as marking them read via other devices.
			this.internalSyncRooms();
		}.bind(this), 30000);
		this.connect();
	}

	Standalone.prototype = new OCA.Talk.Signaling.Base();
	Standalone.prototype.constructor = Standalone;
	OCA.Talk.Signaling.Standalone = Standalone;

	OCA.Talk.Signaling.Standalone.prototype.reconnect = function() {
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

	OCA.Talk.Signaling.Standalone.prototype.connect = function() {
		console.log("Connecting to", this.url);
		this.callbacks = {};
		this.id = 1;
		this.pendingMessages = [];
		this.connected = false;
		this._forceReconnect = false;
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
			this._trigger('onBeforeReceiveMessage', [data]);
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
			this._trigger('onAfterReceiveMessage', [data]);
		}.bind(this);
	};

	OCA.Talk.Signaling.Standalone.prototype.sendBye = function() {
		if (this.connected) {
			this.doSend({
				"type": "bye",
				"bye": {}
			});
		}
		this.resumeId = null;
		this.signalingRoomJoined = null;
	};

	OCA.Talk.Signaling.Standalone.prototype.disconnect = function() {
		this.sendBye();
		if (this.socket) {
			this.socket.close();
			this.socket = null;
		}
		OCA.Talk.Signaling.Base.prototype.disconnect.apply(this, arguments);
	};

	OCA.Talk.Signaling.Standalone.prototype.forceReconnect = function(newSession, flags) {
		if (flags !== undefined) {
			this.currentCallFlags = flags;
		}

		if (!this.connected) {
			if (!newSession) {
				// Not connected, will do reconnect anyway.
				return;
			}

			this._forceReconnect = true;
			this.resumeId = null;
			this.signalingRoomJoined = null;
			return;
		}

		this._forceReconnect = false;
		if (newSession) {
			if (this.currentCallToken) {
				// Mark this session as "no longer in the call".
				this.leaveCall(this.currentCallToken, true);
			}
			this.sendBye();
		}
		if (this.socket) {
			// Trigger reconnect.
			this.socket.close();
		}
	};

	OCA.Talk.Signaling.Standalone.prototype.sendCallMessage = function(data) {
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

	OCA.Talk.Signaling.Standalone.prototype.sendRoomMessage = function(data) {
		if (!this.currentCallToken) {
			console.warn("Not in a room, not sending room message", data);
			return;
		}

		this.doSend({
			"type": "message",
			"message": {
				"recipient": {
					"type": "room"
				},
				"data": data
			}
		});
	};

	OCA.Talk.Signaling.Standalone.prototype.doSend = function(msg, callback) {
		if (!this.connected && msg.type !== "hello" || this.socket === null) {
			// Defer sending any messages until the hello response has been
			// received and when the socket is open
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

	OCA.Talk.Signaling.Standalone.prototype.sendHello = function() {
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
			// Already reconnected with a new session.
			this._forceReconnect = false;
			var user = OCA.Talk.getCurrentUser();
			var url = OC.linkToOCS('apps/spreed/api/v1/signaling', 2) + 'backend';
			msg = {
				"type": "hello",
				"hello": {
					"version": "1.0",
					"auth": {
						"url": url,
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

	OCA.Talk.Signaling.Standalone.prototype.helloResponseReceived = function(data) {
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
		if (this._forceReconnect && resumedSession) {
			console.log("Perform pending forced reconnect");
			this.forceReconnect(true);
			return;
		}
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
			// so perform resync once; force it to ensure that the resync is not
			// waiting to retry a pending one failed due to a lack of
			// connection.
			this._forceInternalSyncRooms();
			// Load any chat messages that might have been missed.
			this._receiveChatMessages();
		}
		if (!resumedSession && this.currentRoomToken) {
			this.joinRoom(this.currentRoomToken);
		}
	};

	OCA.Talk.Signaling.Standalone.prototype.setRoom = function(/* room */) {
		OCA.Talk.Signaling.Base.prototype.setRoom.apply(this, arguments);
		return this.internalSyncRooms();
	};

	OCA.Talk.Signaling.Standalone.prototype.joinRoom = function(token /*, password */) {
		if (!this.sessionId) {
			// If we would join without a connection to the signaling server here,
			// the room would be re-joined again in the "helloResponseReceived"
			// callback, leading to two entries for anonymous participants.
			console.log("Not connected to signaling server yet, defer joining room", token);
			this.currentRoomToken = token;
			return;
		}

		return OCA.Talk.Signaling.Base.prototype.joinRoom.apply(this, arguments);
	};

	OCA.Talk.Signaling.Standalone.prototype._joinRoomSuccess = function(token, nextcloudSessionId) {
		if (!this.sessionId) {
			console.log("No hello response received yet, not joining room", token);
			return;
		}

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

	OCA.Talk.Signaling.Standalone.prototype.joinCall = function(token, flags) {
		if (this.signalingRoomJoined !== token) {
			console.log("Not joined room yet, not joining call", token);
			this.pendingJoinCall = {
				token: token,
				flags: flags
			};
			return;
		}

		OCA.Talk.Signaling.Base.prototype.joinCall.apply(this, arguments);
	};

	OCA.Talk.Signaling.Standalone.prototype._joinCallSuccess = function(/* token */) {
		// Update room list to fetch modified properties.
		this.internalSyncRooms();
	};

	OCA.Talk.Signaling.Standalone.prototype._leaveCallSuccess = function(/* token */) {
		// Update room list to fetch modified properties.
		this.internalSyncRooms();
	};

	OCA.Talk.Signaling.Standalone.prototype.joinResponseReceived = function(data, token) {
		console.log("Joined", data, token);
		this.signalingRoomJoined = token;
		if (this.pendingJoinCall && token === this.pendingJoinCall.token) {
			this.joinCall(this.pendingJoinCall.token, this.pendingJoinCall.flags);
			this.pendingJoinCall = null;
		}
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

	OCA.Talk.Signaling.Standalone.prototype._doLeaveRoom = function(token) {
		console.log("Leave room", token);
		this.doSend({
			"type": "room",
			"room": {
				"roomid": ""
			}
		}, function(data) {
			console.log("Left", data);
			this.signalingRoomJoined = null;
			// Any users we previously had in the room also "left" for us.
			var leftUsers = _.keys(this.joinedUsers);
			if (leftUsers.length) {
				this._trigger("usersLeft", [leftUsers]);
			}
			this.joinedUsers = {};
		}.bind(this));
	};

	OCA.Talk.Signaling.Standalone.prototype.processEvent = function(data) {
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

	OCA.Talk.Signaling.Standalone.prototype.processRoomEvent = function(data) {
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
					this._trigger('participantListChanged');
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
					this._trigger('participantListChanged');
				}
				break;
			case "message":
				this.processRoomMessageEvent(data.event.message.data);
				break;
			default:
				console.log("Unknown room event", data);
				break;
		}
	};

	OCA.Talk.Signaling.Standalone.prototype.processRoomMessageEvent = function(data) {
		switch (data.type) {
			case "chat":
				this._receiveChatMessages();
				break;
			default:
				console.log("Unknown room message event", data);
		}
	};

	OCA.Talk.Signaling.Standalone.prototype.setRoomCollection = function(/* rooms */) {
		OCA.Talk.Signaling.Base.prototype.setRoomCollection.apply(this, arguments);
		// Retrieve initial list of rooms for this user.
		return this.internalSyncRooms();
	};

	OCA.Talk.Signaling.Standalone.prototype.syncRooms = function() {
		if (this._pendingSyncRooms) {
			// A sync request is already in progress, don't start another one.
			return this._pendingSyncRooms;
		}

		// Never manually sync rooms, will be done based on notifications
		// from the signaling server.
		var defer = $.Deferred();
		defer.resolve(this.rooms);
		return defer;
	};

	OCA.Talk.Signaling.Standalone.prototype.internalSyncRooms = function() {
		if (this._pendingSyncRooms) {
			// A sync request is already in progress, don't start another one.
			return this._pendingSyncRooms;
		}

		this._pendingSyncRooms = $.Deferred();
		this._waitTimeUntilSyncRetry = 1;
		this._internalSyncRoomsWithRetry();
		return this._pendingSyncRooms;
	};

	/**
	 * Forces the synchronization of rooms.
	 *
	 * The rooms are synchronized immediately, even if the synchronization
	 * failed before and there is a scheduled retry for later (which is
	 * cancelled).
	 *
	 * Use sparingly, only when it is very likely that synchronizing again will
	 * succeed despite having failed earlier (for example, after the Internet
	 * connection has been restored).
	 */
	OCA.Talk.Signaling.Standalone.prototype._forceInternalSyncRooms = function() {
		if (!this._pendingSyncRooms) {
			return this.internalSyncRooms();
		}

		if (this._delayedInternalSyncRoomsWithRetry) {
			clearTimeout(this._delayedInternalSyncRoomsWithRetry);
			this._waitTimeUntilSyncRetry = 1;
			this._internalSyncRoomsWithRetry();
		} else {
			// A synchronization is being performed right now, so there is
			// nothing to do except for waiting.
		}

		return this._pendingSyncRooms;
	};

	OCA.Talk.Signaling.Standalone.prototype._internalSyncRoomsWithRetry = function() {
		this._delayedInternalSyncRoomsWithRetry = null;

		OCA.Talk.Signaling.Base.prototype.syncRooms.apply(this, arguments).then(function(rooms) {
			// Remove _pendingSyncRooms before resolving it to make possible to
			// sync again from handlers if needed.
			var pendingSyncRooms = this._pendingSyncRooms;
			this._pendingSyncRooms = null;
			this.rooms = rooms;
			pendingSyncRooms.resolve(rooms);
		}.bind(this)).fail(function() {
			this._delayedInternalSyncRoomsWithRetry = setTimeout(this._internalSyncRoomsWithRetry.bind(this), this._waitTimeUntilSyncRetry * 1000);

			// Increase the wait time until retry to at most 8 seconds.
			if (this._waitTimeUntilSyncRetry < 8) {
				this._waitTimeUntilSyncRetry *= 2;
			}
		}.bind(this));
	};

	OCA.Talk.Signaling.Standalone.prototype.processRoomListEvent = function(data) {
		console.log("Room list event", data);
		this.internalSyncRooms();
	};

	OCA.Talk.Signaling.Standalone.prototype.processRoomParticipantsEvent = function(data) {
		switch (data.event.type) {
			case "update":
				this._trigger("usersChanged", [data.event.update.users || []]);
				this._trigger('participantListChanged');
				this.internalSyncRooms();
				break;
			default:
				console.log("Unknown room participant event", data);
				break;
		}
	};

	OCA.Talk.Signaling.Standalone.prototype._getChatRequestData = function(/* lastKnownMessageId */) {
		var data = OCA.Talk.Signaling.Base.prototype._getChatRequestData.apply(this, arguments);
		// Don't keep connection open and wait for more messages, will be done
		// through another event on the WebSocket.
		data.timeout = 0;
		return data;
	};

	OCA.Talk.Signaling.Standalone.prototype._retryChatLoadingOnError = function() {
		// We don't regularly poll for changes, so need to always retry loading
		// of chat messages in case of errors.
		return true;
	};

	OCA.Talk.Signaling.Standalone.prototype.startReceiveMessages = function() {
		OCA.Talk.Signaling.Base.prototype.startReceiveMessages.apply(this, arguments);
		// We will be notified when to load new messages.
		this.receiveMessagesAgain = false;
	};

	OCA.Talk.Signaling.Standalone.prototype.requestOffer = function(sessionid, roomType) {
		if (!this.hasFeature("mcu")) {
			console.warn("Can't request an offer without a MCU.");
			return;
		}

		if (typeof(sessionid) !== "string") {
			// Got a user object.
			sessionid = sessionid.sessionId || sessionid.sessionid;
		}
		console.log("Request offer from", sessionid);
		this.doSend({
			"type": "message",
			"message": {
				"recipient": {
					"type": "session",
					"sessionid": sessionid
				},
				"data": {
					"type": "requestoffer",
					"roomType": roomType
				}
			}
		});
	};

	OCA.Talk.Signaling.Standalone.prototype.sendOffer = function(sessionid, roomType) {
		// TODO(jojo): This should go away and "requestOffer" should be used
		// instead by peers that want an offer by the MCU. See the calling
		// location for further details.
		if (!this.hasFeature("mcu")) {
			console.warn("Can't send an offer without a MCU.");
			return;
		}

		if (typeof(sessionid) !== "string") {
			// Got a user object.
			sessionid = sessionid.sessionId || sessionid.sessionid;
		}
		console.log("Send offer to", sessionid);
		this.doSend({
			"type": "message",
			"message": {
				"recipient": {
					"type": "session",
					"sessionid": sessionid
				},
				"data": {
					"type": "sendoffer",
					"roomType": roomType
				}
			}
		});
	};

})(OCA, OC, $);

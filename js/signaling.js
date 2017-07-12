(function(OCA, OC) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	function SignalingBase() {
		this.sessionId = '';
		this.currentCallId = null;
		this.currentCallToken = null;
		this.handlers = {};
	}

	SignalingBase.prototype.on = function(ev, handler) {
		if (!this.handlers.hasOwnProperty(ev)) {
			this.handlers[ev] = [handler];
		} else {
			this.handlers[ev].push(handler);
		}
	};

	SignalingBase.prototype.emit = function(/*ev, data*/) {
		// Override in subclasses.
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
		this.currentCallId = null;
		this.currentCallToken = null;
	};

	SignalingBase.prototype.emit = function(ev, data) {
		switch (ev) {
			case 'join':
				var callback = arguments[2];
				var token = data;
				this.joinCall(token, callback);
				break;
			case 'leave':
				this.leaveCall();
				break;
			case 'message':
				this.sendCallMessage(data);
				break;
		}
	};

	SignalingBase.prototype.leaveAllRooms = function() {
		// Override if necessary.
	};

	// Connection to the internal signaling server provided by the app.
	function InternalSignaling() {
		SignalingBase.prototype.constructor.apply(this, arguments);
		this.spreedArrayConnection = [];
		this._openEventSource();

		this.pingFails = 0;
		this.pingInterval = null;

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

			case 'stunservers':
			case 'turnservers':
				// Values are not pushed by the server but have to be explicitly
				// requested.
				this._sendMessageWithCallback(ev);
				break;
		}
	};

	InternalSignaling.prototype._sendMessageWithCallback = function(ev) {
		var message = [{
			ev: ev
		}];
		$.post(OC.generateUrl('/apps/spreed/signalling'), {
			messages: JSON.stringify(message)
		}, function(data) {
			this._trigger(ev, [data]);
		}.bind(this));
	};

	InternalSignaling.prototype.joinCall = function(token, callback) {
		// The client is joining a new room, in this case we need
		// to do the following:
		//
		// 1. Get a list of connected clients to the room
		// 2. Return the list of connected clients
		// 3. Connect to the room with the clients as list here
		//
		// The clients will then use the message command to exchange
		// their signalling information.
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			type: 'POST',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			success: function (result) {
				console.log("Joined", result);
				this.sessionId = result.ocs.data.sessionId;
				this.currentCallId = result.ocs.data.id;
				this.currentCallToken = token;
				this._startPingRoom();
				this._getRoomPeers(token).then(function(result) {
					var roomDescription = {
						'clients': {}
					};

					result.ocs.data.forEach(function(element) {
						if (this.sessionId !== element['sessionId']) {
							roomDescription['clients'][element['sessionId']] = {
								'video': true
							};
						}
					}.bind(this));
					callback('', roomDescription);
				}.bind(this));
			}.bind(this)
		});
	};

	InternalSignaling.prototype.leaveCall = function() {
		this._stopPingRoom();
		if (this.currentCallId) {
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.currentCallId + '/participants/self',
				type: 'DELETE'
			});
			this.currentCallId = null;
		}
		this.currentCallToken = null;
	};

	InternalSignaling.prototype.leaveAllRooms = function() {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			method: 'DELETE',
			async: false,
			success: function() {
				this.currentCallId = null;
				this.currentCallToken = null;
			}.bind(this)
		});
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

	InternalSignaling.prototype.addParticipantToCall = function(token, participant) {
		var defer = $.Deferred();
		$.post(
			OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants',
			{
				newParticipant: participant
			}
		).done(function() {
			this.syncRooms();
			defer.resolve();
		}.bind(this));
		return defer;
	};

	InternalSignaling.prototype.setRoomCollection = function(rooms) {
		this.roomCollection = rooms;
		this._pollForRoomChanges();
		return this.syncRooms();
	};

	InternalSignaling.prototype.syncRooms = function() {
		var defer = $.Deferred();
		if (this.roomCollection && oc_current_user) {
			this.roomCollection.fetch({
				success: function(data) {
					defer.resolve(data);
				}
			});
		} else {
			defer.resolve([]);
		}
		return defer;
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
	InternalSignaling.prototype._getRoomPeers = function(token) {
		return $.ajax({
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token
		});
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._openEventSource = function() {
		// Connect to the messages endpoint and pull for new messages
		this.source = new OC.EventSource(OC.generateUrl('/apps/spreed/messages'));

		this.source.listen('usersInRoom', function(users) {
			this._trigger('usersInRoom', [users]);
		}.bind(this));
		this.source.listen('message', function(message) {
			if (typeof(message) === 'string') {
				message = JSON.parse(message);
			}
			this._trigger('message', [message]);
		}.bind(this));
		this.source.listen('__internal__', function(data) {
			if (data === 'close') {
				console.log('signaling connection closed - will reopen');
				setTimeout(function() {
					this._openEventSource();
				}.bind(this), 0);
			}
		}.bind(this));
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype.sendPendingMessages = function() {
		if (!this.spreedArrayConnection.length) {
			return;
		}

		$.post(OC.generateUrl('/apps/spreed/signalling'), {
			messages: JSON.stringify(this.spreedArrayConnection)
		});
		this.spreedArrayConnection = [];
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._startPingRoom = function() {
		this._pingRoom();
		// Send a ping to the server all 5 seconds to ensure that the connection
		// is still alive.
		this.pingInterval = window.setInterval(function() {
			this._pingRoom();
		}.bind(this), 5000);
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._stopPingRoom = function() {
		if (this.pingInterval) {
			window.clearInterval(this.pingInterval);
			this.pingInterval = null;
		}
	};

	/**
	 * @private
	 */
	InternalSignaling.prototype._pingRoom = function() {
		if (!this.currentCallToken) {
			return;
		}

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + this.currentCallToken + '/ping',
			method: 'POST'
		).done(function() {
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

	OCA.SpreedMe.createSignalingConnection = function() {
		// TODO(fancycode): Create different type of signaling connection
		// depending on configuration.
		return new InternalSignaling();
	};

})(OCA, OC);

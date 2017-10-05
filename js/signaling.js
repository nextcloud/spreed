(function(OCA, OC) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	function SignalingBase() {
		this.sessionId = '';
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
				this.leaveCurrentCall();
				break;
			case 'message':
				this.sendCallMessage(data);
				break;
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

	SignalingBase.prototype.syncRooms = function() {
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

	// Connection to the internal signaling server provided by the app.
	function InternalSignaling() {
		SignalingBase.prototype.constructor.apply(this, arguments);
		this.spreedArrayConnection = [];

		this.pingFails = 0;
		this.pingInterval = null;
		this.isSendingMessages = false;

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

	InternalSignaling.prototype.joinCall = function(token, callback, password) {
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			type: 'POST',
			beforeSend: function (request) {
				request.setRequestHeader('Accept', 'application/json');
			},
			data: {
				password: password
			},
			success: function (result) {
				console.log("Joined", result);
				this.sessionId = result.ocs.data.sessionId;
				this.currentCallToken = token;
				this._startPingCall();
				this._startPullingMessages();
				// We send an empty call description to simplewebrtc since
				// usersChanged (webrtc.js) will create/remove peer connections
				// with call participants
				var callDescription = {'clients': {}};
				callback('', callDescription);
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
								this.joinCall(token, callback, password);
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

	InternalSignaling.prototype.leaveCall = function(token) {
		if (token === this.currentCallToken) {
			this._stopPingCall();
			this._closeEventSource();
		}
		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + token,
			method: 'DELETE',
			async: false
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

	InternalSignaling.prototype.setRoomCollection = function(/*rooms*/) {
		this._pollForRoomChanges();
		return SignalingBase.prototype.setRoomCollection.apply(this, arguments);
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
		// Connect to the messages endpoint and pull for new messages
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
			error: function (/*jqXHR, textStatus, errorThrown*/) {
				//Retry to pull messages after 5 seconds
				window.setTimeout(function() {
					this._startPullingMessages();
				}.bind(this), 5000);
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
		if (!this.currentCallToken) {
			return;
		}

		$.ajax({
			url: OC.linkToOCS('apps/spreed/api/v1/call', 2) + this.currentCallToken + '/ping',
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

	OCA.SpreedMe.createSignalingConnection = function() {
		// TODO(fancycode): Create different type of signaling connection
		// depending on configuration.
		return new InternalSignaling();
	};

})(OCA, OC);

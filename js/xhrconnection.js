var spreedArrayConnection = [];
var sessionId = '';

(function(OCA) {

	OCA.SpreedMe = OCA.SpreedMe || {};

	OCA.SpreedMe.XhrConnection = {
		on: function(ev, fn) {
			var self = this;

			if (ev !== 'message') {
				var message = [{ev: ev}];
				$.post(OC.generateUrl('/apps/spreed/signalling'), {messages: JSON.stringify(message)}, function (data) {
					self.emit(fn, data);
				});
			}
		},

		emit: function(fn, data) {
			if (typeof fn === 'function') {
				fn(data);
			} else {
				switch (fn) {
					case 'join':
						// The client is joining a new room, in this case we need
						// to do the following:
						//
						// 1. Get a list of connected clients to the room
						// 2. Return the list of connected clients
						// 3. Connect to the room with the clients as list here
						//
						// The clients will then use the message command to exchange
						// their signalling information.
						var callback = arguments[2];
						$.post(
							OC.generateUrl('/apps/spreed/api/room/{token}/join', {token: data}),
							function(sessionData) {
								sessionId = sessionData.sessionId;
								OCA.SpreedMe.Rooms.peers(data).then(function(result) {
									var roomDescription = {
										'clients': {}
									};

									result.forEach(function(element) {
										if(sessionId !== element['sessionId']) {
											roomDescription['clients'][element['sessionId']] = {
												'video': true
											};
										}
									});
									callback('', roomDescription);
								});

							}
						);
						break;
					case 'message':
						if(data.type === 'answer') {
							console.log("ANSWER", data);
						} else if(data.type === 'offer') {
							console.log("OFFER", data);
						}
						spreedArrayConnection.push({
							ev: fn,
							fn: JSON.stringify(data),
							sessionId: sessionId
						});
						break;
				}
			}
		},
		getSessionid: function() {
			return sessionId;
		},
		disconnect: function() {
			console.log('disconnect');
		}
	};
})(OCA);

window.setInterval(function(){
	if(spreedArrayConnection.length > 0) {
		$.post(OC.generateUrl('/apps/spreed/signalling'), {messages: JSON.stringify(spreedArrayConnection)});
		spreedArrayConnection = [];
	}
}, 500);

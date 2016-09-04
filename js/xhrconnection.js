(function(OCA) {

	OCA.SpreedMe = OCA.SpreedMe || {};

	OCA.SpreedMe.XhrConnection = {
		on: function(ev, fn) {
			var self = this;

			$.post(OC.generateUrl('/apps/spreedme/signalling'), {ev: ev}, function(data) {
				self.emit(fn, data);
			});
		},

		emit: function(fn, data) {
			var self = this;

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
							OC.generateUrl('/apps/spreedme/api/room/{roomId}/join', {roomId: data}),
							function() {

								OCA.SpreedMe.Rooms.peers(data).then(function(result) {
									var roomDescription = {
										'clients': {}
									};

									result.forEach(function(element) {
										if(self.getSessionid() !== element['userId']) {
											roomDescription['clients'][element['userId']] = {
												'type': 'video'
											};
										}
									});
									callback('', roomDescription);
								});

							}
						);
						break;
					case 'message':
						$.post(OC.generateUrl('/apps/spreedme/signalling'), {ev: fn, fn: JSON.stringify(data)});
						break;
				}
			}
		},
		getSessionid: function() {
			return $('#app').data('sessionid');
		},
		disconnect: function() {
			console.log('disconnect');
		}
	}
})(OCA);
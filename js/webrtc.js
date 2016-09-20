webrtc = new SimpleWebRTC({
	localVideoEl: 'localVideo',
	remoteVideosEl: '',
	autoRequestMedia: true,
	debug: false,
	autoAdjustMic: false,
	detectSpeakingEvents: true,
	connection: OCA.SpreedMe.XhrConnection,
	supportDataChannel: true
});

function openEventSource() {

// Connect to the messages endpoint and pull for new messages
	var messageEventSource = new OC.EventSource(OC.generateUrl('/apps/spreed/messages'));
	var previousUsersInRoom = [];
	Array.prototype.diff = function(a) {
		return this.filter(function(i) {return a.indexOf(i) < 0;});
	};
	messageEventSource.listen('usersInRoom', function(users) {
		var currentUsersInRoom = [];
		users.forEach(function(user) {
			currentUsersInRoom.push(user['userId']);
		});
		$('#app-content').attr('class','');
		$('#app-content').addClass('participants-'+currentUsersInRoom.length);

		var disconnectedUsers = previousUsersInRoom.diff(currentUsersInRoom);
		disconnectedUsers.forEach(function(user) {
			webrtc.removePeers(user);
		});
		previousUsersInRoom = currentUsersInRoom;
	});

	messageEventSource.listen('message', function(message) {
		message = JSON.parse(message);
		var peers = self.webrtc.getPeers(message.from, message.roomType);
		var peer;

		if (message.type === 'offer') {
			if (peers.length) {
				peers.forEach(function (p) {
					if (p.sid == message.sid) peer = p;
				});
			}
			if (!peer) {
				peer = self.webrtc.createPeer({
					id: message.from,
					sid: message.sid,
					type: message.roomType,
					enableDataChannels: false,
					sharemyscreen: message.roomType === 'screen' && !message.broadcaster,
					broadcaster: message.roomType === 'screen' && !message.broadcaster ? self.connection.getSessionid() : null
				});
				webrtc.emit('createdPeer', peer);
			}
			peer.handleMessage(message);
		} else if (peers.length) {
			peers.forEach(function (peer) {
				if (message.sid) {
					if (peer.sid === message.sid) {
						peer.handleMessage(message);
					}
				} else {
					peer.handleMessage(message);
				}
			});
		}
	});
}

webrtc.on('joinedRoom', function() {
	$('#app-content').removeClass('icon-loading');
	$('.videoView').removeClass('hidden');
	openEventSource();
	OCA.SpreedMe.Rooms.list();
});

webrtc.on('videoAdded', function (video, peer) {
	console.log('video added', peer);
	var remotes = document.getElementById('remotes');
	if (remotes) {
		// Indicator for username
		var userIndicator = document.createElement('div');
		userIndicator.className = 'nameIndicator';
		userIndicator.textContent = peer.id;

		// Generic container
		var container = document.createElement('div');
		container.className = 'videoContainer';
		container.id = 'container_' + webrtc.getDomId(peer);
		container.appendChild(video);
		container.appendChild(userIndicator);
		video.oncontextmenu = function () { return false; };
		remotes.appendChild(container);
	}
});

// a peer was removed
webrtc.on('videoRemoved', function (video, peer) {
	var remotes = document.getElementById('remotes');
	var el = document.getElementById(peer ? 'container_' + webrtc.getDomId(peer) : 'localScreenContainer');
	if (remotes && el) {
		remotes.removeChild(el);
	}
});

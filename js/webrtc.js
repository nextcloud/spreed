// TODO(fancycode): Should load through AMD if possible.
/* global SimpleWebRTC, OC, OCA: false */

var webrtc;
var spreedMappingTable = [];

(function(OCA, OC) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	/**
	 * @private
	 */
	function openEventSource() {
		// Connect to the messages endpoint and pull for new messages
		var messageEventSource = new OC.EventSource(OC.generateUrl('/apps/spreed/messages'));
		var previousUsersInRoom = [];
		Array.prototype.diff = function(a) {
			return this.filter(function(i) {
				return a.indexOf(i) < 0;
			});
		};
		messageEventSource.listen('usersInRoom', function(users) {
			var currentUsersInRoom = [];
			users.forEach(function(user) {
				currentUsersInRoom.push(user['sessionId']);
				spreedMappingTable[user['sessionId']] = user['userId'];
			});

			var currentUsersNo = currentUsersInRoom.length;
			if(currentUsersNo === 0) {
				currentUsersNo = 1;
			}

			var appContentElement = $('#app-content'),
				participantsClass = 'participants-' + currentUsersNo;
			if (!appContentElement.hasClass(participantsClass)) {
				appContentElement.attr('class', '').addClass(participantsClass);
			}

			var disconnectedUsers = previousUsersInRoom.diff(currentUsersInRoom);
			disconnectedUsers.forEach(function(user) {
				console.log('XXX Remove peer', user);
				OCA.SpreedMe.webrtc.removePeers(user);
				OCA.SpreedMe.speakers.remove(user, true);
			});
			previousUsersInRoom = currentUsersInRoom;
		});

		messageEventSource.listen('message', function(message) {
			message = JSON.parse(message);
			var peers = self.webrtc.getPeers(message.from, message.roomType);
			var peer;

			if (message.type === 'offer') {
				if (peers.length) {
					peers.forEach(function(p) {
						if (p.sid === message.sid) {
							peer = p;
						}
					});
				}
				if (!peer) {
					peer = self.webrtc.createPeer({
						id: message.from,
						sid: message.sid,
						type: message.roomType,
						enableDataChannels: true,
						sharemyscreen: message.roomType === 'screen' && !message.broadcaster,
						broadcaster: message.roomType === 'screen' && !message.broadcaster ? self.connection.getSessionid() : null
					});
					OCA.SpreedMe.webrtc.emit('createdPeer', peer);
				}
				peer.handleMessage(message);
			} else if (peers.length) {
				peers.forEach(function(peer) {
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
		messageEventSource.listen('__internal__', function(data) {
			if (data === 'close') {
				console.log('signaling connection closed - will reopen');
				setTimeout(openEventSource, 0);
			}
		});
	}

	function initWebRTC() {
		'use strict';
		openEventSource();

		webrtc = new SimpleWebRTC({
			localVideoEl: 'localVideo',
			remoteVideosEl: '',
			autoRequestMedia: true,
			debug: false,
			media: {
				audio: true,
				video: {
					width: { max: 1280 },
					height: { max: 720 }
				}
			},
			autoAdjustMic: false,
			detectSpeakingEvents: true,
			connection: OCA.SpreedMe.XhrConnection,
			enableDataChannels: true,
			nick: OC.getCurrentUser()['displayName']
	});
		OCA.SpreedMe.webrtc = webrtc;

		var $appContent = $('#app-content');
		var spreedListofSpeakers = {};
		var latestSpeakerId = null;
		OCA.SpreedMe.speakers = {
			showStatus: function() {
				var data = [];
				for (var currentId in spreedListofSpeakers) {
					// skip loop if the property is from prototype
					if (!spreedListofSpeakers.hasOwnProperty(currentId)) {
						continue;
					}

					var currentTime = spreedListofSpeakers[currentId];
					var id = currentId.replace('\\', '');
					data.push([spreedMappingTable[id], id, currentTime]);
				}
				console.log('spreedListofSpeakers');
				console.table(data);
				console.log('spreedMappingTable');
				console.table(spreedMappingTable);
				console.log('latestSpeakerId');
				console.log(latestSpeakerId);
			},
			getContainerId: function(id) {
				var sanitizedId = id.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
				return '#container_' + sanitizedId + '_type_incoming';
			},
			switchVideoToId: function(id) {
				var newContainer = $(OCA.SpreedMe.speakers.getContainerId(id));
				if(newContainer.find('video').length === 0) {
					console.warn('promote: no video found for ID', id);
					return;
				}

				if(latestSpeakerId === id) {
					console.log('promote: no need to repromote same speaker');
					return
				}

				if (latestSpeakerId !== null) {
					console.log('promote: unpromote speaker "' + spreedMappingTable[latestSpeakerId] + '"');
					// move old video to new location
					var oldContainer = $(OCA.SpreedMe.speakers.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
				}

				console.log('promote: promote speaker "' + spreedMappingTable[id] + '"');
				$('.videoContainer-dummy').remove();
				// add new user to it
				newContainer.addClass('promoted');
				newContainer.after(
					$('<div>')
						.addClass('videoContainer videoContainer-dummy')
						.append(newContainer.find('.nameIndicator').clone())
						.append(newContainer.find('.muteIndicator').clone())
						.append(newContainer.find('.speakingIndicator').clone())
					);

				latestSpeakerId = id;
			},
			add: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var sanitizedId = OCA.SpreedMe.speakers.getContainerId(id);
				spreedListofSpeakers[sanitizedId] = (new Date()).getTime();

				// set speaking class
				$(sanitizedId).addClass('speaking');

				if (latestSpeakerId === id) {
					console.log('promoting: latest speaker "' + spreedMappingTable[id] + '" is already promoted');
					return;
				}

				console.log('promoting: change promoted speaker to "' + spreedMappingTable[id] + '" after speaking');
				OCA.SpreedMe.speakers.switchVideoToId(id);
			},
			remove: function(id, enforce) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var sanitizedId = OCA.SpreedMe.speakers.getContainerId(id);
				spreedListofSpeakers[sanitizedId] = -1;

				// remove speaking class
				$(sanitizedId).removeClass('speaking');

				if (latestSpeakerId !== id) {
					console.log('promoting: stopped speaker "' + spreedMappingTable[id] + '" is not promoted');
					return;
				}

				console.log('promoting: try to find better promoted speaker for "' + spreedMappingTable[id] + '"');

				var mostRecentTime = 0,
					mostRecentId = null;
				for (var currentId in spreedListofSpeakers) {
					// skip loop if the property is from prototype
					if (!spreedListofSpeakers.hasOwnProperty(currentId)) {
						continue;
					}

					// skip non-string ids
					if (!(typeof currentId === 'string' || currentId instanceof String))  continue;

					var currentTime = spreedListofSpeakers[currentId];
					if (currentTime > mostRecentTime && $(OCA.SpreedMe.speakers.getContainerId(currentId.replace('\\', ''))).length > 0) {
						mostRecentTime = currentTime;
						mostRecentId = currentId;
					}
				}

				if (mostRecentId !== null) {
					console.log('promoting: change promoted speaker from "' + spreedMappingTable[id] + '" to "' + spreedMappingTable[mostRecentId] + '" after speakingStopped');
					OCA.SpreedMe.speakers.switchVideoToId(mostRecentId.replace('\\', ''));
				} else if (enforce === true) {
					console.log('promoting: no recent speaker to promote - but enforced removal');
					// if there is no mostRecentId is available there is no user left in call
					// remove the remaining dummy container then too
					$('.videoContainer-dummy').remove();
				} else {
					console.log('promoting: no recent speaker to promote - keep "' + spreedMappingTable[id] + '"');
				}
			}
		};

		OCA.SpreedMe.webrtc.on('createdPeer', function (peer) {
			peer.pc.on('PeerConnectionTrace', function (event) {
				console.log('trace', event);
			});
		});

		OCA.SpreedMe.webrtc.on('localMediaError', function(error) {
			console.log('Access to microphone & camera failed', error);
			var message, messageAdditional;
			if (error.name === "NotAllowedError") {
				if (error.message && error.message.indexOf("Only secure origins") !== -1) {
					message = t('spreed', 'Access to microphone & camera is only possible with HTTPS');
					messageAdditional = t('spreed', 'Please adjust your configuration');
				} else {
					message = t('spreed', 'Access to microphone & camera was denied');
					$('#emptycontent p').hide();
				}
			} else {
				message = t('spreed', 'Error while accessing microphone & camera');
				messageAdditional = error.message || error.name;
			}
			$('#emptycontent .icon-video').removeClass('icon-video').addClass('icon-video-off');
			$('#emptycontent h2').text(message);
			$('#emptycontent p').text(messageAdditional);
		});

		OCA.SpreedMe.webrtc.on('WebrtcError', function(error) {
			console.log('Access to WebRTC failed', error);
			var message, messageAdditional;

			message = t('spreed', 'WebRTC doesn’t seem to work in your browser :-/');
			messageAdditional = t('spreed', 'Please use a different browser like Firefox or Chrome');

			$('#emptycontent h2').text(message);
			$('#emptycontent p').text(messageAdditional);
		});

		OCA.SpreedMe.webrtc.on('joinedRoom', function(name) {
			$('#app-content').removeClass('icon-loading');
			$('.videoView').removeClass('hidden');
			OCA.SpreedMe.app.syncAndSetActiveRoom(name);
		});

		OCA.SpreedMe.webrtc.on('channelMessage', function (peer, label, data) {
			if(label === 'speaking') {
				OCA.SpreedMe.speakers.add(peer.id);
			} else if(label === 'stoppedSpeaking') {
				OCA.SpreedMe.speakers.remove(peer.id);
			} else if(label === 'audioOn') {
				OCA.SpreedMe.webrtc.emit('unmute', {id: peer.id, name:'audio'});
			} else if(label === 'audioOff') {
				OCA.SpreedMe.webrtc.emit('mute', {id: peer.id, name:'audio'});
			} else if(label === 'videoOn') {
				OCA.SpreedMe.webrtc.emit('unmute', {id: peer.id, name:'video'});
			} else if(label === 'videoOff') {
				OCA.SpreedMe.webrtc.emit('mute', {id: peer.id, name:'video'});
			}
		});

		OCA.SpreedMe.webrtc.on('videoAdded', function(video, peer) {
			console.log('video added', peer);
			var remotes = document.getElementById('videos');
			if (remotes) {
				// Indicator for username
				var userIndicator = document.createElement('div');
				userIndicator.className = 'nameIndicator';
				userIndicator.textContent = peer.nick;

				// Avatar for username
				var avatar = document.createElement('div');
				avatar.className = 'avatar';

				var avatarContainer = document.createElement('div');
				avatarContainer.className = 'avatar-container hidden';
				avatarContainer.appendChild(avatar);

				// Mute indicator
				var muteIndicator = document.createElement('div');
				muteIndicator.className = 'muteIndicator icon-audio-off-white hidden';
				muteIndicator.textContent = '';

				// Generic container
				var container = document.createElement('div');
				container.className = 'videoContainer';
				container.id = 'container_' + OCA.SpreedMe.webrtc.getDomId(peer);
				container.appendChild(video);
				container.appendChild(avatarContainer);
				container.appendChild(userIndicator);
				container.appendChild(muteIndicator);
				video.oncontextmenu = function() {
					return false;
				};

				// show the ice connection state
				if (peer && peer.pc) {
					peer.pc.on('iceConnectionStateChange', function () {
						switch (peer.pc.iceConnectionState) {
							case 'checking':
								console.log('Connecting to peer...');
								break;
							case 'connected':
							case 'completed': // on caller side
								console.log('Connection established.');
								// Send the current information about the video and microphone state
								if (!OCA.SpreedMe.webrtc.webrtc.isVideoEnabled()) {
									OCA.SpreedMe.webrtc.emit('videoOff');
								} else {
									OCA.SpreedMe.webrtc.emit('videoOn');
								}
								if (!OCA.SpreedMe.webrtc.webrtc.isAudioEnabled()) {
									OCA.SpreedMe.webrtc.emit('audioOff');
								} else {
									OCA.SpreedMe.webrtc.emit('audioOn');
								}
								break;
							case 'disconnected':
								// If the peer is still disconnected after 5 seconds
								// we close the video connection.
								setTimeout(function() {
									if(peer.pc.iceConnectionState === 'disconnected') {
										OCA.SpreedMe.webrtc.removePeers(peer.id);
									}
								}, 5000);
								console.log('Disconnected.');
								break;
							case 'failed':
								console.log('Connection failed.');
								break;
							case 'closed':
								console.log('Connection closed.');
								break;
						}
					});
				}

				$(container).prependTo($('#videos'));
			}

			var otherSpeakerPromoted = false;
			for (var key in spreedListofSpeakers) {
				if (spreedListofSpeakers.hasOwnProperty(key) && spreedListofSpeakers[key] > 0) {
					otherSpeakerPromoted = true;
					break;
				}
			}
			if (!otherSpeakerPromoted) {
				OCA.SpreedMe.speakers.add(peer.id);
			}
		});

		OCA.SpreedMe.webrtc.on('speaking', function(){
			console.log('local speaking');
			OCA.SpreedMe.webrtc.sendDirectlyToAll('speaking');

			$('#localVideoContainer').addClass('speaking');
		});
		OCA.SpreedMe.webrtc.on('stoppedSpeaking', function(){
			console.log('local stoppedSpeaking');
			OCA.SpreedMe.webrtc.sendDirectlyToAll('stoppedSpeaking');

			$('#localVideoContainer').removeClass('speaking');
		});

		// a peer was removed
		OCA.SpreedMe.webrtc.on('videoRemoved', function(video, peer) {
			// a removed peer can't speak anymore ;)
			OCA.SpreedMe.speakers.remove(peer, true);

			var remotes = document.getElementById('videos');
			var el = document.getElementById(peer ? 'container_' + OCA.SpreedMe.webrtc.getDomId(peer) : 'localScreenContainer');
			if (remotes && el) {
				remotes.removeChild(el);
			}
		});

		// Send the audio on and off events via data channel
		OCA.SpreedMe.webrtc.on('audioOn', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('audioOn');
		});
		OCA.SpreedMe.webrtc.on('audioOff', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('audioOff');
		});
		OCA.SpreedMe.webrtc.on('videoOn', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('videoOn');
		});
		OCA.SpreedMe.webrtc.on('videoOff', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('videoOff');
		});

		// Peer is muted
		OCA.SpreedMe.webrtc.on('mute', function(data) {
			var el = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'type',
					broadcaster: false
				}));
			var $el = $(el);

			if (data.name === 'video') {
				var avatar = $el.find('.avatar');
				avatar.avatar(spreedMappingTable[data.id], 128);

				var avatarContainer = $el.find('.avatar-container');
				avatarContainer.removeClass('hidden');
				avatarContainer.show();
				$el.find('video').hide();
			} else {
				var muteIndicator = $el.find('.muteIndicator');
				muteIndicator.removeClass('hidden');
				muteIndicator.show();
				$el.removeClass('speaking');
			}
		});

		// Peer is umuted
		OCA.SpreedMe.webrtc.on('unmute', function(data) {
			var el = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'type',
					broadcaster: false
				}));
			var $el = $(el);

			if (data.name === 'video') {
				$el.find('.avatar-container').hide();
				$el.find('video').show();
			} else {
				$el.find('.muteIndicator').hide();
			}
		});

		OCA.SpreedMe.webrtc.on('localStream', function() {
			console.log('localStream event received - let\'s enable video');
			OCA.SpreedMe.app.enableVideo();
		});
	}

	OCA.SpreedMe.initWebRTC = initWebRTC;

})(OCA, OC);

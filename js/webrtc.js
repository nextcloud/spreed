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

		var nick = OC.getCurrentUser()['displayName'];

		//Check if there is some nick saved on local storage for guests
		if (!nick && OCA.SpreedMe.app.guestNick) {
			nick = OCA.SpreedMe.app.guestNick;
		}

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
			audioFallback: true,
			detectSpeakingEvents: true,
			connection: OCA.SpreedMe.XhrConnection,
			enableDataChannels: true,
			nick: nick
		});

		OCA.SpreedMe.webrtc = webrtc;

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
				return '#container_' + sanitizedId + '_video_incoming';
			},
			switchVideoToId: function(id) {
				var newContainer = $(OCA.SpreedMe.speakers.getContainerId(id));
				if(newContainer.find('video').length === 0) {
					console.warn('promote: no video found for ID', id);
					return;
				}

				if(latestSpeakerId === id) {
					console.log('promote: no need to repromote same speaker');
					return;
				}

				if (latestSpeakerId !== null) {
					console.log('promote: unpromote speaker "' + spreedMappingTable[latestSpeakerId] + '"');
					// move old video to new location
					var oldContainer = $(OCA.SpreedMe.speakers.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
				}

				console.log('promote: promote speaker "' + spreedMappingTable[id] + '"');
				newContainer.addClass('promoted');
				OCA.SpreedMe.speakers.updateVideoContainerDummy(id);

				latestSpeakerId = id;
			},
			updateVideoContainerDummy: function(id) {
				var newContainer = $(OCA.SpreedMe.speakers.getContainerId(id));

				$('.videoContainer-dummy').remove();

				newContainer.after(
					$('<div>')
						.addClass('videoContainer videoContainer-dummy')
						.append(newContainer.find('.nameIndicator').clone())
						.append(newContainer.find('.muteIndicator').clone())
						.append(newContainer.find('.speakingIndicator').clone())
					);

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
					if (!(typeof currentId === 'string' || currentId instanceof String)) {
						continue;
					}

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

		OCA.SpreedMe.webrtc.on('localMediaStarted', function (configuration) {
			OCA.SpreedMe.app.initAudioVideoSettings(configuration);
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
			} else if(!OCA.SpreedMe.webrtc.capabilities.support) {
				console.log('WebRTC not supported');

				message = t('spreed', 'WebRTC is not supported in your browser');
				messageAdditional = t('spreed', 'Please use a different browser like Firefox or Chrome');
			} else {
				message = t('spreed', 'Error while accessing microphone & camera');
				messageAdditional = error.message || error.name;
			}

			//Remove previous icon, avatar or link from emptycontent
			var emptyContentIcon = document.getElementById('emptycontent-icon');
			emptyContentIcon.removeAttribute('class');
			emptyContentIcon.innerHTML = '';
			$('#shareRoomInput').addClass('hidden');
			$('#shareRoomClipboardButton').addClass('hidden');

			$('#emptycontent-icon').addClass('icon-video-off');
			$('#emptycontent h2').text(message);
			$('#emptycontent p').text(messageAdditional);
		});

		if(!OCA.SpreedMe.webrtc.capabilities.support) {
			console.log('WebRTC not supported');
			var message, messageAdditional;

			message = t('spreed', 'WebRTC is not supported in your browser :-/');
			messageAdditional = t('spreed', 'Please use a different browser like Firefox or Chrome');

			//Remove previous icon, avatar or link from emptycontent
			var emptyContentIcon = document.getElementById('emptycontent-icon');
			emptyContentIcon.removeAttribute('class');
			emptyContentIcon.innerHTML = '';
			$('#shareRoomInput').addClass('hidden');
			$('#shareRoomClipboardButton').addClass('hidden');

			$('#emptycontent-icon').addClass('icon-video-off');
			$('#emptycontent h2').text(message);
			$('#emptycontent p').text(messageAdditional);
		}

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
			} else if (label === 'nickChanged') {
				OCA.SpreedMe.webrtc.emit('nick', {id: peer.id, name:data.type});
			}
		});

		OCA.SpreedMe.webrtc.on('videoAdded', function(video, peer) {
			console.log('video added', peer);
			var remotes = document.getElementById('videos');
			if (remotes) {
				// Indicator for username
				var userIndicator = document.createElement('div');
				userIndicator.className = 'nameIndicator';
				if (peer.nick) {
					userIndicator.textContent = peer.nick;
				} else {
					userIndicator.textContent = t('spreed', 'Guest');
				}

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
								if (!OC.getCurrentUser()['uid']) {
									// If we are a guest, send updated nick if it is different from the one we initialize SimpleWebRTC (OCA.SpreedMe.app.guestNick)
									var currentGuestNick = localStorage.getItem("nick");
									if (OCA.SpreedMe.app.guestNick !== currentGuestNick) {
										if (!currentGuestNick) {
											currentGuestNick = t('spreed', 'Guest');
										}
										OCA.SpreedMe.webrtc.sendDirectlyToAll('nickChanged', currentGuestNick);
									}
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

		// Peer changed nick
		OCA.SpreedMe.webrtc.on('nick', function(data) {
			var el = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'video',
					broadcaster: false
				}));
			var $el = $(el);

			var nameIndicator = $el.find('.nameIndicator');
			nameIndicator.text(data.name);

			if (latestSpeakerId === data.id) {
				OCA.SpreedMe.speakers.updateVideoContainerDummy(data.id);
			}
		});

		// Peer is muted
		OCA.SpreedMe.webrtc.on('mute', function(data) {
			var el = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'video',
					broadcaster: false
				}));
			var $el = $(el);

			if (data.name === 'video') {
				var avatar = $el.find('.avatar');
				var userId = spreedMappingTable[data.id];

				if (userId.length) {
					avatar.avatar(userId, 128);
				} else {// Guest
					avatar.avatar(null, 128);
				}

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
					type: 'video',
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
			if(!OCA.SpreedMe.app.videoWasEnabledAtLeastOnce) {
				OCA.SpreedMe.app.videoWasEnabledAtLeastOnce = true;
			}

			if (!OCA.SpreedMe.app.videoDisabled) {
				OCA.SpreedMe.app.enableVideo();
			}
		});
	}

	OCA.SpreedMe.initWebRTC = initWebRTC;

})(OCA, OC);

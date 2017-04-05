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
			var peerConnectionsTable = {};

			users.forEach(function(user) {
				currentUsersInRoom.push(user['sessionId']);
				spreedMappingTable[user['sessionId']] = user['userId'];
				var peers = self.webrtc.getPeers(user['sessionId'], 'video');
				var peer;
				if (peers.length) {
					//There should be only one.
					peer = peers[0];
				}
				if (peer && peer.pc) {
					peerConnectionsTable[user['sessionId']] = peer.pc.iceConnectionState;
				}
			});

			OCA.SpreedMe.usersInRoom = currentUsersInRoom;
			OCA.SpreedMe.peerConnectionsTable = peerConnectionsTable;

			if (currentUsersInRoom.length !== (Object.keys(peerConnectionsTable).length + 1)) {
				console.log(currentUsersInRoom);
				console.log(peerConnectionsTable);
			}

			var currentUsersNo = currentUsersInRoom.length;
			if(currentUsersNo === 0) {
				currentUsersNo = 1;
			}

			var appContentElement = $('#app-content'),
				participantsClass = 'participants-' + currentUsersNo;
			if (!appContentElement.hasClass(participantsClass) && !appContentElement.hasClass('screensharing')) {
				appContentElement.attr('class', '').addClass(participantsClass);
			}

			//Send shared screen to new participants
			var webrtc = OCA.SpreedMe.webrtc;
			if (webrtc.getLocalScreen()) {
				var newUsers = currentUsersInRoom.diff(previousUsersInRoom);
				var currentUser = webrtc.connection.getSessionid();
				newUsers.forEach(function(user) {
					if (user !== currentUser) {
						var peer = webrtc.webrtc.createPeer({
								id: user,
								type: 'screen',
								sharemyscreen: true,
								enableDataChannels: false,
								receiveMedia: {
									offerToReceiveAudio: 0,
									offerToReceiveVideo: 0
								},
								broadcaster: currentUser,
						});
						webrtc.emit('createdPeer', peer);
						peer.start();
					}
				});
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
		var spreedListofSharedScreens = {};
		var latestSpeakerId = null;
		var unpromotedSpeakerId = null;
		var latestScreenId = null;
		var screenSharingActive = false;

		window.addEventListener('resize', function() {
			if (screenSharingActive) {
				$('#screens').children('video').each(function() {
					$(this).width('100%');
					$(this).height($('#screens').height());
				});
			}
		});

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
				if (screenSharingActive) {
					return;
				}

				var newContainer = $(OCA.SpreedMe.speakers.getContainerId(id));
				if(newContainer.find('video').length === 0) {
					console.warn('promote: no video found for ID', id);
					return;
				}

				if(latestSpeakerId === id) {
					return;
				}

				if (latestSpeakerId !== null) {
					// move old video to new location
					var oldContainer = $(OCA.SpreedMe.speakers.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
				}

				newContainer.addClass('promoted');
				OCA.SpreedMe.speakers.updateVideoContainerDummy(id);

				latestSpeakerId = id;
			},
			unpromoteLatestSpeaker: function() {
				if (latestSpeakerId) {
					var oldContainer = $(OCA.SpreedMe.speakers.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
					unpromotedSpeakerId = latestSpeakerId;
					latestSpeakerId = null;
					$('.videoContainer-dummy').remove();
				}
			},
			updateVideoContainerDummy: function(id) {
				var newContainer = $(OCA.SpreedMe.speakers.getContainerId(id));

				$('.videoContainer-dummy').remove();

				newContainer.after(
					$('<div>')
						.addClass('videoContainer videoContainer-dummy')
						.append(newContainer.find('.nameIndicator').clone())
						.append(newContainer.find('.mediaIndicator').clone())
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
					return;
				}

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
					return;
				}

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
					OCA.SpreedMe.speakers.switchVideoToId(mostRecentId.replace('\\', ''));
				} else if (enforce === true) {
					// if there is no mostRecentId is available there is no user left in call
					// remove the remaining dummy container then too
					$('.videoContainer-dummy').remove();
				}
			}
		};

		OCA.SpreedMe.sharedScreens = {
			getContainerId: function(id) {
				var currentUser = OCA.SpreedMe.webrtc.connection.getSessionid();
				if (currentUser === id) {
					return '#localScreenContainer';
				} else {
					var sanitizedId = id.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
					return '#container_' + sanitizedId + '_screen_incoming';
				}
			},
			switchScreenToId: function(id) {
				var selectedScreen = $(OCA.SpreedMe.sharedScreens.getContainerId(id));
				if(selectedScreen.find('video').length === 0) {
					console.warn('promote: no screen video found for ID', id);
					return;
				}

				if(latestScreenId === id) {
					return;
				}

				var screenContainerId = null;
				for (var currentId in spreedListofSharedScreens) {
					// skip loop if the property is from prototype
					if (!spreedListofSharedScreens.hasOwnProperty(currentId)) {
						continue;
					}

					// skip non-string ids
					if (!(typeof currentId === 'string' || currentId instanceof String)) {
						continue;
					}

					screenContainerId = OCA.SpreedMe.sharedScreens.getContainerId(currentId);
					if (currentId === id) {
						$(screenContainerId).removeClass('hidden');
					} else {
						$(screenContainerId).addClass('hidden');
					}
				}

				// Add screen visible icon to video container
				$('#videos').find('.screensharingIndicator').removeClass('screen-visible');
				$(OCA.SpreedMe.speakers.getContainerId(id)).find('.screensharingIndicator').addClass('screen-visible');

				latestScreenId = id;
			},
			add: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				spreedListofSharedScreens[id] = (new Date()).getTime();

				var screensharingIndicator = $(OCA.SpreedMe.speakers.getContainerId(id)).find('.screensharingIndicator');
				screensharingIndicator.removeClass('screen-off');
				screensharingIndicator.addClass('screen-on');

				OCA.SpreedMe.sharedScreens.switchScreenToId(id);
			},
			remove: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				delete spreedListofSharedScreens[id];

				var screensharingIndicator = $(OCA.SpreedMe.speakers.getContainerId(id)).find('.screensharingIndicator');
				screensharingIndicator.addClass('screen-off');
				screensharingIndicator.removeClass('screen-on');

				var mostRecentTime = 0,
					mostRecentId = null;
				for (var currentId in spreedListofSharedScreens) {
					// skip loop if the property is from prototype
					if (!spreedListofSharedScreens.hasOwnProperty(currentId)) {
						continue;
					}

					// skip non-string ids
					if (!(typeof currentId === 'string' || currentId instanceof String)) {
						continue;
					}

					var currentTime = spreedListofSharedScreens[currentId];
					if (currentTime > mostRecentTime) {
						mostRecentTime = currentTime;
						mostRecentId = currentId;
					}
				}

				if (mostRecentId !== null) {
					OCA.SpreedMe.sharedScreens.switchScreenToId(mostRecentId);
				}
			}
		};

		OCA.SpreedMe.webrtc.on('createdPeer', function (peer) {
			peer.pc.on('PeerConnectionTrace', function (event) {
				console.log('trace', event);
			});
		});

		OCA.SpreedMe.webrtc.on('localMediaStarted', function (configuration) {
			OCA.SpreedMe.app.startSpreed(configuration);
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
				console.log('Error while accessing microphone & camera: ', error.message || error.name);
			}

			OCA.SpreedMe.app.setEmptyContentMessage(
				'icon-video-off',
				message,
				messageAdditional
			);

			// Hide rooms sidebar
			$('#app-navigation').hide();
		});

		if(!OCA.SpreedMe.webrtc.capabilities.support) {
			console.log('WebRTC not supported');
			var message, messageAdditional;

			message = t('spreed', 'WebRTC is not supported in your browser :-/');
			messageAdditional = t('spreed', 'Please use a different browser like Firefox or Chrome');

			OCA.SpreedMe.app.setEmptyContentMessage(
				'icon-video-off',
				message,
				messageAdditional
			);
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
			if (peer.type === 'screen') {
				OCA.SpreedMe.webrtc.emit('screenAdded', video, peer);
				return;
			}

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
				if (peer.nick) {
					$(avatar).data('guestName', peer.nick);
				}

				var avatarContainer = document.createElement('div');
				avatarContainer.className = 'avatar-container hidden';
				avatarContainer.appendChild(avatar);

				// Media indicators
				var mediaIndicator = document.createElement('div');
				mediaIndicator.className = 'mediaIndicator';

				var muteIndicator = document.createElement('button');
				muteIndicator.className = 'muteIndicator icon-audio-off-white audio-off';
				muteIndicator.disabled = true;

				var screenSharingIndicator = document.createElement('button');
				screenSharingIndicator.className = 'screensharingIndicator icon-screen-white screen-off';
				screenSharingIndicator.setAttribute('data-original-title', 'Show screen');

				screenSharingIndicator.onclick = function() {
					if (!this.classList.contains('screen-visible')) {
						OCA.SpreedMe.sharedScreens.switchScreenToId(peer.id);
					}
					$(this).tooltip('hide');
				};

				$(screenSharingIndicator).tooltip({
					placement: 'top',
					trigger: 'hover'
				});

				// Check if there is a screen from that user already added.
				if (spreedListofSharedScreens.hasOwnProperty(peer.id)) {
					$(screenSharingIndicator).removeClass('screen-off').addClass('screen-on');
				}

				mediaIndicator.appendChild(muteIndicator);
				mediaIndicator.appendChild(screenSharingIndicator);

				// Generic container
				var container = document.createElement('div');
				container.className = 'videoContainer';
				container.id = 'container_' + OCA.SpreedMe.webrtc.getDomId(peer);
				container.appendChild(video);
				container.appendChild(avatarContainer);
				container.appendChild(userIndicator);
				container.appendChild(mediaIndicator);
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
			OCA.SpreedMe.webrtc.sendDirectlyToAll('speaking');
			$('#localVideoContainer').addClass('speaking');
		});
		OCA.SpreedMe.webrtc.on('stoppedSpeaking', function(){
			OCA.SpreedMe.webrtc.sendDirectlyToAll('stoppedSpeaking');
			$('#localVideoContainer').removeClass('speaking');
		});

		// a peer was removed
		OCA.SpreedMe.webrtc.on('videoRemoved', function(video, peer) {
			if (peer) {
				if (peer.type === 'video') {
					// a removed peer can't speak anymore ;)
					OCA.SpreedMe.speakers.remove(peer, true);
				} else if (peer.type === 'screen') {
					OCA.SpreedMe.sharedScreens.remove(peer.id);
				}

				var remotes = document.getElementById(peer.type === 'screen' ? 'screens' : 'videos');
				var el = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId(peer));
				if (remotes && el) {
					remotes.removeChild(el);
				}
			} else if (video.id === 'localScreen') {
				// SimpleWebRTC notifies about stopped screensharing through
				// the generic "videoRemoved" API, but the stream must be
				// handled differently.
				OCA.SpreedMe.webrtc.emit('localScreenStopped');

				var screens = document.getElementById('screens');
				var localScreenContainer = document.getElementById('localScreenContainer');
				if (screens && localScreenContainer) {
					screens.removeChild(localScreenContainer);
				}

				OCA.SpreedMe.sharedScreens.remove(OCA.SpreedMe.webrtc.connection.getSessionid());
			}

			// Check if there are still some screens
			if (!document.getElementById('screens').hasChildNodes()) {
				screenSharingActive = false;
				$('#app-content').removeClass('screensharing');
				if (unpromotedSpeakerId) {
					OCA.SpreedMe.speakers.switchVideoToId(unpromotedSpeakerId);
					unpromotedSpeakerId = null;
				}
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

		OCA.SpreedMe.webrtc.on('screenAdded', function(video, peer) {
			OCA.SpreedMe.speakers.unpromoteLatestSpeaker();

			screenSharingActive = true;
			$('#app-content').attr('class', '').addClass('screensharing');

			var screens = document.getElementById('screens');
			if (screens) {
				// Indicator for username
				var userIndicator = document.createElement('div');
				userIndicator.className = 'nameIndicator';
				if (peer) {
					if (peer.nick) {
						userIndicator.textContent = t('spreed', "{participantName}'s screen", {participantName: peer.nick});
					} else {
						userIndicator.textContent = t('spreed', "Guest's screen");
					}
				} else {
					userIndicator.textContent = t('spreed', 'Your screen');
				}

				// Generic container
				var container = document.createElement('div');
				container.className = 'screenContainer';
				container.id = peer ? 'container_' + OCA.SpreedMe.webrtc.getDomId(peer) : 'localScreenContainer';
				container.appendChild(video);
				container.appendChild(userIndicator);
				video.oncontextmenu = function() {
					return false;
				};

				$(container).prependTo($('#screens'));

				if (peer) {
					OCA.SpreedMe.sharedScreens.add(peer.id);
				} else {
					OCA.SpreedMe.sharedScreens.add(OCA.SpreedMe.webrtc.connection.getSessionid());
				}

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
			}
		});

		// Local screen added.
		OCA.SpreedMe.webrtc.on('localScreenAdded', function(video) {
			OCA.SpreedMe.webrtc.emit('screenAdded', video, null);
		});

		// Peer changed nick
		OCA.SpreedMe.webrtc.on('nick', function(data) {
			// Video
			var video = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'video',
					broadcaster: false
				}));

			var videoNameIndicator = $(video).find('.nameIndicator');
			var videoAvatar = $(video).find('.avatar');

			//Screen
			var screen = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId({
					id: data.id,
					type: 'screen',
					broadcaster: false
				}));

			var screenNameIndicator = $(screen).find('.nameIndicator');

			if (data.name.length === 0) {
				var guestName = t('spreed', 'Guest');
				videoNameIndicator.text(guestName);
				videoAvatar.avatar(null, 128);
				videoAvatar.removeData('guestName');
				screenNameIndicator.text(t('spreed', "{participantName}'s screen", {participantName: guestName}));
			} else {
				videoNameIndicator.text(data.name);
				videoAvatar.imageplaceholder(data.name, undefined, 128);
				videoAvatar.data('guestName', data.name);
				screenNameIndicator.text(t('spreed', "{participantName}'s screen", {participantName: data.name}));
			}

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
				} else if (avatar.data('guestName')) {
					avatar.imageplaceholder(avatar.data('guestName'), undefined, 128);
				} else {
					avatar.avatar(null, 128);
				}

				var avatarContainer = $el.find('.avatar-container');
				avatarContainer.removeClass('hidden');
				avatarContainer.show();
				$el.find('video').hide();
			} else {
				var muteIndicator = $el.find('.muteIndicator');
				muteIndicator.removeClass('audio-on');
				muteIndicator.addClass('audio-off');
				$el.removeClass('speaking');
			}

			if (latestSpeakerId === data.id) {
				OCA.SpreedMe.speakers.updateVideoContainerDummy(data.id);
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
				var muteIndicator = $el.find('.muteIndicator');
				muteIndicator.removeClass('audio-off');
				muteIndicator.addClass('audio-on');
			}

			if (latestSpeakerId === data.id) {
				OCA.SpreedMe.speakers.updateVideoContainerDummy(data.id);
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

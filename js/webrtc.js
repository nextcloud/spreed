// TODO(fancycode): Should load through AMD if possible.
/* global SimpleWebRTC, OC, OCA: false */

var webrtc;
var guestNamesTable = {};
var spreedMappingTable = {};
var spreedPeerConnectionTable = [];

(function(OCA, OC) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var previousUsersInRoom = [];
	var usersInCallMapping = {};

	function updateParticipantsUI(currentUsersNo) {
		'use strict';
		if (!currentUsersNo) {
			currentUsersNo = 1;
		}

		var $appContentElement = $('#app-content'),
			participantsClass = 'participants-' + currentUsersNo,
			hadSidebar = $appContentElement.hasClass('with-app-sidebar');
		if (!$appContentElement.hasClass(participantsClass) && !$appContentElement.hasClass('screensharing')) {
			$appContentElement.attr('class', '').addClass(participantsClass);
			if (currentUsersNo > 1) {
				$appContentElement.addClass('incall');
			} else {
				$appContentElement.removeClass('incall');
			}

			if (hadSidebar) {
				$appContentElement.addClass('with-app-sidebar');
			}
		}
	}

	function usersChanged(newUsers, disconnectedSessionIds) {
		'use strict';
		var currentSessionId = webrtc.connection.getSessionid();

		newUsers.forEach(function(user) {
			if (!user.inCall) {
				return;
			}

			// TODO(fancycode): Adjust property name of internal PHP backend to be all lowercase.
			var sessionId = user.sessionId || user.sessionid;
			if (!sessionId || sessionId === currentSessionId || previousUsersInRoom.indexOf(sessionId) !== -1) {
				return;
			}

			previousUsersInRoom.push(sessionId);

			// TODO(fancycode): Adjust property name of internal PHP backend to be all lowercase.
			spreedMappingTable[sessionId] = user.userId || user.userid;
			var videoContainer = $(OCA.SpreedMe.videos.getContainerId(sessionId));
			if (videoContainer.length === 0) {
				OCA.SpreedMe.videos.add(sessionId);
			}

			var peer;
			// To avoid overloading the user joining a room (who previously called
			// all the other participants), we decide who calls who by comparing
			// the session ids of the users: "larger" ids call "smaller" ones.
			if (sessionId < currentSessionId && !webrtc.webrtc.getPeers(sessionId, 'video').length) {
				console.log("Starting call with", user);
				peer = webrtc.webrtc.createPeer({
					id: sessionId,
					type: "video",
					enableDataChannels: true,
					receiveMedia: {
						offerToReceiveAudio: 1,
						offerToReceiveVideo: 1
					}
				});
				webrtc.emit('createdPeer', peer);
				peer.start();
			}

			//Send shared screen to new participants
			if (webrtc.getLocalScreen() && !webrtc.webrtc.getPeers(sessionId, 'screen').length) {
				peer = webrtc.webrtc.createPeer({
					id: sessionId,
					type: 'screen',
					sharemyscreen: true,
					enableDataChannels: false,
					receiveMedia: {
						offerToReceiveAudio: 0,
						offerToReceiveVideo: 0
					},
					broadcaster: currentSessionId,
				});
				webrtc.emit('createdPeer', peer);
				peer.start();
			}
		});

		disconnectedSessionIds.forEach(function(sessionId) {
			console.log('XXX Remove peer', sessionId);
			OCA.SpreedMe.webrtc.removePeers(sessionId);
			OCA.SpreedMe.speakers.remove(sessionId, true);
			OCA.SpreedMe.videos.remove(sessionId);
			delete spreedMappingTable[sessionId];
			delete guestNamesTable[sessionId];
		});

		previousUsersInRoom = previousUsersInRoom.diff(disconnectedSessionIds);
		updateParticipantsUI(previousUsersInRoom.length + 1);
	}

	function usersInCallChanged(users) {
		// The passed list are the users that are currently in the room,
		// i.e. that are in the call and should call each other.
		var currentSessionId = webrtc.connection.getSessionid();
		var currentUsersInRoom = [];
		var userMapping = {};
		var selfInCall = false;
		var sessionId;
		for (sessionId in users) {
			if (!users.hasOwnProperty(sessionId)) {
				continue;
			}
			var user = users[sessionId];
			if (!user.inCall) {
				continue;
			}

			if (sessionId === currentSessionId) {
				selfInCall = true;
				continue;
			}

			currentUsersInRoom.push(sessionId);
			userMapping[sessionId] = user;
		}

		if (!selfInCall) {
			// Own session is no longer in the call, disconnect from all others.
			usersChanged([], previousUsersInRoom);
			return;
		}

		var newSessionIds = currentUsersInRoom.diff(previousUsersInRoom);
		var disconnectedSessionIds = previousUsersInRoom.diff(currentUsersInRoom);
		var newUsers = [];
		newSessionIds.forEach(function(sessionId) {
			newUsers.push(userMapping[sessionId]);
		});
		if (newUsers.length || disconnectedSessionIds.length) {
			usersChanged(newUsers, disconnectedSessionIds);
		}
	}

	function initWebRTC() {
		'use strict';
		Array.prototype.diff = function(a) {
			return this.filter(function(i) {
				return a.indexOf(i) < 0;
			});
		};

		var signaling = OCA.SpreedMe.createSignalingConnection();
		signaling.on('usersLeft', function(users) {
			users.forEach(function(user) {
				delete usersInCallMapping[user];
			});
			usersChanged([], users);
		});
		signaling.on('usersChanged', function(users) {
			users.forEach(function(user) {
				var sessionId = user.sessionId || user.sessionid;
				usersInCallMapping[sessionId] = user;
			});
			usersInCallChanged(usersInCallMapping);
		});
		signaling.on('usersInRoom', function(users) {
			usersInCallMapping = {};
			users.forEach(function(user) {
				var sessionId = user.sessionId || user.sessionid;
				usersInCallMapping[sessionId] = user;
			});
			usersInCallChanged(usersInCallMapping);
		});

		var nick = OC.getCurrentUser()['displayName'];

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
			connection: signaling,
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

		OCA.SpreedMe.videos = {
			getContainerId: function(id) {
				var sanitizedId = id.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
				return '#container_' + sanitizedId + '_video_incoming';
			},
			add: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}
				// Indicator for username
				var userIndicator = document.createElement('div');
				userIndicator.className = 'nameIndicator';

				// Avatar for username
				var avatar = document.createElement('div');
				avatar.className = 'avatar icon-loading';

				var userId = spreedMappingTable[id];
				if (userId && userId.length) {
					$(avatar).avatar(userId, 128);
				} else {
					$(avatar).imageplaceholder('?', undefined, 128);
					$(avatar).css('background-color', '#b9b9b9');
				}

				$(avatar).css('opacity', '0.5');

				var avatarContainer = document.createElement('div');
				avatarContainer.className = 'avatar-container';
				avatarContainer.appendChild(avatar);

				// Media indicators
				var mediaIndicator = document.createElement('div');
				mediaIndicator.className = 'mediaIndicator';

				var muteIndicator = document.createElement('button');
				muteIndicator.className = 'muteIndicator icon-white icon-shadow icon-audio-off audio-on';
				muteIndicator.disabled = true;

				var screenSharingIndicator = document.createElement('button');
				screenSharingIndicator.className = 'screensharingIndicator icon-white icon-shadow icon-screen screen-off';
				screenSharingIndicator.setAttribute('data-original-title', t('spreed', 'Show screen'));

				var iceFailedIndicator = document.createElement('button');
				iceFailedIndicator.className = 'iceFailedIndicator icon-white icon-shadow icon-error not-failed';
				iceFailedIndicator.disabled = true;

				$(screenSharingIndicator).tooltip({
					placement: 'top',
					trigger: 'hover'
				});

				mediaIndicator.appendChild(muteIndicator);
				mediaIndicator.appendChild(screenSharingIndicator);
				mediaIndicator.appendChild(iceFailedIndicator);

				// Generic container
				var container = document.createElement('div');
				container.className = 'videoContainer';
				container.id = 'container_' + id + '_video_incoming';
				container.appendChild(avatarContainer);
				container.appendChild(userIndicator);
				container.appendChild(mediaIndicator);

				$(container).prependTo($('#videos'));
				return container;
			},
			remove: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				$(OCA.SpreedMe.videos.getContainerId(id)).remove();
			},
			addPeer: function(peer) {
				var newContainer = $(OCA.SpreedMe.videos.getContainerId(peer.id));
				if (newContainer.length === 0) {
					newContainer = $(OCA.SpreedMe.videos.add(peer.id));
				}

				// Initialize ice restart counter for peer
				spreedPeerConnectionTable[peer.id] = 0;

				peer.pc.on('iceConnectionStateChange', function () {
					var avatar = $(newContainer).find('.avatar');
					var mediaIndicator = $(newContainer).find('.mediaIndicator');
					avatar.removeClass('icon-loading');
					mediaIndicator.find('.iceFailedIndicator').addClass('not-failed');

					switch (peer.pc.iceConnectionState) {
						case 'checking':
							avatar.addClass('icon-loading');
							console.log('Connecting to peer...');
							break;
						case 'connected':
						case 'completed': // on caller side
							console.log('Connection established.');
							avatar.css('opacity', '1');
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
								var currentGuestNick = localStorage.getItem("nick");
								OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'nickChanged', currentGuestNick);
							}

							// Reset ice restart counter for peer
							if (spreedPeerConnectionTable[peer.id] > 0) {
								spreedPeerConnectionTable[peer.id] = 0;
							}

							break;
						case 'disconnected':
							console.log('Disconnected.');
							// If the peer is still disconnected after 5 seconds we try ICE restart.
							setTimeout(function() {
								if(peer.pc.iceConnectionState === 'disconnected') {
									avatar.addClass('icon-loading');
									if (spreedPeerConnectionTable[peer.id] < 5) {
										if (peer.pc.pc.peerconnection.localDescription.type === 'offer' &&
											peer.pc.pc.peerconnection.signalingState === 'stable') {
											spreedPeerConnectionTable[peer.id] ++;
											console.log('ICE restart.');
											peer.icerestart();
										}
									}
								}
							}, 5000);
							break;
						case 'failed':
							console.log('Connection failed.');
							if (spreedPeerConnectionTable[peer.id] < 5) {
								avatar.addClass('icon-loading');
								if (peer.pc.pc.peerconnection.localDescription.type === 'offer' &&
									peer.pc.pc.peerconnection.signalingState === 'stable') {
									spreedPeerConnectionTable[peer.id] ++;
									console.log('ICE restart.');
									peer.icerestart();
								}
							} else {
								console.log('ICE failed after 5 tries.');
								mediaIndicator.children().hide();
								mediaIndicator.find('.iceFailedIndicator').removeClass('not-failed').show();
							}
							break;
						case 'closed':
							console.log('Connection closed.');
							break;
					}

					if (latestSpeakerId === peer.id) {
						OCA.SpreedMe.speakers.updateVideoContainerDummy(peer.id);
					}
				});

				peer.pc.on('PeerConnectionTrace', function (event) {
					console.log('trace', event);
				});
			}
		};

		OCA.SpreedMe.speakers = {
			switchVideoToId: function(id) {
				if (screenSharingActive || latestSpeakerId === id) {
					return;
				}

				var newContainer = $(OCA.SpreedMe.videos.getContainerId(id));
				if(newContainer.find('video').length === 0) {
					console.warn('promote: no video found for ID', id);
					return;
				}

				if (latestSpeakerId !== null) {
					// move old video to new location
					var oldContainer = $(OCA.SpreedMe.videos.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
				}

				newContainer.addClass('promoted');
				OCA.SpreedMe.speakers.updateVideoContainerDummy(id);

				latestSpeakerId = id;
			},
			unpromoteLatestSpeaker: function() {
				if (latestSpeakerId) {
					var oldContainer = $(OCA.SpreedMe.videos.getContainerId(latestSpeakerId));
					oldContainer.removeClass('promoted');
					unpromotedSpeakerId = latestSpeakerId;
					latestSpeakerId = null;
					$('.videoContainer-dummy').remove();
				}
			},
			updateVideoContainerDummy: function(id) {
				var newContainer = $(OCA.SpreedMe.videos.getContainerId(id));

				$('.videoContainer-dummy').remove();

				newContainer.after(
					$('<div>')
						.addClass('videoContainer videoContainer-dummy')
						.append(newContainer.find('.nameIndicator').clone())
						.append(newContainer.find('.mediaIndicator').clone())
						.append(newContainer.find('.speakingIndicator').clone())
					);

			},
			add: function(id, notPromote) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				if (notPromote) {
					spreedListofSpeakers[id] = 1;
					return;
				}

				spreedListofSpeakers[id] = (new Date()).getTime();

				// set speaking class
				$(OCA.SpreedMe.videos.getContainerId(id)).addClass('speaking');

				if (latestSpeakerId === id) {
					return;
				}

				OCA.SpreedMe.speakers.switchVideoToId(id);
			},
			remove: function(id, enforce) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				if (enforce) {
					delete spreedListofSpeakers[id];
				}

				// remove speaking class
				$(OCA.SpreedMe.videos.getContainerId(id)).removeClass('speaking');

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
					if (currentTime > mostRecentTime && $(OCA.SpreedMe.videos.getContainerId(currentId)).length > 0) {
						mostRecentTime = currentTime;
						mostRecentId = currentId;
					}
				}

				if (mostRecentId !== null) {
					OCA.SpreedMe.speakers.switchVideoToId(mostRecentId);
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
				$(OCA.SpreedMe.videos.getContainerId(id)).find('.screensharingIndicator').addClass('screen-visible');

				latestScreenId = id;
			},
			add: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				spreedListofSharedScreens[id] = (new Date()).getTime();

				var currentUser = OCA.SpreedMe.webrtc.connection.getSessionid();
				if (currentUser !== id) {
					var screensharingIndicator = $(OCA.SpreedMe.videos.getContainerId(id)).find('.screensharingIndicator');
					screensharingIndicator.removeClass('screen-off');
					screensharingIndicator.addClass('screen-on');

					screensharingIndicator.click(function() {
						if (!this.classList.contains('screen-visible')) {
							OCA.SpreedMe.sharedScreens.switchScreenToId(id);
						}
						$(this).tooltip('hide');
					});
				}

				OCA.SpreedMe.sharedScreens.switchScreenToId(id);
			},
			remove: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				delete spreedListofSharedScreens[id];

				var screensharingIndicator = $(OCA.SpreedMe.videos.getContainerId(id)).find('.screensharingIndicator');
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
			console.log('PEER CREATED', peer);
			if (peer.type === 'video') {
				OCA.SpreedMe.videos.addPeer(peer);
			}
		});

		OCA.SpreedMe.webrtc.on('localMediaStarted', function (configuration) {
			OCA.SpreedMe.app.startSpreed(configuration, signaling);
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
			OCA.SpreedMe.app.syncAndSetActiveRoom(name);
		});

		OCA.SpreedMe.webrtc.on('joinedCall', function() {
			OCA.SpreedMe.app.syncRooms();

			$('#app-content').removeClass('icon-loading');
			$('.videoView').removeClass('hidden');
		});

		OCA.SpreedMe.webrtc.on('leftCall', function() {
			OCA.SpreedMe.app.syncRooms();
		});

		OCA.SpreedMe.webrtc.on('channelOpen', function(channel) {
			console.log('%s datachannel is open', channel.label);
		});

		OCA.SpreedMe.webrtc.on('channelMessage', function (peer, label, data) {
			if (label === 'status') {
				if(data.type === 'speaking') {
					OCA.SpreedMe.speakers.add(peer.id);
				} else if(data.type === 'stoppedSpeaking') {
					OCA.SpreedMe.speakers.remove(peer.id);
				} else if(data.type === 'audioOn') {
					OCA.SpreedMe.webrtc.emit('unmute', {id: peer.id, name:'audio'});
				} else if(data.type === 'audioOff') {
					OCA.SpreedMe.webrtc.emit('mute', {id: peer.id, name:'audio'});
				} else if(data.type === 'videoOn') {
					OCA.SpreedMe.webrtc.emit('unmute', {id: peer.id, name:'video'});
				} else if(data.type === 'videoOff') {
					OCA.SpreedMe.webrtc.emit('mute', {id: peer.id, name:'video'});
				} else if (data.type === 'nickChanged') {
					OCA.SpreedMe.webrtc.emit('nick', {id: peer.id, name:data.payload});
				}
			} else if (label === 'hark') {
				// Ignore messages from hark datachannel
			} else {
				console.log('Uknown message from %s datachannel', label, data);
			}
		});

		OCA.SpreedMe.webrtc.on('videoAdded', function(video, peer) {
			console.log('VIDEO ADDED', peer);
			if (peer.type === 'screen') {
				OCA.SpreedMe.webrtc.emit('screenAdded', video, peer);
				return;
			}

			var videoContainer = $(OCA.SpreedMe.videos.getContainerId(peer.id));
			if (videoContainer.length) {
				var userId = spreedMappingTable[peer.id];
				var guestName = guestNamesTable[peer.id];
				var nameIndicator = videoContainer.find('.nameIndicator');
				var avatar = videoContainer.find('.avatar');

				if (userId && userId.length) {
					avatar.avatar(userId, 128);
					nameIndicator.text(peer.nick);
				} else if (peer.nick) {
					avatar.imageplaceholder(peer.nick, undefined, 128);
					nameIndicator.text(peer.nick);
				} else if (guestName && guestName.length > 0) {
					avatar.imageplaceholder(guestName, undefined, 128);
					nameIndicator.text(guestName);
				} else {
					avatar.imageplaceholder('?', undefined, 128);
					avatar.css('background-color', '#b9b9b9');
					nameIndicator.text(t('spreed', 'Guest'));
				}

				$(videoContainer).prepend(video);
				video.oncontextmenu = function() {
					return false;
				};
			}

			var otherSpeakerPromoted = false;
			for (var key in spreedListofSpeakers) {
				if (spreedListofSpeakers.hasOwnProperty(key) && spreedListofSpeakers[key] > 1) {
					otherSpeakerPromoted = true;
					break;
				}
			}
			if (!otherSpeakerPromoted) {
				OCA.SpreedMe.speakers.add(peer.id);
			} else {
				OCA.SpreedMe.speakers.add(peer.id, true);
			}
		});

		OCA.SpreedMe.webrtc.on('speaking', function(){
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'speaking');
			$('#localVideoContainer').addClass('speaking');
		});
		OCA.SpreedMe.webrtc.on('stoppedSpeaking', function(){
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'stoppedSpeaking');
			$('#localVideoContainer').removeClass('speaking');
		});

		// a peer was removed
		OCA.SpreedMe.webrtc.on('videoRemoved', function(video, peer) {
			if (peer) {
				if (peer.type === 'video') {
					// a removed peer can't speak anymore ;)
					OCA.SpreedMe.speakers.remove(peer.id, true);

					var videoContainer = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId(peer));
					var el = document.getElementById(OCA.SpreedMe.webrtc.getDomId(peer));
					if (videoContainer && el) {
						videoContainer.removeChild(el);
					}
				} else if (peer.type === 'screen') {
					var remotes = document.getElementById('screens');
					var screenContainer = document.getElementById('container_' + OCA.SpreedMe.webrtc.getDomId(peer));
					if (remotes && screenContainer) {
						remotes.removeChild(screenContainer);
					}

					OCA.SpreedMe.sharedScreens.remove(peer.id);
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
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'audioOn');
		});
		OCA.SpreedMe.webrtc.on('audioOff', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'audioOff');
		});
		OCA.SpreedMe.webrtc.on('videoOn', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'videoOn');
		});
		OCA.SpreedMe.webrtc.on('videoOff', function() {
			OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'videoOff');
		});

		OCA.SpreedMe.webrtc.on('screenAdded', function(video, peer) {
			OCA.SpreedMe.speakers.unpromoteLatestSpeaker();

			screenSharingActive = true;
			$('#app-content').addClass('screensharing');

			var screens = document.getElementById('screens');
			if (screens) {
				// Indicator for username
				var userIndicator = document.createElement('div');
				userIndicator.className = 'nameIndicator';
				if (peer) {
					var guestName = guestNamesTable[peer.id];
					if (peer.nick) {
						userIndicator.textContent = t('spreed', "{participantName}'s screen", {participantName: peer.nick});
					} else if (guestName && guestName.length > 0) {
						userIndicator.textContent = t('spreed', "{participantName}'s screen", {participantName: guestName});
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

			if (!data.name) {
				videoNameIndicator.text(t('spreed', 'Guest'));
				videoAvatar.imageplaceholder('?', undefined, 128);
				videoAvatar.css('background-color', '#b9b9b9');
				screenNameIndicator.text(t('spreed', "Guest's screen"));
			} else {
				videoNameIndicator.text(data.name);
				videoAvatar.imageplaceholder(data.name, undefined, 128);
				screenNameIndicator.text(t('spreed', "{participantName}'s screen", {participantName: data.name}));
				guestNamesTable[data.id] = data.name;
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

			var $hideVideoButton = $('#hideVideo');
			if (OCA.SpreedMe.webrtc.webrtc.localStream.getVideoTracks().length === 0) {
				$hideVideoButton.removeClass('video-disabled icon-video')
					.addClass('no-video-available icon-video-off')
					.attr('data-original-title', t('spreed', 'No Camera'));
			}
		});
	}

	OCA.SpreedMe.initWebRTC = initWebRTC;

})(OCA, OC);

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
	var ownPeer = null;
	var ownScreenPeer = null;
	var hasLocalMedia = false;
	var selfInCall = 0;  // OCA.SpreedMe.app.FLAG_DISCONNECTED, not available yet.
	var delayedCreatePeer = [];

	function updateParticipantsUI(currentUsersNo) {
		'use strict';
		if (!currentUsersNo) {
			currentUsersNo = 1;
		}

		var $appContentElement = $(OCA.SpreedMe.app.mainCallElementSelector),
			participantsClass = 'participants-' + currentUsersNo,
			hadScreensharing = $appContentElement.hasClass('screensharing'),
			hadSidebar = $appContentElement.hasClass('with-app-sidebar');
		if (!$appContentElement.hasClass(participantsClass)) {
			$appContentElement.attr('class', '').addClass(participantsClass);
			if (currentUsersNo > 1) {
				$appContentElement.addClass('incall');
			} else {
				$appContentElement.removeClass('incall');
			}

			if (hadScreensharing) {
				$appContentElement.addClass('screensharing');
			}

			if (hadSidebar) {
				$appContentElement.addClass('with-app-sidebar');
			}
		}
	}

	function createScreensharingPeer(signaling, sessionId) {
		var currentSessionId = signaling.getSessionid();
		var useMcu = signaling.hasFeature("mcu");

		if (useMcu && !webrtc.webrtc.getPeers(currentSessionId, 'screen').length) {
			if (ownScreenPeer) {
				ownScreenPeer.end();
			}

			// Create own publishing stream.
			ownScreenPeer = webrtc.webrtc.createPeer({
				id: currentSessionId,
				type: 'screen',
				sharemyscreen: true,
				enableDataChannels: false,
				receiveMedia: {
					offerToReceiveAudio: 0,
					offerToReceiveVideo: 0
				},
				broadcaster: currentSessionId,
			});
			webrtc.emit('createdPeer', ownScreenPeer);
			ownScreenPeer.start();
		}

		if (sessionId === currentSessionId) {
			return;
		}

		if (useMcu) {
			// TODO(jojo): Already create peer object to avoid duplicate offers.
			// TODO(jojo): We should use "requestOffer" as with regular
			// audio/video peers. Not possible right now as there is no way
			// for clients to know that screensharing is active and an offer
			// from the MCU should be requested.
			webrtc.connection.sendOffer(sessionId, "screen");
		} else if (!useMcu) {
			var screenPeers = webrtc.webrtc.getPeers(sessionId, 'screen');
			var screenPeerSharedTo = screenPeers.find(function(screenPeer) {
				return screenPeer.sharemyscreen === true;
			});
			if (!screenPeerSharedTo) {
				var peer = webrtc.webrtc.createPeer({
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
		}
	}

	function checkStartPublishOwnPeer(signaling) {
		'use strict';
		var currentSessionId = signaling.getSessionid();
		if (!hasLocalMedia || webrtc.webrtc.getPeers(currentSessionId, 'video').length) {
			// No media yet or already publishing.
			return;
		}

		if (ownPeer) {
			OCA.SpreedMe.webrtc.removePeers(ownPeer.id);
			OCA.SpreedMe.speakers.remove(ownPeer.id, true);
			OCA.SpreedMe.videos.remove(ownPeer.id);
			delete spreedMappingTable[ownPeer.id];
			ownPeer.end();
		}

		// Create own publishing stream.
		ownPeer = webrtc.webrtc.createPeer({
			id: currentSessionId,
			type: "video",
			enableDataChannels: true,
			receiveMedia: {
				offerToReceiveAudio: 0,
				offerToReceiveVideo: 0
			}
		});
		webrtc.emit('createdPeer', ownPeer);
		ownPeer.start();
	}

	function userHasStreams(user) {
		var flags = user;
		if (flags.hasOwnProperty('inCall')) {
			flags = flags.inCall;
		}
		flags = flags || OCA.SpreedMe.app.FLAG_DISCONNECTED;
		var REQUIRED_FLAGS = OCA.SpreedMe.app.FLAG_WITH_AUDIO | OCA.SpreedMe.app.FLAG_WITH_VIDEO;
		return (flags & REQUIRED_FLAGS) !== 0;
	}

	function usersChanged(signaling, newUsers, disconnectedSessionIds) {
		'use strict';
		var currentSessionId = signaling.getSessionid();

		var useMcu = signaling.hasFeature("mcu");
		if (useMcu && newUsers.length) {
			checkStartPublishOwnPeer(signaling);
		}

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

			var createPeer = function() {
				var peer = webrtc.webrtc.createPeer({
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
			};

			if (!webrtc.webrtc.getPeers(sessionId, 'video').length) {
				if (useMcu) {
					// TODO(jojo): Already create peer object to avoid duplicate offers.
					webrtc.connection.requestOffer(user, "video");
				} else if (userHasStreams(selfInCall) && (!userHasStreams(user) || sessionId < currentSessionId)) {
					// To avoid overloading the user joining a room (who previously called
					// all the other participants), we decide who calls who by comparing
					// the session ids of the users: "larger" ids call "smaller" ones.
					console.log("Starting call with", user);
					createPeer();
				} else if (userHasStreams(selfInCall) && userHasStreams(user) && sessionId > currentSessionId) {
					// If the remote peer is not aware that it was disconnected
					// from the current peer the remote peer will not send a new
					// offer; thus, if the current peer does not receive a new
					// offer in a reasonable time, the current peer calls the
					// remote peer instead of waiting to be called to
					// reestablish the connection.
					delayedCreatePeer[sessionId] = setTimeout(function() {
						createPeer();
					}, 10000);
				}
			}

			//Send shared screen to new participants
			if (webrtc.getLocalScreen()) {
				createScreensharingPeer(signaling, sessionId);
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

	function usersInCallChanged(signaling, users) {
		// The passed list are the users that are currently in the room,
		// i.e. that are in the call and should call each other.
		var currentSessionId = signaling.getSessionid();
		var currentUsersInRoom = [];
		var userMapping = {};
		selfInCall = OCA.SpreedMe.app.FLAG_DISCONNECTED;
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
				selfInCall = user.inCall;
				continue;
			}

			currentUsersInRoom.push(sessionId);
			userMapping[sessionId] = user;
		}

		if (!selfInCall) {
			// Own session is no longer in the call, disconnect from all others.
			usersChanged(signaling, [], previousUsersInRoom);
			return;
		}

		var newSessionIds = currentUsersInRoom.diff(previousUsersInRoom);
		var disconnectedSessionIds = previousUsersInRoom.diff(currentUsersInRoom);
		var newUsers = [];
		newSessionIds.forEach(function(sessionId) {
			newUsers.push(userMapping[sessionId]);
		});
		if (newUsers.length || disconnectedSessionIds.length) {
			usersChanged(signaling, newUsers, disconnectedSessionIds);
		}
	}

	/**
	 * @param {OCA.Talk.Application} app
	 */
	function initWebRTC(app) {
		Array.prototype.diff = function(a) {
			return this.filter(function(i) {
				return a.indexOf(i) < 0;
			});
		};

		var signaling = app.signaling;
		signaling.on('usersLeft', function(users) {
			users.forEach(function(user) {
				delete usersInCallMapping[user];
			});
			usersChanged(signaling, [], users);
		});
		signaling.on('usersChanged', function(users) {
			users.forEach(function(user) {
				var sessionId = user.sessionId || user.sessionid;
				usersInCallMapping[sessionId] = user;
			});
			usersInCallChanged(signaling, usersInCallMapping);
		});
		signaling.on('usersInRoom', function(users) {
			usersInCallMapping = {};
			users.forEach(function(user) {
				var sessionId = user.sessionId || user.sessionid;
				usersInCallMapping[sessionId] = user;
			});
			usersInCallChanged(signaling, usersInCallMapping);
		});
		signaling.on('leaveCall', function () {
			webrtc.leaveCall();
		});

		signaling.on('message', function (message) {
			if (message.type !== 'offer') {
				return;
			}

			var peers = OCA.SpreedMe.webrtc.webrtc.peers;
			var stalePeer = peers.find(function(peer) {
				if (peer.sharemyscreen) {
					return false;
				}

				return peer.id === message.from && peer.type === message.roomType && peer.sid !== message.sid;
			});

			if (stalePeer) {
				stalePeer.end();

				if (message.roomType === 'video') {
					OCA.SpreedMe.speakers.remove(stalePeer.id, true);
					OCA.SpreedMe.videos.remove(stalePeer.id);
				}
			}

			if (message.roomType === 'video' && delayedCreatePeer[message.from]) {
				clearTimeout(delayedCreatePeer[message.from]);
				delete delayedCreatePeer[message.from];
			}
		});

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
			nick: OC.getCurrentUser().displayName
		});
		if (signaling.hasFeature('mcu')) {
			// Force "Plan-B" semantics if the MCU is used, which doesn't support
			// "Unified Plan" with SimpleWebRTC yet.
			webrtc.webrtc.config.peerConnectionConfig.sdpSemantics = 'plan-b';
		}
		OCA.SpreedMe.webrtc = webrtc;

		OCA.SpreedMe.webrtc.startMedia = function (token) {
			webrtc.joinCall(token);
		};

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

		var sendDataChannelToAll = function(channel, message, payload) {
			// If running with MCU, the message must be sent through the
			// publishing peer and will be distributed by the MCU to subscribers.
			var conn = OCA.SpreedMe.webrtc.connection;
			if (ownPeer && conn.hasFeature && conn.hasFeature('mcu')) {
				ownPeer.sendDirectly(channel, message, payload);
				return;
			}
			OCA.SpreedMe.webrtc.sendDirectlyToAll(channel, message, payload);
		};

		OCA.SpreedMe.videos = {
			getContainerId: function(id) {
				var sanitizedId = id.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, "\\$&");
				return '#container_' + sanitizedId + '_video_incoming';
			},
			add: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var user = usersInCallMapping[id];
				if (user && !userHasStreams(user)) {
					console.log("User has no stream", id);
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
				muteIndicator.className = 'muteIndicator force-icon-white-in-call icon-shadow icon-audio-off audio-on';
				muteIndicator.disabled = true;

				var hideRemoteVideoButton = document.createElement('button');
				hideRemoteVideoButton.className = 'hideRemoteVideo force-icon-white-in-call icon-shadow icon-video';
				hideRemoteVideoButton.setAttribute('style', 'display: none;');
				hideRemoteVideoButton.setAttribute('data-original-title', t('spreed', 'Disable video'));
				hideRemoteVideoButton.onclick = function() {
					OCA.SpreedMe.videos._toggleRemoteVideo(id);
				};

				var screenSharingIndicator = document.createElement('button');
				var screenOnOffClass = !!spreedListofSharedScreens[id]? 'screen-on': 'screen-off';
				screenSharingIndicator.className = 'screensharingIndicator force-icon-white-in-call icon-shadow icon-screen ' + screenOnOffClass;
				screenSharingIndicator.setAttribute('data-original-title', t('spreed', 'Show screen'));

				var iceFailedIndicator = document.createElement('button');
				iceFailedIndicator.className = 'iceFailedIndicator force-icon-white-in-call icon-shadow icon-error not-failed';
				iceFailedIndicator.disabled = true;

				$(hideRemoteVideoButton).tooltip({
					placement: 'top',
					trigger: 'hover'
				});

				$(screenSharingIndicator).tooltip({
					placement: 'top',
					trigger: 'hover'
				});

				mediaIndicator.appendChild(muteIndicator);
				mediaIndicator.appendChild(hideRemoteVideoButton);
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
			muteRemoteVideo: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var $container = $(OCA.SpreedMe.videos.getContainerId(id));

				$container.find('.avatar-container').show();
				$container.find('video').hide();
				$container.find('.hideRemoteVideo').hide();
			},
			unmuteRemoteVideo: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var $container = $(OCA.SpreedMe.videos.getContainerId(id));

				var $hideRemoteVideoButton = $container.find('.hideRemoteVideo');
				$hideRemoteVideoButton.show();

				if ($hideRemoteVideoButton.hasClass('icon-video')) {
					$container.find('.avatar-container').hide();
					$container.find('video').show();
				}
			},
			_toggleRemoteVideo: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				var $container = $(OCA.SpreedMe.videos.getContainerId(id));

				var $hideRemoteVideoButton = $container.find('.hideRemoteVideo');
				if ($hideRemoteVideoButton.hasClass('icon-video')) {
					$container.find('.avatar-container').show();
					$container.find('video').hide();
					$hideRemoteVideoButton.attr('data-original-title', t('spreed', 'Enable video'))
						.removeClass('icon-video')
						.addClass('icon-video-off');
				} else {
					$container.find('.avatar-container').hide();
					$container.find('video').show();
					$hideRemoteVideoButton.attr('data-original-title', t('spreed', 'Disable video'))
						.removeClass('icon-video-off')
						.addClass('icon-video');
				}

				if (latestSpeakerId === id) {
					OCA.SpreedMe.speakers.updateVideoContainerDummy(id);
				}
			},
			remove: function(id) {
				if (!(typeof id === 'string' || id instanceof String)) {
					return;
				}

				$(OCA.SpreedMe.videos.getContainerId(id)).remove();
			},
			addPeer: function(peer) {
				var signaling = OCA.SpreedMe.app.signaling;
				if (peer.id === webrtc.connection.getSessionid()) {
					console.log("Not adding video for own peer", peer);
					OCA.SpreedMe.videos.startSendingNick(peer);
					return;
				}

				var newContainer = $(OCA.SpreedMe.videos.getContainerId(peer.id));
				if (newContainer.length === 0) {
					newContainer = $(OCA.SpreedMe.videos.add(peer.id));
				}

				// Initialize ice restart counter for peer
				spreedPeerConnectionTable[peer.id] = 0;

				peer.pc.on('iceConnectionStateChange', function () {
					var userId = spreedMappingTable[peer.id];
					var avatar = $(newContainer).find('.avatar');
					var nameIndicator = $(newContainer).find('.nameIndicator');
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
							// Ensure that the peer name is shown, as the name
							// indicator for registered users without microphone
							// nor camera will not be updated later.
							if (userId && userId.length) {
								nameIndicator.text(peer.nick);
							}
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
								sendDataChannelToAll('status', 'nickChanged', currentGuestNick);
							}

							// Reset ice restart counter for peer
							if (spreedPeerConnectionTable[peer.id] > 0) {
								spreedPeerConnectionTable[peer.id] = 0;
							}
							break;
						case 'disconnected':
							console.log('Disconnected.');
							if (!signaling.hasFeature("mcu")) {
								// ICE failures will be handled in "iceFailed"
								// below for MCU installations.
								setTimeout(function() {
									// If the peer is still disconnected after 5 seconds we try ICE restart.
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
							}
							break;
						case 'failed':
							console.log('Connection failed.');
							if (!signaling.hasFeature("mcu")) {
								// ICE failures will be handled in "iceFailed"
								// below for MCU installations.
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
			},
			// The nick name below the avatar is distributed through the
			// DataChannel of the PeerConnection and only sent once during
			// establishment. For the MCU case, the sending PeerConnection
			// is created once and then never changed when more participants
			// join. For this, we periodically send the nick to all other
			// participants through the sending PeerConnection.
			//
			// TODO: The name for the avatar should come from the participant
			// list which already has all information and get rid of using the
			// DataChannel for this.
			startSendingNick: function(peer) {
				if (!signaling.hasFeature("mcu")) {
					return;
				}

				OCA.SpreedMe.videos.stopSendingNick(peer);
				peer.nickInterval = setInterval(function() {
					var payload;
					var user = OC.getCurrentUser();
					if (!user.uid) {
						payload = localStorage.getItem("nick");
					} else {
						payload = {
							"name": user.displayName,
							"userid": user.uid
						};
					}
					peer.sendDirectly('status', "nickChanged", payload);
				}, 1000);
			},
			stopSendingNick: function(peer) {
				if (!peer.nickInterval) {
					return;
				}

				clearInterval(peer.nickInterval);
				peer.nickInterval = null;
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

				// Cloning does not copy event handlers by default; it could be
				// forced with a parameter, but the tooltip would have to be
				// explicitly set on the new element anyway. Due to this the
				// click handler is explicitly copied too.
				$('.videoContainer-dummy').find('.hideRemoteVideo').get(0).onclick = newContainer.find('.hideRemoteVideo').get(0).onclick;
				$('.videoContainer-dummy').find('.hideRemoteVideo').tooltip({
					placement: 'top',
					trigger: 'hover'
				});
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
					// if there is no mostRecentId available, there is no user left in call
					// remove the remaining dummy container then too
					OCA.SpreedMe.speakers.unpromoteLatestSpeaker();
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
				// Make sure required data channels exist for all peers. This
				// is required for peers that get created by SimpleWebRTC from
				// received "Offer" messages. Otherwise the "channelMessage"
				// will not be called.
				peer.getDataChannel('status');
			}
		});

		function checkPeerMedia(peer, track, mediaType) {
			var defer = $.Deferred();
			peer.pc.pc.getStats(track, function(stats) {
				var result = false;
				Object.keys(stats).forEach(function(key) {
					var value = stats[key];
					if (!result && !value || value.mediaType !== mediaType || !value.hasOwnProperty('bytesReceived')) {
						return;
					}

					if (value.bytesReceived > 0) {
						OCA.SpreedMe.webrtc.emit('unmute', {
							id: peer.id,
							name: mediaType
						});
						result = true;
					}
				});
				if (result) {
					defer.resolve();
				} else {
					defer.reject();
				}
			});
			return defer;
		}

		function stopPeerCheckMedia(peer) {
			if (peer.check_audio_interval) {
				clearInterval(peer.check_audio_interval);
				peer.check_audio_interval = null;
			}
			if (peer.check_video_interval) {
				clearInterval(peer.check_video_interval);
				peer.check_video_interval = null;
			}
			OCA.SpreedMe.videos.stopSendingNick(peer);
		}

		function startPeerCheckMedia(peer, stream) {
			stopPeerCheckMedia(peer);
			peer.check_video_interval = setInterval(function() {
				stream.getVideoTracks().forEach(function(video) {
					checkPeerMedia(peer, video, 'video').then(function() {
						clearInterval(peer.check_video_interval);
						peer.check_video_interval = null;
					});
				});
			}, 1000);
			peer.check_audio_interval = setInterval(function() {
				stream.getAudioTracks().forEach(function(audio) {
					checkPeerMedia(peer, audio, 'audio').then(function() {
						clearInterval(peer.check_audio_interval);
						peer.check_audio_interval = null;
					});
				});
			}, 1000);
		}

		OCA.SpreedMe.webrtc.on('peerStreamAdded', function (peer) {
			// With the MCU, a newly subscribed stream might not get the
			// "audioOn"/"videoOn" messages as they are only sent when
			// a user starts publishing. Instead wait for initial data
			// and trigger events locally.
			if (!OCA.SpreedMe.app.signaling.hasFeature("mcu")) {
				return;
			}

			startPeerCheckMedia(peer, peer.stream);
		});

		OCA.SpreedMe.webrtc.on('peerStreamRemoved', function (peer) {
			stopPeerCheckMedia(peer);
		});

		OCA.SpreedMe.webrtc.on('localScreenStopped', function() {
			app.disableScreensharingButton();
		});

		OCA.SpreedMe.webrtc.webrtc.on('iceFailed', function (/* peer */) {
			var signaling = OCA.SpreedMe.app.signaling;
			if (!signaling.hasFeature("mcu")) {
				// ICE restarts will be handled by "iceConnectionStateChange"
				// above.
				return;
			}

			// For now assume the connection to the MCU is interrupted on ICE
			// failures and force a reconnection of all streams.
			if (ownPeer) {
				OCA.SpreedMe.webrtc.removePeers(ownPeer.id);
				OCA.SpreedMe.speakers.remove(ownPeer.id, true);
				OCA.SpreedMe.videos.remove(ownPeer.id);
				delete spreedMappingTable[ownPeer.id];
				ownPeer.end();
				ownPeer = null;
			}
			usersChanged(signaling, [], previousUsersInRoom);
			usersInCallMapping = {};
			previousUsersInRoom = [];
			// Reconnects with a new session id will trigger "usersChanged"
			// with the users in the room and that will re-establish the
			// peerconnection streams.
			signaling.forceReconnect(true);
		});

		OCA.SpreedMe.webrtc.on('localMediaStarted', function (configuration) {
			console.log('localMediaStarted');
			app.startLocalMedia(configuration);
			hasLocalMedia = true;
			var signaling = OCA.SpreedMe.app.signaling;
			if (signaling.hasFeature("mcu")) {
				checkStartPublishOwnPeer(signaling);
			}
		});

		OCA.SpreedMe.webrtc.on('localMediaError', function(error) {
			console.log('Access to microphone & camera failed', error);
			hasLocalMedia = false;
			var message;
			if (error.name === "NotAllowedError") {
				if (error.message && error.message.indexOf("Only secure origins") !== -1) {
					message = t('spreed', 'Access to microphone & camera is only possible with HTTPS');
					message += ': ' + t('spreed', 'Please move your setup to HTTPS');
				} else {
					message = t('spreed', 'Access to microphone & camera was denied');
				}
			} else if(!OCA.SpreedMe.webrtc.capabilities.support) {
				console.log('WebRTC not supported');

				message = t('spreed', 'WebRTC is not supported in your browser');
				message += ': ' + t('spreed', 'Please use a different browser like Firefox or Chrome');
			} else {
				message = t('spreed', 'Error while accessing microphone & camera');
				console.log('Error while accessing microphone & camera: ', error.message || error.name);
			}

			app.startWithoutLocalMedia({audio: false, video: false});
			OC.Notification.show(message, {
				type: 'error',
				timeout: 15,
			});
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
					var payload = data.payload || '';
					if (typeof(payload) === 'string') {
						OCA.SpreedMe.webrtc.emit('nick', {id: peer.id, name:data.payload});
						app._messageCollection.updateGuestName(new Hashes.SHA1().hex(peer.id), data.payload);
					} else {
						OCA.SpreedMe.webrtc.emit('nick', {id: peer.id, name: payload.name, userid: payload.userid});
					}
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
				} else {
					avatar.imageplaceholder('?', peer.nick || guestName, 128);
					avatar.css('background-color', '#b9b9b9');
					nameIndicator.text(peer.nick || guestName || t('spreed', 'Guest'));
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
			sendDataChannelToAll('status', 'speaking');
			$('#localVideoContainer').addClass('speaking');
		});

		OCA.SpreedMe.webrtc.on('stoppedSpeaking', function(){
			sendDataChannelToAll('status', 'stoppedSpeaking');
			$('#localVideoContainer').removeClass('speaking');
		});

		// a peer was removed
		OCA.SpreedMe.webrtc.on('videoRemoved', function(video, peer) {
			var screens;

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

				screens = document.getElementById('screens');
				var localScreenContainer = document.getElementById('localScreenContainer');
				if (screens && localScreenContainer) {
					screens.removeChild(localScreenContainer);
				}

				OCA.SpreedMe.sharedScreens.remove(OCA.SpreedMe.webrtc.connection.getSessionid());
			}

			// Check if there are still some screens
			screens = document.getElementById('screens');
			if (!screens || !screens.hasChildNodes()) {
				screenSharingActive = false;
				$(OCA.SpreedMe.app.mainCallElementSelector).removeClass('screensharing');
				if (unpromotedSpeakerId) {
					OCA.SpreedMe.speakers.switchVideoToId(unpromotedSpeakerId);
					unpromotedSpeakerId = null;
				}
			}
		});

		// Send the audio on and off events via data channel
		OCA.SpreedMe.webrtc.on('audioOn', function() {
			sendDataChannelToAll('status', 'audioOn');
		});
		OCA.SpreedMe.webrtc.on('audioOff', function() {
			sendDataChannelToAll('status', 'audioOff');
		});
		OCA.SpreedMe.webrtc.on('videoOn', function() {
			sendDataChannelToAll('status', 'videoOn');
		});
		OCA.SpreedMe.webrtc.on('videoOff', function() {
			sendDataChannelToAll('status', 'videoOff');
		});

		OCA.SpreedMe.webrtc.on('screenAdded', function(video, peer) {
			OCA.SpreedMe.speakers.unpromoteLatestSpeaker();

			screenSharingActive = true;
			$(OCA.SpreedMe.app.mainCallElementSelector).addClass('screensharing');

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
			var signaling = OCA.SpreedMe.app.signaling;

			var currentSessionId = signaling.getSessionid();
			for (var sessionId in usersInCallMapping) {
				if (!usersInCallMapping.hasOwnProperty(sessionId)) {
					continue;
				} else if (sessionId === currentSessionId) {
					// Running with MCU, no need to create screensharing
					// subscriber for client itself.
					continue;
				}

				createScreensharingPeer(signaling, sessionId);
			}
		});

		OCA.SpreedMe.webrtc.on('localScreenStopped', function() {
			var signaling = OCA.SpreedMe.app.signaling;
			if (!signaling.hasFeature('mcu')) {
				// Only need to notify clients here if running with MCU.
				// Otherwise SimpleWebRTC will notify each client on its own.
				return;
			}

			var currentSessionId = signaling.getSessionid();
			OCA.SpreedMe.webrtc.getPeers().forEach(function(existingPeer) {
				if (ownScreenPeer && existingPeer.type === 'screen' && existingPeer.id === currentSessionId) {
					ownScreenPeer = null;
					existingPeer.end();
					signaling.sendRoomMessage({
						roomType: 'screen',
						type: 'unshareScreen'
					});
				}
			});
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
				screenNameIndicator.text(t('spreed', "Guest's screen"));
			} else {
				screenNameIndicator.text(t('spreed', "{participantName}'s screen", {participantName: data.name}));
				if (!data.userid) {
					guestNamesTable[data.id] = data.name;
				}
			}
			videoNameIndicator.text(data.name || t('spreed', 'Guest'));
			if (data.userid) {
				videoAvatar.avatar(data.userid, 128);
			} else {
				videoAvatar.imageplaceholder('?', data.name, 128);
				videoAvatar.css('background-color', '#b9b9b9');
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
				OCA.SpreedMe.videos.muteRemoteVideo(data.id);
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
				OCA.SpreedMe.videos.unmuteRemoteVideo(data.id);
			} else {
				var muteIndicator = $el.find('.muteIndicator');
				muteIndicator.removeClass('audio-off');
				muteIndicator.addClass('audio-on');
			}

			if (latestSpeakerId === data.id) {
				OCA.SpreedMe.speakers.updateVideoContainerDummy(data.id);
			}
		});
	}

	OCA.SpreedMe.initWebRTC = initWebRTC;

})(OCA, OC);

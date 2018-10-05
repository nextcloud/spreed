/* global Marionette, Backbone, OCA */

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

(function(OC, OCA, Marionette, Backbone, _, $) {
	'use strict';

	OCA.Talk = OCA.Talk || {};

	var roomChannel = Backbone.Radio.channel('rooms');

	OCA.Talk.Embedded = Marionette.Application.extend({
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USERSELFJOINED: 5,

		/* Must stay in sync with values in "lib/Room.php". */
		FLAG_DISCONNECTED: 0,
		FLAG_IN_CALL: 1,
		FLAG_WITH_AUDIO: 2,
		FLAG_WITH_VIDEO: 4,

		/** @property {OCA.SpreedMe.Models.Room} activeRoom  */
		activeRoom: null,

		/** @property {String} token  */
		token: null,

		/** @property {OCA.Talk.Connection} connection  */
		connection: null,

		/** @property {OCA.Talk.Signaling.base} signaling  */
		signaling: null,

		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,
		/** @property {OCA.SpreedMe.Views.RoomListView} _roomsView  */
		_roomsView: null,
		/** @property {OCA.SpreedMe.Models.ParticipantCollection} _participants  */
		_participants: null,
		/** @property {OCA.SpreedMe.Views.ParticipantView} _participantsView  */
		_participantsView: null,
		/** @property {boolean} videoWasEnabledAtLeastOnce  */
		videoWasEnabledAtLeastOnce: false,
		displayedGuestNameHint: false,
		audioDisabled: localStorage.getItem("audioDisabled"),
		audioNotFound: false,
		videoDisabled: localStorage.getItem("videoDisabled"),
		videoNotFound: false,
		fullscreenDisabled: true,
		_searchTerm: '',
		guestNick: null,
		_currentEmptyContent: null,
		_lastEmptyContent: null,
		_registerPageEvents: function() {
			// Initialize button tooltips
			$('[data-toggle="tooltip"]').tooltip({trigger: 'hover'}).click(function() {
				$(this).tooltip('hide');
			});

// 			this.registerLocalVideoButtonHandlers();
		},

// 		registerLocalVideoButtonHandlers: function() {
// 			$('#hideVideo').click(function() {
// 				if(!OCA.SpreedMe.app.videoWasEnabledAtLeastOnce) {
// 					// don't allow clicking the video toggle
// 					// when no video ever was streamed (that
// 					// means that permission wasn't granted
// 					// yet or there is no video available at
// 					// all)
// 					console.log('video can not be enabled - there was no stream available before');
// 					return;
// 				}
// 				if ($(this).hasClass('video-disabled')) {
// 					OCA.SpreedMe.app.enableVideo();
// 					localStorage.removeItem("videoDisabled");
// 				} else {
// 					OCA.SpreedMe.app.disableVideo();
// 					localStorage.setItem("videoDisabled", true);
// 				}
// 			});
// 
// 			$('#mute').click(function() {
// 				if (OCA.SpreedMe.webrtc.webrtc.isAudioEnabled()) {
// 					OCA.SpreedMe.app.disableAudio();
// 					localStorage.setItem("audioDisabled", true);
// 				} else {
// 					OCA.SpreedMe.app.enableAudio();
// 					localStorage.removeItem("audioDisabled");
// 				}
// 			});
// 
// 			$('#video-fullscreen').click(function() {
// 				if (this.fullscreenDisabled) {
// 					this.enableFullscreen();
// 				} else {
// 					this.disableFullscreen();
// 				}
// 			}.bind(this));
// 
// 			$('#screensharing-button').click(function() {
// 				var webrtc = OCA.SpreedMe.webrtc;
// 				if (!webrtc.capabilities.supportScreenSharing) {
// 					if (window.location.protocol === 'https:') {
// 						OC.Notification.showTemporary(t('spreed', 'Screensharing is not supported by your browser.'));
// 					} else {
// 						OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
// 					}
// 					return;
// 				}
// 
// 				if (webrtc.getLocalScreen()) {
// 					$('#screensharing-menu').toggleClass('open');
// 				} else {
// 					var screensharingButton = $(this);
// 					screensharingButton.prop('disabled', true);
// 					webrtc.shareScreen(function(err) {
// 						screensharingButton.prop('disabled', false);
// 						if (!err) {
// 							$('#screensharing-button').attr('data-original-title', t('spreed', 'Screensharing options'))
// 								.removeClass('screensharing-disabled icon-screen-off')
// 								.addClass('icon-screen');
// 							return;
// 						}
// 
// 						switch (err.name) {
// 							case "HTTPS_REQUIRED":
// 								OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
// 								break;
// 							case "PERMISSION_DENIED":
// 							case "NotAllowedError":
// 							case "CEF_GETSCREENMEDIA_CANCELED":  // Experimental, may go away in the future.
// 								break;
// 							case "FF52_REQUIRED":
// 								OC.Notification.showTemporary(t('spreed', 'Sharing your screen only works with Firefox version 52 or newer.'));
// 								break;
// 							case "EXTENSION_UNAVAILABLE":
// 								var  extensionURL = null;
// 								if (!!window.chrome && !!window.chrome.webstore) {// Chrome
// 									extensionURL = 'https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol';
// 								}
// 
// 								if (extensionURL) {
// 									var text = t('spreed', 'Screensharing extension is required to share your screen.');
// 									var element = $('<a>').attr('href', extensionURL).attr('target','_blank').text(text);
// 
// 									OC.Notification.showTemporary(element, {isHTML: true});
// 								} else {
// 									OC.Notification.showTemporary(t('spreed', 'Please use a different browser like Firefox or Chrome to share your screen.'));
// 								}
// 								break;
// 							default:
// 								OC.Notification.showTemporary(t('spreed', 'An error occurred while starting screensharing.'));
// 								console.log("Could not start screensharing", err);
// 								break;
// 						}
// 					});
// 				}
// 			});
// 
// 			$("#show-screen-button").on('click', function() {
// 				var currentUser = OCA.SpreedMe.webrtc.connection.getSessionid();
// 				OCA.SpreedMe.sharedScreens.switchScreenToId(currentUser);
// 
// 				$('#screensharing-menu').toggleClass('open', false);
// 			});
// 
// 			$("#stop-screen-button").on('click', function() {
// 				OCA.SpreedMe.webrtc.stopScreenShare();
// 			});
// 		},

		/**
		 * @param {string} token
		 */
		// TODO
		_setRoomActive: function(token) {
			if (OC.getCurrentUser().uid) {
				this._rooms.forEach(function(room) {
					room.set('active', room.get('token') === token);
				});
			}
		},
		// TODO
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.signaling.syncRooms()
				.then(function() {
					self.stopListening(self.activeRoom, 'change:participantFlags');

					var participants;
					if (OC.getCurrentUser().uid) {
						roomChannel.trigger('active', token);

						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								self.activeRoom = room;
							}
						});
						participants = self.activeRoom.get('participants');
					}
					// Disable video when entering a room with more than 5 participants.
					if (participants && Object.keys(participants).length > 5) {
						self.disableVideo();
					}

// 					self.updateContentsLayout();
// 					self.listenTo(self.activeRoom, 'change:participantFlags', self.updateContentsLayout);
// 
// 					self.updateSidebarWithActiveRoom();
				});
		},
		// TODO
		updateContentsLayout: function() {
			if (!this.activeRoom) {
				// This should never happen, but just in case
				return;
			}

// 			var flags = this.activeRoom.get('participantFlags') || 0;
// 			var inCall = flags & OCA.SpreedMe.app.FLAG_IN_CALL !== 0;
// 			if (inCall && this._chatViewInMainView === true) {
// 				this._chatView.saveScrollPosition();
// 				this._chatView.$el.detach();
// 				this._sidebarView.addTab('chat', { label: t('spreed', 'Chat'), icon: 'icon-comment', priority: 100 }, this._chatView);
// 				this._sidebarView.selectTab('chat');
// 				this._chatView.restoreScrollPosition();
// 				this._chatView.setTooltipContainer(this._chatView.$el);
// 				this._chatViewInMainView = false;
// 			} else if (!inCall && !this._chatViewInMainView) {
// 				this._chatView.saveScrollPosition();
// 				this._sidebarView.removeTab('chat');
// 				this._chatView.$el.prependTo('#app-content-wrapper');
// 				this._chatView.restoreScrollPosition();
// 				this._chatView.setTooltipContainer($('#app'));
// 				this._chatView.focusChatInput();
// 				this._chatViewInMainView = true;
// 			}
// 
// 			if (inCall) {
// 				$('#video-speaking').show();
// 				$('#videos').show();
// 				$('#screens').show();
// 				$('#emptycontent').hide();
// 			} else {
// 				$('#video-speaking').hide();
// 				$('#videos').hide();
// 				$('#screens').hide();
// 				$('#emptycontent').show();
// 			}
		},
		// TODO
		updateSidebarWithActiveRoom: function() {
// 			this._sidebarView.enable();

// 			// The sidebar has a width of 27% of the window width and a minimum
// 			// width of 300px. Therefore, when the window is 1111px wide or
// 			// narrower the sidebar will always be 300px wide, and when that
// 			// happens it will overlap with the content area (the narrower the
// 			// window the larger the overlap). Due to this the sidebar is opened
// 			// automatically only if it will not overlap with the content area.
// 			if ($(window).width() > 1111) {
// 				this._sidebarView.open();
// 			}

			var callInfoView = new OCA.SpreedMe.Views.CallInfoView({
				model: this.activeRoom,
				// TODO
// 				guestNameModel: this._localStorageModel
			});
// 			this._sidebarView.setCallInfoView(callInfoView);

			this._messageCollection.setRoomToken(this.activeRoom.get('token'));
			this._messageCollection.receiveMessages();
		},

		/**
		 *
		 * @param {string|Object} icon
		 * @param {string} icon.userId
		 * @param {string} icon.displayName
		 * @param {string} message
		 * @param {string} [messageAdditional]
		 * @param {string} [url]
		 */
		// TODO
		setEmptyContentMessage: function(icon, message, messageAdditional, url) {
			console.log("Set empty content message: " + message);
// 			var $icon = $('#emptycontent-icon'),
// 				$emptyContent = $('#emptycontent');
// 
// 			//Remove previous icon and avatar from emptycontent
// 			$icon.removeAttr('class').attr('class', '');
// 			$icon.html('');
// 
// 			if (url) {
// 				$('#shareRoomInput').removeClass('hidden').val(url);
// 				$('#shareRoomClipboardButton').removeClass('hidden');
// 			} else {
// 				$('#shareRoomInput').addClass('hidden');
// 				$('#shareRoomClipboardButton').addClass('hidden');
// 			}
// 
// 			if (typeof icon === 'string') {
// 				$icon.addClass(icon);
// 			} else {
// 				var $avatar = $('<div>');
// 				$avatar.addClass('avatar room-avatar');
// 				if (icon.userId !== icon.displayName) {
// 					$avatar.avatar(icon.userId, 128, undefined, false, undefined, icon.displayName);
// 				} else {
// 					$avatar.avatar(icon.userId, 128);
// 				}
// 				$icon.append($avatar);
// 			}
// 
// 			$emptyContent.find('h2').html(message);
// 			$emptyContent.find('p').text(messageAdditional ? messageAdditional : '');
// 			this._lastEmptyContent = this._currentEmptyContent;
// 			this._currentEmptyContent = arguments;
		},
		// TODO
		restoreEmptyContent: function() {
			console.log("Restore empty content");
// 			this.setEmptyContentMessage.apply(this, this._lastEmptyContent);
		},
		// TODO
		initialize: function() {
			// TODO
// 			this._sidebarView = new OCA.SpreedMe.Views.SidebarView();
// 			$('#content').append(this._sidebarView.$el);

			// TODO
			if (OC.getCurrentUser().uid) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			}

			// TODO
// 			this._sidebarView.listenTo(roomChannel, 'leaveCurrentRoom', function() {
// 				this.disable();
// 			});

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				id: 'chatTabView'
				// TODO
// 				guestNameModel: this._localStorageModel
			});

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this.stopReceivingMessages();
			});

			// TODO
			this.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this._chatView.$el.detach();
			});
// 			this.listenTo(roomChannel, 'leaveCurrentRoom', function() {
// 				this._chatView.$el.detach();
// 				this._chatViewInMainView = false;
// 
// 				$('#video-speaking').hide();
// 				$('#videos').hide();
// 				$('#screens').hide();
// 				$('#emptycontent').show();
// 			});

			$(document).on('click', this.onDocumentClick);
		},
		// TODO
		onStart: function() {
			this.signaling = OCA.Talk.Signaling.createConnection();
			this.connection = new OCA.Talk.Connection(this);

			this.signaling.on('joinRoom', function(/* token */) {
				this.inRoom = true;
			}.bind(this));

			$(window).unload(function () {
				this.connection.leaveCurrentRoom(false);
				this.signaling.disconnect();
			}.bind(this));

			this._registerPageEvents();
		},
		// TODO
		setupWebRTC: function() {
			if (!OCA.SpreedMe.webrtc) {
				OCA.SpreedMe.initWebRTC(this);
			}
			OCA.SpreedMe.webrtc.startMedia(this.token);
		},
		// TODO
		startLocalMedia: function(configuration) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(configuration);
				this.callbackAfterMedia = null;
			}

			$('.videoView').removeClass('hidden');
			this.initAudioVideoSettings(configuration);
			this.restoreEmptyContent();
		},
		// TODO
		startWithoutLocalMedia: function(isAudioEnabled, isVideoEnabled) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(null);
				this.callbackAfterMedia = null;
			}

			$('.videoView').removeClass('hidden');

			this.disableAudio();
			if (!isAudioEnabled) {
				this.hasNoAudio();
			}

			this.disableVideo();
			if (!isVideoEnabled) {
				this.hasNoVideo();
			}
		},
		// TODO
		onDocumentClick: function(event) {
			var uiChannel = Backbone.Radio.channel('ui');

			uiChannel.trigger('document:click', event);
		},
		// TODO
		initAudioVideoSettings: function(configuration) {
			if (this.audioDisabled) {
				this.disableAudio();
			}

			if (configuration.video !== false) {
				if (this.videoDisabled) {
					this.disableVideo();
				}
			} else {
				this.videoWasEnabledAtLeastOnce = false;
				this.disableVideo();
			}
		},
		// TODO
		enableAudioButton: function() {
			$('#mute').attr('data-original-title', t('spreed', 'Mute audio (m)'))
				.removeClass('audio-disabled icon-audio-off')
				.addClass('icon-audio');
		},
		// TODO
		enableAudio: function() {
			if (this.audioNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}
			OCA.SpreedMe.webrtc.unmute();
			this.enableAudioButton();
			this.audioDisabled = false;
		},
		// TODO
		disableAudioButton: function() {
			$('#mute').attr('data-original-title', t('spreed', 'Unmute audio (m)'))
				.addClass('audio-disabled icon-audio-off')
				.removeClass('icon-audio');
		},
		// TODO
		disableAudio: function() {
			if (this.audioNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}
			OCA.SpreedMe.webrtc.mute();
			this.disableAudioButton();
			this.audioDisabled = true;
		},
		// TODO
		hasAudio: function() {
			$('#mute').removeClass('no-audio-available');
			this.enableAudioButton();
			this.audioNotFound = false;
		},
		// TODO
		hasNoAudio: function() {
			$('#mute').removeClass('audio-disabled icon-audio')
				.addClass('no-audio-available icon-audio-off')
				.attr('data-original-title', t('spreed', 'No audio'));
			this.audioDisabled = true;
			this.audioNotFound = true;
		},
		// TODO
		enableVideoUI: function() {
			var $hideVideoButton = $('#hideVideo');
			var $audioMuteButton = $('#mute');
			var $screensharingButton = $('#screensharing-button');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			$hideVideoButton.attr('data-original-title', t('spreed', 'Disable video (v)'))
				.removeClass('local-video-disabled video-disabled icon-video-off')
				.addClass('icon-video');
			$audioMuteButton.removeClass('local-video-disabled');
			$screensharingButton.removeClass('local-video-disabled');

			avatarContainer.hide();
			localVideo.show();
		},
		// TODO
		enableVideo: function() {
			if (this.videoNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.resumeVideo();
			this.enableVideoUI();
			this.videoDisabled = false;
		},
		// TODO
		hideVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var $audioMuteButton = $('#mute');
			var $screensharingButton = $('#screensharing-button');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			if (!$hideVideoButton.hasClass('no-video-available')) {
				$hideVideoButton.attr('data-original-title', t('spreed', 'Enable video (v)'))
					.addClass('local-video-disabled video-disabled icon-video-off')
					.removeClass('icon-video');
				$audioMuteButton.addClass('local-video-disabled');
				$screensharingButton.addClass('local-video-disabled');
			}

			var avatar = avatarContainer.find('.avatar');
			var guestName = localStorage.getItem("nick");
			if (OC.getCurrentUser().uid) {
				avatar.avatar(OC.getCurrentUser().uid, 128);
			} else {
				avatar.imageplaceholder('?', guestName, 128);
				avatar.css('background-color', '#b9b9b9');
				if (this.displayedGuestNameHint === false) {
					OC.Notification.showTemporary(t('spreed', 'Set your name in the chat window so other participants can identify you better.'));
					this.displayedGuestNameHint = true;
				}
			}

			avatarContainer.removeClass('hidden');
			avatarContainer.show();
			localVideo.hide();
		},
		// TODO
		disableVideo: function() {
			if (this.videoNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.pauseVideo();
			this.hideVideo();
			this.videoDisabled = true;
		},
		// TODO
		hasVideo: function() {
			$('#hideVideo').removeClass('no-video-available');
			this.enableVideoUI();
			this.videoNotFound = false;
		},
		// TODO
		hasNoVideo: function() {
			$('#hideVideo').removeClass('icon-video')
				.addClass('no-video-available icon-video-off')
				.attr('data-original-title', t('spreed', 'No Camera'));
			this.videoDisabled = true;
			this.videoNotFound = true;
		},
		// TODO
		disableScreensharingButton: function() {
			$('#screensharing-button').attr('data-original-title', t('spreed', 'Enable screensharing'))
					.addClass('screensharing-disabled icon-screen-off')
					.removeClass('icon-screen');
			$('#screensharing-menu').toggleClass('open', false);
		},
	});

})(OC, OCA, Marionette, Backbone, _, $);

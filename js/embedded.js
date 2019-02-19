/* global Marionette, Backbone, _, $ */

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
	var localMediaChannel = Backbone.Radio.channel('localMedia');

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

		/** property {String} selector */
		mainCallElementSelector: '#call-container',

		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,

		_registerPageEvents: function() {
			// Initialize button tooltips
			$('[data-toggle="tooltip"]').tooltip({trigger: 'hover'}).click(function() {
				$(this).tooltip('hide');
			});
		},

		/**
		 * @param {string} token
		 */
		_setRoomActive: function(token) {
			if (OC.getCurrentUser().uid) {
				this._rooms.forEach(function(room) {
					room.set('active', room.get('token') === token);
				});
			}
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.signaling.syncRooms()
				.then(function() {
					self.stopListening(self.activeRoom, 'change:participantFlags');

					if (OC.getCurrentUser().uid) {
						roomChannel.trigger('active', token);

						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								self.activeRoom = room;
							}
						});
					}
				});
		},

		initialize: function() {
			if (OC.getCurrentUser().uid) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			}

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				id: 'chatView'
			});

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this.stopReceivingMessages();
			});

			this._localVideoView = new OCA.Talk.Views.LocalVideoView({
				app: this,
				webrtc: OCA.SpreedMe.webrtc,
				sharedScreens: OCA.SpreedMe.sharedScreens,
			});

			this._mediaControlsView = this._localVideoView._mediaControlsView;
		},
		onStart: function() {
			this.signaling = OCA.Talk.Signaling.createConnection();
			this.connection = new OCA.Talk.Connection(this);

			this.signaling.on('joinCall', function() {
				// Disable video when joining a call in a room with more than 5
				// participants.
				var participants = this.activeRoom.get('participants');
				if (participants && Object.keys(participants).length > 5) {
					this.setVideoEnabled(false);
				}
			}.bind(this));

			$(window).unload(function () {
				this.connection.leaveCurrentRoom();
				this.signaling.disconnect();
			}.bind(this));

			this._registerPageEvents();
		},

		setupWebRTC: function() {
			if (!OCA.SpreedMe.webrtc) {
				OCA.SpreedMe.initWebRTC(this);
				this._mediaControlsView.setWebRtc(OCA.SpreedMe.webrtc);
			}

			if (!OCA.SpreedMe.webrtc.capabilities.support) {
				localMediaChannel.trigger('webRtcNotSupported');
			} else {
				localMediaChannel.trigger('waitingForPermissions');
			}

			OCA.SpreedMe.webrtc.startMedia(this.token);
		},
		startLocalMedia: function(configuration) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(configuration);
				this.callbackAfterMedia = null;
			}

			this.initAudioVideoSettings(configuration);

			localMediaChannel.trigger('startLocalMedia');
		},
		startWithoutLocalMedia: function(configuration) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(null);
				this.callbackAfterMedia = null;
			}

			this.initAudioVideoSettings(configuration);

			if (OCA.SpreedMe.webrtc.capabilities.support) {
				localMediaChannel.trigger('startWithoutLocalMedia');
			}
		},
		initAudioVideoSettings: function(configuration) {
			if (configuration.audio !== false) {
				this._mediaControlsView.setAudioAvailable(true);

				if (!this._mediaControlsView.audioEnabled) {
					this._mediaControlsView.setAudioEnabled(false);
				} else {
					this._mediaControlsView.setAudioEnabled(true);
				}
			} else {
				this._mediaControlsView.setAudioEnabled(false);
				this._mediaControlsView.setAudioAvailable(false);
			}

			if (configuration.video !== false) {
				this._mediaControlsView.setVideoAvailable(true);

				if (!this._mediaControlsView.videoEnabled) {
					this.setVideoEnabled(false);
				} else {
					this.setVideoEnabled(true);
				}
			} else {
				this.setVideoEnabled(false);
				this._mediaControlsView.setVideoAvailable(false);
			}
		},
		setVideoEnabled: function(videoEnabled) {
			if (!this._mediaControlsView.setVideoEnabled(videoEnabled)) {
				return;
			}

			var avatarContainer = this._mediaControlsView.$el.closest('.videoView').find('.avatar-container');
			var localVideo = this._mediaControlsView.$el.closest('.videoView').find('#localVideo');

			if (videoEnabled) {
				avatarContainer.addClass('hidden');
				localVideo.removeClass('hidden');

				return;
			}

			var userId = OC.getCurrentUser().uid;
			var guestName = localStorage.getItem("nick");
			this._localVideoView.setAvatar(userId, guestName);

			if (!userId && !this._displayedGuestNameHint) {
				OC.Notification.showTemporary(t('spreed', 'Set your name in the chat window so other participants can identify you better.'));
				this._displayedGuestNameHint = true;
			}

			avatarContainer.removeClass('hidden');
			localVideo.addClass('hidden');
		},
		// Called from webrtc.js
		disableScreensharingButton: function() {
			this._mediaControlsView.disableScreensharingButton();
		},
	});

})(OC, OCA, Marionette, Backbone, _, $);

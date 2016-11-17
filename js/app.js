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

(function(OCA, Marionette, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var roomChannel = Backbone.Radio.channel('rooms');

	var App = Marionette.Application.extend({
		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,
		/** @property {OCA.SpreedMe.Views.RoomListView} _roomsView  */
		_roomsView: null,
		_registerPageEvents: function() {
			$('#edit-roomname').select2({
				ajax: {
					url: OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees',
					dataType: 'json',
					quietMillis: 100,
					data: function (term) {
						return {
							format: 'json',
							search: term,
							perPage: 200,
							itemType: 'call'
						};
					},
					results: function (response) {
						// TODO improve error case
						if (response.ocs.data === undefined) {
							console.error('Failure happened', response);
							return;
						}

						var results = [];
						$.each(response.ocs.data.exact.users, function(id, user) {
							if (oc_current_user === user.value.shareWith) {
								return;
							}
							results.push({ id: user.value.shareWith, displayName: user.label, type: "user"});
						});
						$.each(response.ocs.data.exact.groups, function(id, group) {
							results.push({ id: group.value.shareWith, displayName: group.label + ' ' + t('spreed', '(group)'), type: "group"});
						});
						$.each(response.ocs.data.users, function(id, user) {
							if (oc_current_user === user.value.shareWith) {
								return;
							}
							results.push({ id: user.value.shareWith, displayName: user.label, type: "user"});
						});
						$.each(response.ocs.data.groups, function(id, group) {
							results.push({ id: group.value.shareWith, displayName: group.label + ' ' + t('spreed', '(group)'), type: "group"});
						});

						return {
							results: results,
							more: false
						};
					}
				},
				initSelection: function (element, callback) {
					console.log(element);
					callback({id: element.val()});
				},
				formatResult: function (element) {
					return '<span><div class="avatar" data-user="' + escapeHTML(element.id) + '" data-user-display-name="' + escapeHTML(element.displayName) + '"></div>' + escapeHTML(element.displayName) + '</span>';
				},
				formatSelection: function () {
					return '<span class="select2-default" style="padding-left: 0;">'+OC.L10N.translate('spreed', 'Choose person…')+'</span>';
				}
			});
			$('#edit-roomname').on("change", function(e) {
				if (e.added.type === "user") {
					OCA.SpreedMe.Rooms.createOneToOneVideoCall(e.val);
				} else if (e.added.type === "group") {
					OCA.SpreedMe.Rooms.createGroupVideoCall(e.val);
				}

				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});
			$('#edit-roomname').on("click", function() {
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#edit-roomname').on("select2-loaded", function() {
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#hideVideo').click(function() {
				if (OCA.SpreedMe.webrtc.webrtc.isVideoEnabled()) {
					OCA.SpreedMe.app.enableVideo();
				} else {
					OCA.SpreedMe.app.disableVideo();
				}
			});
			$('#mute').click(function() {
				if (OCA.SpreedMe.webrtc.webrtc.isAudioEnabled()) {
					OCA.SpreedMe.webrtc.mute();
					$(this).data('title', 'Enable audio')
						.addClass('audio-disabled icon-audio-off-white')
						.removeClass('icon-audio-white');
				} else {
					OCA.SpreedMe.webrtc.unmute();
					$(this).data('title', 'Mute audio')
						.removeClass('audio-disabled icon-audio-off-white')
						.addClass('icon-audio-white');
				}
			});

			$('#video-fullscreen').click(function() {
				var fullscreenElem = document.getElementById('app-content');

				if (!document.fullscreenElement && !document.mozFullScreenElement &&
					!document.webkitFullscreenElement && !document.msFullscreenElement) {
					if (fullscreenElem.requestFullscreen) {
						fullscreenElem.requestFullscreen();
					} else if (fullscreenElem.webkitRequestFullscreen) {
						fullscreenElem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
					} else if (fullscreenElem.mozRequestFullScreen) {
						fullscreenElem.mozRequestFullScreen();
					} else if (fullscreenElem.msRequestFullscreen) {
						fullscreenElem.msRequestFullscreen();
					}
				} else {
					if (document.exitFullscreen) {
						document.exitFullscreen();
					} else if (document.webkitExitFullscreen) {
						document.webkitExitFullscreen();
					} else if (document.mozCancelFullScreen) {
						document.mozCancelFullScreen();
					} else if (document.msExitFullscreen) {
						document.msExitFullscreen();
					}
				}
			});
		},
		_onRegisterHashChange: function() {
			// If page is opened already with a hash in the URL redirect to plain URL
			if (window.location.hash !== '') {
				window.location.replace(window.location.href.slice(0, -window.location.hash.length));
			}

			// If the hash changes a room gets joined
			$(window).on('hashchange', function() {
				var roomId = parseInt(window.location.hash.substring(1), 10);
				OCA.SpreedMe.Rooms.join(roomId);
			});
			if (window.location.hash.substring(1) === '') {
				OCA.SpreedMe.Rooms.showCamera();
			}
		},
		_showRoomList: function() {
			this._roomsView = new OCA.SpreedMe.Views.RoomListView({
				el: '#app-navigation ul',
				collection: this._rooms
			});
		},
		_pollForRoomChanges: function() {
			// Load the list of rooms all 10 seconds
			var self = this;
			setInterval(function() {
				self.syncRooms();
			}, 10000);
		},
		_startPing: function() {
			// Send a ping to the server all 5 seconds to ensure that the connection is
			// still alive.
			setInterval(function() {
				OCA.SpreedMe.Rooms.ping();
			}, 5000);
		},
		/**
		 * @param {int} roomId
		 */
		_setRoomActive: function(roomId) {
			this._rooms.forEach(function(room) {
				room.set('active', room.get('id') === roomId);
			});
		},
		syncRooms: function() {
			this._rooms.fetch();
		},
		syncAndSetActiveRoom: function(roomId) {
			this._rooms.fetch({
				success: function() {
					roomChannel.trigger('active', roomId);
				}
			});
		},
		initialize: function() {
			this._rooms = new OCA.SpreedMe.Models.RoomCollection();
			this.listenTo(roomChannel, 'active', this._setRoomActive);

			$(document).on('click', this.onDocumentClick);
		},
		onStart: function() {
			console.log('Starting spreed …');
			var self = this;

			OCA.SpreedMe.initWebRTC();
			OCA.SpreedMe.initRooms();
			OCA.SpreedMe.Rooms.leaveAllRooms();
			this._registerPageEvents();
			this._onRegisterHashChange();

			this._showRoomList();
			this._rooms.fetch({
				success: function() {
					$('#app-navigation').removeClass('icon-loading');
					self._roomsView.render();
				}
			});

			this._pollForRoomChanges();
			this._startPing();

			// disable by default and enable once we get a stream from the webcam
			this.disableVideo();
		},
		onDocumentClick: function(event) {
			var uiChannel = Backbone.Radio.channel('ui');

			uiChannel.trigger('document:click', event);
		},
		enableVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			OCA.SpreedMe.webrtc.resumeVideo();
			$hideVideoButton.data('title', 'Disable video')
				.removeClass('video-disabled icon-video-off-white')
				.addClass('icon-video-white');
			avatarContainer.hide();
			localVideo.show();
		},
		disableVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			OCA.SpreedMe.webrtc.pauseVideo();
			$hideVideoButton.data('title', 'Enable video')
				.addClass('video-disabled icon-video-off-white')
				.removeClass('icon-video-white');

			avatarContainer.find('.avatar').avatar(OC.currentUser, 128);
			avatarContainer.removeClass('hidden');
			avatarContainer.show();

			localVideo.hide();
		}
	});

	OCA.SpreedMe.App = App;
})(OCA, Marionette, Backbone);

$(window).unload(function () {
	OCA.SpreedMe.Rooms.leaveAllRooms();
});

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

	var App = Marionette.Application.extend({
		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,
		/** @property {OCA.SpreedMe.Views.RoomListView} _roomsView  */
		_roomsView: null,
		_registerPageEvents: function() {
			var self = this;

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
							itemType: 'folder',
							shareType: 0
						};
					},
					results: function (response) {
						// TODO improve error case
						if (response.ocs.data === undefined) {
							console.error('Failure happened', response);
							return;
						}

						var results = [];
						$.each(response.ocs.data.users, function(id, user) {
							results.push({ id: user.value.shareWith});
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
					return '<span><div class="avatar" data-user="' + escapeHTML(element.id) + '" data-user-display-name="' + escapeHTML(element.id) + '"></div>' + escapeHTML(element.id) + '</span>';
				},
				formatSelection: function (element) {
					return '<span><div class="avatar" data-user="' + escapeHTML(element.id) + '" data-user-display-name="' + escapeHTML(element.id) + '"></div>' + escapeHTML(element.id) + '</span>';
				}
			});
			$('#edit-roomname').on("change", function(e) {
				OCA.SpreedMe.Rooms.createOneToOneVideoCall(e.val);
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 28, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 28);
					}
				});
			});
			$('#edit-roomname').on("click", function() {
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 28, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 28);
					}
				});
			});

			$('#edit-roomname').on("select2-loaded", function() {
				$('body').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 28, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 28);
					}
				});
			});

			var videoHidden = false;
			$('#hideVideo').click(function() {
				if (videoHidden) {
					OCA.SpreedMe.webrtc.resumeVideo();
					$(this).data('title', 'Disable video').removeClass('video-disabled');
					videoHidden = false;
				} else {
					OCA.SpreedMe.webrtc.pauseVideo();
					$(this).data('title', 'Enable video').addClass('video-disabled');
					videoHidden = true;
				}
			});
			var audioMuted = false;
			$('#mute').click(function() {
				if (audioMuted) {
					OCA.SpreedMe.webrtc.unmute();
					$(this).data('title', 'Mute audio').removeClass('audio-disabled');
					audioMuted = false;
				} else {
					OCA.SpreedMe.webrtc.mute();
					$(this).data('title', 'Enable audio').addClass('audio-disabled');
					audioMuted = true;
				}
			});

			$('#video-more').click(function() {
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
				OCA.SpreedMe.Rooms.join(window.location.hash.substring(1));
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
		initialize: function() {
			var roomChannel = Backbone.Radio.channel('rooms');

			this._rooms = new OCA.SpreedMe.Models.RoomCollection();
			this.listenTo(roomChannel, 'active', this._setRoomActive);
		},
		onStart: function() {
			console.log('Starting spreed …');
			var self = this;

			OCA.SpreedMe.initWebRTC();
			OCA.SpreedMe.initRooms();
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
		}
	});

	OCA.SpreedMe.App = App;
})(OCA, Marionette, Backbone);

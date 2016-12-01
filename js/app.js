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
		/** @property {boolean} videoWasEnabledAtLeastOnce  */
		videoWasEnabledAtLeastOnce: false,
		audioDisabled: localStorage.getItem("audioDisabled"),
		videoDisabled: localStorage.getItem("videoDisabled"),
		_searchTerm: '',
		guestNick: null,
		_registerPageEvents: function() {
			$('#edit-roomname').select2({
				ajax: {
					url: OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees',
					dataType: 'json',
					quietMillis: 100,
					data: function (term) {
						OCA.SpreedMe.app._searchTerm = term;
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

						//Add custom entry to create a new empty room
						if (OCA.SpreedMe.app._searchTerm === '') {
							results.unshift({ id: "create-public-room", displayName: t('spreed', 'New public room'), type: "createPublicRoom"});
						} else {
							results.push({ id: "create-public-room", displayName: t('spreed', 'New public room'), type: "createPublicRoom"});
						}

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
					if (element.type === "createPublicRoom") {
						return '<span><div class="avatar icon-add"></div>' + escapeHTML(element.displayName) + '</span>';
					}

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

				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#edit-roomname').on("click", function() {
				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#edit-roomname').on("select2-selecting", function(e) {
				if (e.object.type === "createPublicRoom") {
					OCA.SpreedMe.Rooms.createPublicVideoCall();
				}
			});

			$('#edit-roomname').on("select2-loaded", function() {
				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#hideVideo').click(function() {
				if(!OCA.SpreedMe.app.videoWasEnabledAtLeastOnce) {
					// don't allow clicking the video toggle
					// when no video ever was streamed (that
					// means that permission wasn't granted
					// yet or there is no video available at
					// all)
					console.log('video can not be enabled - there was no stream available before');
					return;
				}
				if ($(this).hasClass('video-disabled')) {
					OCA.SpreedMe.app.enableVideo();
					localStorage.removeItem("videoDisabled");
				} else {
					OCA.SpreedMe.app.disableVideo();
					localStorage.setItem("videoDisabled", true);
				}
			});
			$('#mute').click(function() {
				if (OCA.SpreedMe.webrtc.webrtc.isAudioEnabled()) {
					OCA.SpreedMe.app.disableAudio();
					localStorage.setItem("audioDisabled", true);
				} else {
					OCA.SpreedMe.app.enableAudio();
					localStorage.removeItem("audioDisabled");
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

			$("#guestName").on('click', function() {
				$('#guestName').addClass('hidden');
				$("#guestNameInput").removeClass('hidden');
				$("#guestNameConfirm").removeClass('hidden');
				$("#guestNameInput").focus();
			});

			$('#guestNameConfirm').click(function () {
				OCA.SpreedMe.app.changeGuestName();
				$('#guestName').toggleClass('hidden');
				$("#guestNameInput").toggleClass('hidden');
				$("#guestNameConfirm").toggleClass('hidden');
			});

			$("#guestNameInput").keyup(function (e) {
				var hide = false;

				if (e.keyCode === 13) { // send new gues name on "enter"
					hide = true;
					OCA.SpreedMe.app.changeGuestName();
				} else if (e.keyCode === 27) { // hide input filed again in ESC
					hide = true;
				}

				if (hide) {
					$('#guestName').toggleClass('hidden');
					$("#guestNameInput").toggleClass('hidden');
					$("#guestNameConfirm").toggleClass('hidden');
				}
			});
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
			if (oc_current_user) {
				this._rooms.forEach(function(room) {
					room.set('active', room.get('id') === roomId);
				});
			}
		},
		syncRooms: function() {
			if (oc_current_user) {
				this._rooms.fetch();
			}
		},
		syncAndSetActiveRoom: function(roomId) {
			var self = this;
			if (oc_current_user) {
				this._rooms.fetch({
					success: function() {
						roomChannel.trigger('active', roomId);
					}
				});
			} else {
				$.ajax({
					url: OC.generateUrl('/apps/spreed/api/room/') + roomId,
					type: 'GET',
					success: function(data) {
						console.log("data", data);
						self.showPublicRoomMessage(data.participants);
					}
				});
			}
		},
		showPublicRoomMessage: function(participants) {
			var message, messageAdditional;

			//Remove previous icon or avatar
			var emptyContentIcon = document.getElementById("emptyContentIcon");
			emptyContentIcon.removeAttribute("class");
			emptyContentIcon.innerHTML = "";

			if (Object.keys(participants).length == 1) {
				var waitingParticipantId, waitingParticipantName;

				$.each(participants, function(participantId, participantName) {
					waitingParticipantId = participantId;
					waitingParticipantName = participantName;
				});

				// Avatar for username
				var avatar = document.createElement('div');
				avatar.className = 'avatar room-avatar';

				$('#emptyContentIcon').append(avatar);

				$('#emptyContentIcon').find('.avatar').each(function () {
					if (waitingParticipantName && (waitingParticipantId !== waitingParticipantName)) {
						$(this).avatar(waitingParticipantId, 128, undefined, false, undefined, waitingParticipantName);
					} else {
						$(this).avatar(waitingParticipantId, 128);
					}
				});

				message = t('spreed', 'Waiting for {participantName} to join the room …', {participantName: waitingParticipantName});
				messageAdditional = '';
			} else {
				message = t('spreed', 'Waiting for others to join the room …');
				messageAdditional = '';
				$('#emptyContentIcon').addClass('icon-contacts-dark');
			}

			$('#emptycontent h2').text(message);
			$('#emptycontent p').text(messageAdditional);
		},
		initialize: function() {
			if (oc_current_user) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			}

			$(document).on('click', this.onDocumentClick);
		},
		onStart: function() {
			console.log('Starting spreed …');
			var self = this;

			if (!oc_current_user) {
				this.initGuestName();
			}

			OCA.SpreedMe.initWebRTC();

			if (oc_current_user) {
				OCA.SpreedMe.initRooms();
				OCA.SpreedMe.Rooms.leaveAllRooms();
			}

			this._registerPageEvents();
			this.initShareRoomClipboard();
			var roomId = parseInt($('#app').attr('data-roomId'), 10);
			if (roomId) {
				OCA.SpreedMe.Rooms.join(roomId);
			}
			OCA.SpreedMe.Rooms.showCamera();

			if (oc_current_user) {
				this._showRoomList();
				this._rooms.fetch({
					success: function() {
						$('#app-navigation').removeClass('icon-loading');
						self._roomsView.render();
					}
				});

				this._pollForRoomChanges();
			}

			this._startPing();

			//Show avatar until we receive local video stream and then decide if show video or not.
			this.hideVideo();
		},
		onDocumentClick: function(event) {
			var uiChannel = Backbone.Radio.channel('ui');

			uiChannel.trigger('document:click', event);
		},
		initAudioVideoSettings: function() {
			if (OCA.SpreedMe.app.audioDisabled) {
				OCA.SpreedMe.app.disableAudio();
			}

			if (OCA.SpreedMe.app.videoDisabled) {
				OCA.SpreedMe.app.disableVideo();
			}
		},
		enableAudio: function() {
			OCA.SpreedMe.webrtc.unmute();
			$('#mute').data('title', 'Mute audio')
				.removeClass('audio-disabled icon-audio-off-white')
				.addClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = false;
		},
		disableAudio: function() {
			OCA.SpreedMe.webrtc.mute();
			$('#mute').data('title', 'Enable audio')
				.addClass('audio-disabled icon-audio-off-white')
				.removeClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = true;
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

			OCA.SpreedMe.app.videoDisabled = false;
		},
		hideVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			$hideVideoButton.data('title', 'Enable video')
				.addClass('video-disabled icon-video-off-white')
				.removeClass('icon-video-white');

			avatarContainer.find('.avatar').avatar(OC.currentUser, 128);
			avatarContainer.removeClass('hidden');
			avatarContainer.show();
			localVideo.hide();
		},
		disableVideo: function() {
			OCA.SpreedMe.webrtc.pauseVideo();
			OCA.SpreedMe.app.hideVideo();
			OCA.SpreedMe.app.videoDisabled = true;
		},
		initGuestName: function() {
			var nick = localStorage.getItem("nick");

			if (nick) {
				$('#guestName').text(nick);
				$('#guestNameInput').val(nick);
				OCA.SpreedMe.app.guestNick = nick;
			}
		},
		changeGuestName: function() {
			var guestName = $.trim($('#guestNameInput').val());
			var lastSavedNick = localStorage.getItem("nick");

			if (guestName !== lastSavedNick) {
				if (guestName.length > 0 && guestName.length <= 20) {
					$('#guestName').text(guestName);
					localStorage.setItem("nick", guestName);
					OCA.SpreedMe.webrtc.sendDirectlyToAll('nickChanged', guestName);
				} else if (lastSavedNick) {
					$('#guestName').text(t('spreed', 'Guest'));
					localStorage.removeItem("nick");
					OCA.SpreedMe.webrtc.sendDirectlyToAll('nickChanged', t('spreed', 'Guest'));
				}
			}

			$('#guestNameInput').val(guestName);
		},
		initShareRoomClipboard: function () {
			$('body').find('.shareRoomClipboard').tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('spreed', 'Copy')
			});

			var clipboard = new Clipboard('.shareRoomClipboard');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('spreed', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
			clipboard.on('error', function (e) {
				var $input = $(e.trigger);
				var actionMsg = '';
				if (/iPhone|iPad/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Not supported!');
				} else if (/Mac/i.test(navigator.userAgent)) {
					actionMsg = t('core', 'Press ⌘-C to copy.');
				} else {
					actionMsg = t('core', 'Press Ctrl-C to copy.');
				}

				$input.tooltip('hide')
					.attr('data-original-title', actionMsg)
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function () {
					$input.tooltip('hide')
						.attr('data-original-title', t('spreed', 'Copy'))
						.tooltip('fixTitle');
				}, 3000);
			});
		}
	});

	OCA.SpreedMe.App = App;
})(OCA, Marionette, Backbone);

$(window).unload(function () {
	OCA.SpreedMe.Rooms.leaveAllRooms();
});

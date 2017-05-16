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

(function(OCA, Marionette, Backbone, _) {
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
							results.unshift({ id: "create-public-room", displayName: t('spreed', 'New public call'), type: "createPublicRoom"});
						} else {
							results.push({ id: "create-public-room", displayName: t('spreed', 'New public call'), type: "createPublicRoom"});
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

			// Initialize button tooltips
			$('[data-toggle="tooltip"]').tooltip({trigger: 'hover'}).click(function() {
				$(this).tooltip('hide');
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
					$(this).attr('data-original-title', 'Exit fullscreen');
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
					$(this).attr('data-original-title', 'Fullscreen');
				}
			});

			var screensharingStopped = function() {
				console.log("Screensharing now stopped");
				$('#screensharing-button').attr('data-original-title', 'Enable screensharing')
					.addClass('screensharing-disabled icon-screen-off-white')
					.removeClass('icon-screen-white');
				$('#screensharing-menu').toggleClass('open', false);
			};

			OCA.SpreedMe.webrtc.on('localScreenStopped', function() {
				screensharingStopped();
			});

			$('#screensharing-button').click(function() {
				var webrtc = OCA.SpreedMe.webrtc;
				if (!webrtc.capabilities.supportScreenSharing) {
					if (window.location.protocol === 'https:') {
						OC.Notification.showTemporary(t('spreed', 'Screensharing is not supported by your browser.'));
					} else {
						OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
					}
					return;
				}

				if (webrtc.getLocalScreen()) {
					$('#screensharing-menu').toggleClass('open');
				} else {
					var screensharingButton = $(this);
					screensharingButton.prop('disabled', true);
					webrtc.shareScreen(function(err) {
						screensharingButton.prop('disabled', false);
						if (!err) {
							$('#screensharing-button').attr('data-original-title', 'Screensharing options')
								.removeClass('screensharing-disabled icon-screen-off-white')
								.addClass('icon-screen-white');
							return;
						}

						switch (err.name) {
							case "HTTPS_REQUIRED":
								OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
								break;
							case "PERMISSION_DENIED":
							case "NotAllowedError":
							case "CEF_GETSCREENMEDIA_CANCELED":  // Experimental, may go away in the future.
								break;
							case "FF52_REQUIRED":
								OC.Notification.showTemporary(t('spreed', 'Sharing your screen only works with Firefox version 52 or newer.'));
								break;
							case "EXTENSION_UNAVAILABLE":
								var  extensionURL = null;
								if (!!window.chrome && !!window.chrome.webstore) {// Chrome
									extensionURL = 'https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol';
								}

								if (extensionURL) {
									var text = t('spreed', 'Screensharing extension is required to share your screen.');
									var element = $('<a>').attr('href', extensionURL).attr('target','_blank').text(text);

									OC.Notification.showTemporary(element, {isHTML: true});
								} else {
									OC.Notification.showTemporary(t('spreed', 'Please use a different browser like Firefox or Chrome to share your screen.'));
								}
								break;
							default:
								OC.Notification.showTemporary(t('spreed', 'An error occurred while starting screensharing.'));
								console.log("Could not start screensharing", err);
								break;
						}
					});
				}
			});

			$("#show-screen-button").on('click', function() {
				var currentUser = OCA.SpreedMe.webrtc.connection.getSessionid();
				OCA.SpreedMe.sharedScreens.switchScreenToId(currentUser);

				$('#screensharing-menu').toggleClass('open', false);
			});

			$("#stop-screen-button").on('click', function() {
				OCA.SpreedMe.webrtc.stopScreenShare();
				screensharingStopped();
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
		 * @param {string} token
		 */
		_setRoomActive: function(token) {
			if (oc_current_user) {
				this._rooms.forEach(function(room) {
					room.set('active', room.get('token') === token);
				});
			}
		},
		syncRooms: function() {
			if (oc_current_user) {
				this._rooms.fetch();
			}
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			if (oc_current_user) {
				this._rooms.fetch({
					success: function() {
						roomChannel.trigger('active', token);
						// Disable video when entering a room with more than 5 participants.
						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								if (Object.keys(room.get('participants')).length > 5) {
									self.disableVideo();
								}
								self.setPageTitle(room.get('displayName'));
							}
						});
					}
				});
			} else {
				$.ajax({
					url: OC.generateUrl('/apps/spreed/api/room/') + token,
					type: 'GET',
					success: function(data) {
						self.setRoomMessageForGuest(data.participants);
						self.setPageTitle(data.displayName);
						if (Object.keys(data.participants).length > 5) {
							self.disableVideo();
						}
					}
				});
			}
		},
		setPageTitle: function(title){
			if (title) {
				title += ' - ';
			} else {
				title = '';
			}
			title += t('spreed', 'Video calls');
			title += ' - ' + oc_defaults.title;
			window.document.title = title;
		},
		setEmptyContentMessage: function(icon, message, messageAdditional) {
			//Remove previous icon, avatar or link from emptycontent
			var emptyContentIcon = document.getElementById('emptycontent-icon');
			emptyContentIcon.removeAttribute('class');
			emptyContentIcon.innerHTML = '';
			$('#shareRoomInput').addClass('hidden');
			$('#shareRoomClipboardButton').addClass('hidden');

			$('#emptycontent-icon').addClass(icon);
			$('#emptycontent h2').text(message);
			if (messageAdditional) {
				$('#emptycontent p').text(messageAdditional);
			} else {
				$('#emptycontent p').text('');
			}
		},
		setRoomMessageForGuest: function(participants) {
			var message, messageAdditional;

			//Remove previous icon or avatar
			var emptyContentIcon = document.getElementById('emptycontent-icon');
			emptyContentIcon.removeAttribute('class');
			emptyContentIcon.innerHTML = '';

			if (Object.keys(participants).length === 1) {
				var waitingParticipantId, waitingParticipantName;

				$.each(participants, function(participantId, participantName) {
					waitingParticipantId = participantId;
					waitingParticipantName = participantName;
				});

				// Avatar for username
				var avatar = document.createElement('div');
				avatar.className = 'avatar room-avatar';

				$('#emptycontent-icon').append(avatar);

				$('#emptycontent-icon').find('.avatar').each(function () {
					if (waitingParticipantName && (waitingParticipantId !== waitingParticipantName)) {
						$(this).avatar(waitingParticipantId, 128, undefined, false, undefined, waitingParticipantName);
					} else {
						$(this).avatar(waitingParticipantId, 128);
					}
				});

				message = t('spreed', 'Waiting for {participantName} to join the call …', {participantName: waitingParticipantName});
				messageAdditional = '';
			} else {
				message = t('spreed', 'Waiting for others to join the call …');
				messageAdditional = '';
				$('#emptycontent-icon').addClass('icon-contacts-dark');
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
			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));
		},
		onStart: function() {
			this.setEmptyContentMessage(
				'icon-video-off',
				t('spreed', 'Waiting for camera and microphone permissions'),
				t('spreed', 'Please, give your browser access to use your camera and microphone in order to use this app.')
			);

			if (!oc_current_user) {
				this.initGuestName();
			}

			OCA.SpreedMe.initWebRTC();
		},
		startSpreed: function(configuration) {
			console.log('Starting spreed …');
			var self = this;

			this.setEmptyContentMessage(
				'icon-video',
				t('spreed', 'Looking great today! :)'),
				t('spreed', 'Time to call your friends')
			);

			if (oc_current_user) {
				OCA.SpreedMe.initRooms();
				OCA.SpreedMe.Rooms.leaveAllRooms();
			}

			this._registerPageEvents();
			this.initShareRoomClipboard();

			var token = $('#app').attr('data-token');
			if (token) {
				OCA.SpreedMe.Rooms.join(token);
			}
			OCA.SpreedMe.Rooms.showCamera();

			if (oc_current_user) {
				this._showRoomList();
				this._rooms.fetch({
					success: function(data) {
						$('#app-navigation').removeClass('icon-loading');
						self._roomsView.render();

						if (data.length === 0) {
							$('#edit-roomname').select2('open');
						}
					}
				});

				this._pollForRoomChanges();
			}

			this._startPing();

			this.initAudioVideoSettings(configuration);
		},
		_onPopState: function(params) {
			if (!_.isUndefined(params.token)) {
				OCA.SpreedMe.Rooms.join(params.token);
			}
		},
		onDocumentClick: function(event) {
			var uiChannel = Backbone.Radio.channel('ui');

			uiChannel.trigger('document:click', event);
		},
		initAudioVideoSettings: function(configuration) {
			if (OCA.SpreedMe.app.audioDisabled) {
				OCA.SpreedMe.app.disableAudio();
			}

			if (configuration.video !== false) {
				if (OCA.SpreedMe.app.videoDisabled) {
					OCA.SpreedMe.app.disableVideo();
				}
			} else {
				OCA.SpreedMe.app.videoWasEnabledAtLeastOnce = false;
				OCA.SpreedMe.app.disableVideo();
			}
		},
		enableAudio: function() {
			OCA.SpreedMe.webrtc.unmute();
			$('#mute').attr('data-original-title', 'Mute audio')
				.removeClass('audio-disabled icon-audio-off-white')
				.addClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = false;
		},
		disableAudio: function() {
			OCA.SpreedMe.webrtc.mute();
			$('#mute').attr('data-original-title', 'Enable audio')
				.addClass('audio-disabled icon-audio-off-white')
				.removeClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = true;
		},
		enableVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			OCA.SpreedMe.webrtc.resumeVideo();
			$hideVideoButton.attr('data-original-title', 'Disable video')
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

			$hideVideoButton.attr('data-original-title', 'Enable video')
				.addClass('video-disabled icon-video-off-white')
				.removeClass('icon-video-white');

			var avatar = avatarContainer.find('.avatar');
			var guestName = localStorage.getItem("nick");
			if (oc_current_user) {
				avatar.avatar(OC.currentUser, 128);
			} else if (guestName) {
				avatar.imageplaceholder(guestName, undefined, 128);
			} else {
				avatar.avatar(null, 128);
				OC.Notification.showTemporary(t('spreed', 'You can set your name on the top right of this page so other participants can identify you better.'));
			}

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
					OCA.SpreedMe.webrtc.sendDirectlyToAll('nickChanged', '');
				}
			}

			$('#guestNameInput').val(guestName);

			var avatar = $('#localVideoContainer').find('.avatar');
			var savedGuestName = localStorage.getItem("nick");
			if (savedGuestName) {
				avatar.imageplaceholder(savedGuestName, undefined, 128);
			} else {
				avatar.avatar(null, 128);
			}
		},
		initShareRoomClipboard: function () {
			$('body').find('.shareRoomClipboard').tooltip({
				placement: 'bottom',
				trigger: 'hover',
				title: t('core', 'Copy')
			});

			var clipboard = new Clipboard('.shareRoomClipboard');
			clipboard.on('success', function(e) {
				var $input = $(e.trigger);
				$input.tooltip('hide')
					.attr('data-original-title', t('core', 'Copied!'))
					.tooltip('fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
				_.delay(function() {
					$input.tooltip('hide')
						.attr('data-original-title', t('core', 'Copy'))
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
})(OCA, Marionette, Backbone, _);

$(window).unload(function () {
	OCA.SpreedMe.Rooms.leaveAllRooms();
});

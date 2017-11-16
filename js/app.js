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
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USERSELFJOINED: 5,

		/** @property {OCA.SpreedMe.Models.Room} activeRoom  */
		activeRoom: null,

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
		videoDisabled: localStorage.getItem("videoDisabled"),
		_searchTerm: '',
		guestNick: null,
		_registerPageEvents: function() {
			$('#select-participants').select2({
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

			$('#select-participants').on("click", function() {
				$('.select2-drop').find('.avatar').each(function () {
					var element = $(this);
					if (element.data('user-display-name')) {
						element.avatar(element.data('user'), 32, undefined, false, undefined, element.data('user-display-name'));
					} else {
						element.avatar(element.data('user'), 32);
					}
				});
			});

			$('#select-participants').on("select2-selecting", function(e) {
				switch (e.object.type) {
					case "user":
						OCA.SpreedMe.Calls.createOneToOneVideoCall(e.val);
						break;
					case "group":
						OCA.SpreedMe.Calls.createGroupVideoCall(e.val);
						break;
					case "createPublicRoom":
						OCA.SpreedMe.Calls.createPublicVideoCall();
						break;
					default:
						console.log("Unknown type", e.object.type);
						break;
				}
			});

			$('#select-participants').on("select2-loaded", function() {
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
					$(this).attr('data-original-title', t('spreed', 'Exit fullscreen'));
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
					$(this).attr('data-original-title', t('spreed', 'Fullscreen'));
				}
			});

			var screensharingStopped = function() {
				console.log("Screensharing now stopped");
				$('#screensharing-button').attr('data-original-title', t('spreed', 'Enable screensharing'))
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
							$('#screensharing-button').attr('data-original-title', t('spreed', 'Screensharing options'))
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
		},
		_showRoomList: function() {
			this._roomsView = new OCA.SpreedMe.Views.RoomListView({
				el: '#app-navigation ul',
				collection: this._rooms
			});
		},
		_showParticipantList: function() {
			this._participants = new OCA.SpreedMe.Models.ParticipantCollection();
			this._participantsView = new OCA.SpreedMe.Views.ParticipantView({
				room: this.activeRoom,
				collection: this._participants
			});

			this._participantsView.listenTo(this._rooms, 'change:active', function(model, active) {
				if (active) {
					this.setRoom(model);
				}
			});

			this._sidebarView.addTab('participants', { label: t('spreed', 'Participants') }, this._participantsView);
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
		addParticipantToRoom: function(token, participant) {
			$.post(
				OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants',
				{
					newParticipant: participant
				}
			).done(function() {
				this.syncRooms();
			}.bind(this));
		},
		syncRooms: function() {
			return this.signaling.syncRooms();
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.syncRooms()
				.then(function() {
					if (oc_current_user) {
						roomChannel.trigger('active', token);

						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								self.activeRoom = room;
							}
						});
					} else {
						// The public page supports only a single room, so the
						// active room is already the room for the given token.

						self.setRoomMessageForGuest(self.activeRoom.get('participants'));
					}
					// Disable video when entering a room with more than 5 participants.
					if (Object.keys(self.activeRoom.get('participants')).length > 5) {
						self.disableVideo();
					}

					self.setPageTitle(self.activeRoom.get('displayName'));

					self.updateSidebarWithActiveRoom();
				});
		},
		updateSidebarWithActiveRoom: function() {
			this._sidebarView.enable();

			var callInfoView = new OCA.SpreedMe.Views.CallInfoView({
				model: this.activeRoom,
				guestNameModel: this._localStorageModel
			});
			this._sidebarView.setCallInfoView(callInfoView);

			this._messageCollection.setRoomToken(this.activeRoom.get('token'));
			this._messageCollection.receiveMessages();
		},
		setPageTitle: function(title){
			if (title) {
				title += ' - ';
			} else {
				title = '';
			}
			title += t('spreed', 'Talk');
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

				$.each(participants, function(id, participant) {
					waitingParticipantId = id;
					waitingParticipantName = participant.name;
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
			this._sidebarView = new OCA.SpreedMe.Views.SidebarView();
			$('#app-content').append(this._sidebarView.$el);

			if (oc_current_user) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			}

			this._sidebarView.listenTo(roomChannel, 'leaveCurrentCall', function() {
				this.disable();
			});

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				id: 'commentsTabView',
				className: 'chat tab'
			});

			this._sidebarView.addTab('chat', { label: t('spreed', 'Chat') }, this._chatView);

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentCall', function() {
				this.stopReceivingMessages();
			});

			$(document).on('click', this.onDocumentClick);
			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));
		},
		onStart: function() {
			this.setEmptyContentMessage(
				'icon-video-off',
				t('spreed', 'Waiting for camera and microphone permissions'),
				t('spreed', 'Please, give your browser access to use your camera and microphone in order to use this app.')
			);

			OCA.SpreedMe.initWebRTC();

			if (!oc_current_user) {
				this.initGuestName();
			}
		},
		startSpreed: function(configuration, signaling) {
			console.log('Starting spreed …');
			var self = this;
			this.signaling = signaling;

			$(window).unload(function () {
				OCA.SpreedMe.Calls.leaveAllCalls();
				signaling.disconnect();
			});

			this.setEmptyContentMessage(
				'icon-video',
				t('spreed', 'Looking great today! :)'),
				t('spreed', 'Time to call your friends')
			);

			OCA.SpreedMe.initCalls(signaling);

			this._registerPageEvents();
			this.initShareRoomClipboard();

			OCA.SpreedMe.Calls.showCamera();

			var token = $('#app').attr('data-token');

			if (oc_current_user) {
				this._showRoomList();
				this.signaling.setRoomCollection(this._rooms)
					.then(function(data) {
						$('#app-navigation').removeClass('icon-loading');
						self._roomsView.render();

						if (data.length === 0) {
							$('#select-participants').select2('open');
						}
					});

				this._showParticipantList();
			} else {
				// The token is always defined in the public page.
				this.activeRoom = new OCA.SpreedMe.Models.Room({ token: token });
				this.signaling.setRoom(this.activeRoom);
			}

			this.initAudioVideoSettings(configuration);

			if (token) {
				if (OCA.SpreedMe.webrtc.sessionReady) {
					OCA.SpreedMe.Calls.joinRoom(token);
				} else {
					OCA.SpreedMe.webrtc.once('connectionReady', function() {
						OCA.SpreedMe.Calls.joinRoom(token);
					});
				}
			}
		},
		_onPopState: function(params) {
			if (!_.isUndefined(params.token)) {
				OCA.SpreedMe.Calls.joinRoom(params.token);
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
			$('#mute').attr('data-original-title', t('spreed', 'Mute audio'))
				.removeClass('audio-disabled icon-audio-off-white')
				.addClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = false;
		},
		disableAudio: function() {
			OCA.SpreedMe.webrtc.mute();
			$('#mute').attr('data-original-title', t('spreed', 'Enable audio'))
				.addClass('audio-disabled icon-audio-off-white')
				.removeClass('icon-audio-white');

			OCA.SpreedMe.app.audioDisabled = true;
		},
		enableVideo: function() {
			var $hideVideoButton = $('#hideVideo');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			OCA.SpreedMe.webrtc.resumeVideo();
			$hideVideoButton.attr('data-original-title', t('spreed', 'Disable video'))
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

			$hideVideoButton.attr('data-original-title', t('spreed', 'Enable video'))
				.addClass('video-disabled icon-video-off-white')
				.removeClass('icon-video-white');

			var avatar = avatarContainer.find('.avatar');
			var guestName = localStorage.getItem("nick");
			if (oc_current_user) {
				avatar.avatar(OC.currentUser, 128);
			} else if (guestName) {
				avatar.imageplaceholder(guestName, undefined, 128);
			} else if (OCA.SpreedMe.app.displayedGuestNameHint === false) {
				avatar.imageplaceholder('?', undefined, 128);
				avatar.css('background-color', '#b9b9b9');
				OC.Notification.showTemporary(t('spreed', 'You can set your name on the right sidebar so other participants can identify you better.'));
				OCA.SpreedMe.app.displayedGuestNameHint = true;
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
			this._localStorageModel = new OCA.SpreedMe.Models.LocalStorageModel({ nick: '' });
			this._localStorageModel.on('change:nick', function(model, value) {
				var avatar = $('#localVideoContainer').find('.avatar');

				if (value) {
					avatar.imageplaceholder(value, undefined, 128);
				} else {
					avatar.imageplaceholder('?', undefined, 128);
					avatar.css('background-color', '#b9b9b9');
				}

				OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'nickChanged', value);
			});

			this._localStorageModel.fetch();

			var nick = this._localStorageModel.get('nick');

			if (nick) {
				OCA.SpreedMe.app.guestNick = nick;
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

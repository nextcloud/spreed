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

	OCA.Talk.Application = Marionette.Application.extend({
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USERSELFJOINED: 5,

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
						this._searchTerm = term;
						return {
							format: 'json',
							search: term,
							perPage: 20,
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

						//Add custom entry to create a new empty group or public room
						if (OCA.SpreedMe.app._searchTerm === '') {
							results.unshift({ id: "create-public-room", displayName: t('spreed', 'New public call'), type: "createPublicRoom"});
							results.unshift({ id: "create-group-room", displayName: t('spreed', 'New group call'), type: "createGroupRoom"});
						} else {
							results.push({ id: "create-group-room", displayName: t('spreed', 'New group call'), type: "createGroupRoom"});
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
					if ((element.type === "createGroupRoom") || (element.type === "createPublicRoom")) {
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
						this.connection.createOneToOneVideoCall(e.val);
						break;
					case "group":
						this.connection.createGroupVideoCall(e.val, "");
						break;
					case "createPublicRoom":
						this.connection.createPublicVideoCall(OCA.SpreedMe.app._searchTerm);
						break;
					case "createGroupRoom":
						this.connection.createGroupVideoCall("", OCA.SpreedMe.app._searchTerm);
						break;
					default:
						console.log("Unknown type", e.object.type);
						break;
				}
			}.bind(this));

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
					.addClass('screensharing-disabled icon-screen-off')
					.removeClass('icon-screen');
				$('#screensharing-menu').toggleClass('open', false);
			};

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
								.removeClass('screensharing-disabled icon-screen-off')
								.addClass('icon-screen');
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

			$(document).keyup(this._onKeyUp.bind(this));
		},

		_onKeyUp: function(event) {
			// Define which objects to check for the event properties.
			var key = event.which;

			// Trigger the event only if no input or textarea is focused
			// and the CTRL key is not pressed
			if ($('input:focus').length === 0 &&
				$('textarea:focus').length === 0 &&
				!event.ctrlKey) {

				// Actual shortcut handling
				switch (key) {
					case 86: // 'v'
						event.preventDefault();
						if (this.videoDisabled) {
							this.enableVideo();
						} else {
							this.disableVideo();
						}
						break;
					case 65: // 'a'
						event.preventDefault();
						if (this.audioDisabled) {
							this.enableAudio();
						} else {
							this.disableAudio();
						}
						break;
					case 67: // 'c'
						event.preventDefault();
						this._sidebarView.selectTab('chat');
						break;
					case 80: // 'p'
						event.preventDefault();
						this._sidebarView.selectTab('participants');
						break;
				}
			}
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
				collection: this._participants,
				id: 'participantsTabView'
			});

			this._participantsView.listenTo(this._rooms, 'change:active', function(model, active) {
				if (active) {
					this.setRoom(model);
				}
			});

			this._sidebarView.addTab('participants', { label: t('spreed', 'Participants'), icon: 'icon-contacts-dark' }, this._participantsView);
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
				this.signaling.syncRooms();
			}.bind(this));
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.signaling.syncRooms()
				.then(function() {
					self.stopListening(self.activeRoom, 'change:participantInCall');

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

					self.updateChatViewPlacement();
					self.listenTo(self.activeRoom, 'change:participantInCall', self.updateChatViewPlacement);

					self.updateSidebarWithActiveRoom();
				});
		},
		updateChatViewPlacement: function() {
			if (!this.activeRoom) {
				// This should never happen, but just in case
				return;
			}

			if (this.activeRoom.get('participantInCall') && this._chatViewInMainView === true) {
				this._chatView.$el.detach();
				this._sidebarView.addTab('chat', { label: t('spreed', 'Chat'), icon: 'icon-comment', priority: 100 }, this._chatView);
				this._sidebarView.selectTab('chat');
				this._chatView.setTooltipContainer(this._chatView.$el);
				this._chatViewInMainView = false;
			} else if (!this.activeRoom.get('participantInCall') && !this._chatViewInMainView) {
				this._sidebarView.removeTab('chat');
				this._chatView.$el.prependTo('#app-content-wrapper');
				this._chatView.setTooltipContainer($('#app'));
				this._chatViewInMainView = true;
			}
		},
		updateSidebarWithActiveRoom: function() {
			this._sidebarView.enable();

			// The sidebar has a width of 27% of the window width and a minimum
			// width of 300px. Therefore, when the window is 1111px wide or
			// narrower the sidebar will always be 300px wide, and when that
			// happens it will overlap with the content area (the narrower the
			// window the larger the overlap). Due to this the sidebar is opened
			// automatically only if it will not overlap with the content area.
			if ($(window).width() > 1111) {
				this._sidebarView.open();
			}

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

			if (OC.getCurrentUser().uid) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			} else {
				this.initGuestName();
			}

			this._sidebarView.listenTo(roomChannel, 'leaveCurrentCall', function() {
				this.disable();
			});

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				id: 'commentsTabView',
				oldestOnTopLayout: true,
				guestNameModel: this._localStorageModel
			});

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentCall', function() {
				this.stopReceivingMessages();
			});

			$(document).on('click', this.onDocumentClick);
			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));
		},
		onStart: function() {
			this.signaling = OCA.Talk.Signaling.createConnection();
			this.connection = new OCA.Talk.Connection(this);
			this.token = $('#app').attr('data-token');

			$(window).unload(function () {
				this.connection.leaveAllCalls();
				this.signaling.disconnect();
			}.bind(this));

			if (OC.getCurrentUser().uid) {
				this._showRoomList();
				this.signaling.setRoomCollection(this._rooms)
					.then(function(data) {
						$('#app-navigation').removeClass('icon-loading');
						this._roomsView.render();

						if (data.length === 0) {
							$('#select-participants').select2('open');
						}
					}.bind(this));

				this._showParticipantList();
			} else {
				// The token is always defined in the public page.
				this.activeRoom = new OCA.SpreedMe.Models.Room({ token: this.token });
				this.signaling.setRoom(this.activeRoom);
			}

			this._registerPageEvents();
			this.initShareRoomClipboard();

			if (this.token) {
				this.connection.joinRoom(this.token);
			}
		},
		setupWebRTC: function() {
			if (!OCA.SpreedMe.webrtc) {
				OCA.SpreedMe.initWebRTC(this);
			}
		},
		startLocalMedia: function(configuration) {
			this.connection.showCamera();
			this.initAudioVideoSettings(configuration);

			this.setEmptyContentMessage(
				'icon-video',
				t('spreed', 'Waiting for others to join the call …')
			);
		},
		_onPopState: function(params) {
			if (!_.isUndefined(params.token)) {
				this.connection.joinRoom(params.token);
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
			if (!OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.unmute();
			$('#mute').attr('data-original-title', t('spreed', 'Mute audio'))
				.removeClass('audio-disabled icon-audio-off')
				.addClass('icon-audio');

			OCA.SpreedMe.app.audioDisabled = false;
		},
		disableAudio: function() {
			if (!OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.mute();
			$('#mute').attr('data-original-title', t('spreed', 'Enable audio'))
				.addClass('audio-disabled icon-audio-off')
				.removeClass('icon-audio');

			OCA.SpreedMe.app.audioDisabled = true;
		},
		enableVideo: function() {
			if (!OCA.SpreedMe.webrtc) {
				return;
			}

			var $hideVideoButton = $('#hideVideo');
			var $audioMuteButton = $('#mute');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			OCA.SpreedMe.webrtc.resumeVideo();
			$hideVideoButton.attr('data-original-title', t('spreed', 'Disable video'))
				.removeClass('video-disabled icon-video-off')
				.addClass('icon-video');
			$audioMuteButton.removeClass('video-disabled');

			avatarContainer.hide();
			localVideo.show();

			OCA.SpreedMe.app.videoDisabled = false;
		},
		hideVideo: function() {
			if (!OCA.SpreedMe.webrtc) {
				return;
			}

			var $hideVideoButton = $('#hideVideo');
			var $audioMuteButton = $('#mute');
			var avatarContainer = $hideVideoButton.closest('.videoView').find('.avatar-container');
			var localVideo = $hideVideoButton.closest('.videoView').find('#localVideo');

			if (!$hideVideoButton.hasClass('no-video-available')) {
				$hideVideoButton.attr('data-original-title', t('spreed', 'Enable video'))
					.addClass('video-disabled icon-video-off')
					.removeClass('icon-video');
				$audioMuteButton.addClass('video-disabled');
			}

			var avatar = avatarContainer.find('.avatar');
			var guestName = localStorage.getItem("nick");
			if (oc_current_user) {
				avatar.avatar(OC.currentUser, 128);
			} else if (guestName) {
				avatar.imageplaceholder(guestName, undefined, 128);
			} else if (this.displayedGuestNameHint === false) {
				avatar.imageplaceholder('?', undefined, 128);
				avatar.css('background-color', '#b9b9b9');
				OC.Notification.showTemporary(t('spreed', 'You can set your name on the right sidebar so other participants can identify you better.'));
				this.displayedGuestNameHint = true;
			}

			avatarContainer.removeClass('hidden');
			avatarContainer.show();
			localVideo.hide();
		},
		disableVideo: function() {
			OCA.SpreedMe.webrtc.pauseVideo();
			this.hideVideo();
			this.videoDisabled = true;
		},
		initGuestName: function() {
			this._localStorageModel = new OCA.SpreedMe.Models.LocalStorageModel({ nick: '' });
			this._localStorageModel.on('change:nick', function(model, newDisplayName) {
				$.ajax({
					url: OC.linkToOCS('apps/spreed/api/v1/guest', 2) + 'name',
					type: 'POST',
					data: {
						displayName: newDisplayName
					},
					beforeSend: function (request) {
						request.setRequestHeader('Accept', 'application/json');
					},
					success: function() {
						this._onChangeGuestName(newDisplayName);
					}.bind(this)
				});
			});

			this._localStorageModel.fetch();
		},
		_onChangeGuestName: function(newDisplayName) {
			var avatar = $('#localVideoContainer').find('.avatar');

			if (newDisplayName) {
				avatar.imageplaceholder(value, undefined, 128);
			} else {
				avatar.imageplaceholder('?', undefined, 128);
				avatar.css('background-color', '#b9b9b9');
			}

			if (this.webrtc) {
				this.webrtc.sendDirectlyToAll('status', 'nickChanged', newDisplayName);
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

})(OC, OCA, Marionette, Backbone, _, $);

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
	var localMediaChannel = Backbone.Radio.channel('localMedia');

	OCA.Talk.Application = Marionette.Application.extend({
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USERSELFJOINED: 5,
		GUEST_MODERATOR: 6,

		/* Must stay in sync with values in "lib/Participant.php". */
		NOTIFY_DEFAULT: 0,
		NOTIFY_ALWAYS: 1,
		NOTIFY_MENTION: 2,
		NOTIFY_NEVER: 3,

		/* Must stay in sync with values in "lib/Room.php". */
		FLAG_DISCONNECTED: 0,
		FLAG_IN_CALL: 1,
		FLAG_WITH_AUDIO: 2,
		FLAG_WITH_VIDEO: 4,

		/* Must stay in sync with values in "lib/Room.php". */
		ROOM_TYPE_ONE_TO_ONE: 1,
		ROOM_TYPE_GROUP: 2,
		ROOM_TYPE_PUBLIC: 3,
		ROOM_TYPE_CHANGELOG: 4,

		/** @property {OCA.SpreedMe.Models.Room} activeRoom  */
		activeRoom: null,

		/** @property {String} token  */
		token: null,

		/** @property {OCA.Talk.Connection} connection  */
		connection: null,

		/** @property {OCA.Talk.Signaling.base} signaling  */
		signaling: null,

		/** property {String} selector */
		mainCallElementSelector: '#app-content',

		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,
		/** @property {OCA.SpreedMe.Views.RoomListView} _roomsView  */
		_roomsView: null,
		/** @property {OCA.SpreedMe.Models.ParticipantCollection} _participants  */
		_participants: null,
		/** @property {OCA.SpreedMe.Views.ParticipantView} _participantsView  */
		_participantsView: null,
		/** @property {OCA.SpreedMe.Views.CollectionsView} _collectionsView */
		_collectionsView: null,
		fullscreenDisabled: true,
		_searchTerm: '',
		guestNick: null,
		_registerPageEvents: function() {
			$('#select-participants').select2({
				ajax: {
					url: OC.linkToOCS('core/autocomplete', 2) + 'get',
					dataType: 'json',
					quietMillis: 100,
					data: function (term) {
						this._searchTerm = term;
						return {
							format: 'json',
							search: term,
							itemType: 'call',
							itemId: 'new',
							shareTypes: [OC.Share.SHARE_TYPE_USER, OC.Share.SHARE_TYPE_GROUP]
						};
					}.bind(this),
					results: function (response) {
						// TODO improve error case
						if (response.ocs.data === undefined) {
							console.error('Failure happened', response);
							return;
						}

						var results = [];
						response.ocs.data.forEach(function(suggestion) {
							results.push({
								id: suggestion.id,
								displayName: suggestion.label,
								type: suggestion.source === 'users' ? 'user' : 'group'
							});
						});

						// Add custom entry to create a new empty group or public room
						if (OCA.SpreedMe.app._searchTerm === '') {
							results.unshift({
								id: "create-group-room",
								displayName: t('spreed', 'Enter name for a new conversation'),
								type: "createGroupRoom"
							});
						} else {
							var shortenedName = OCA.SpreedMe.app._searchTerm;
							if (OCA.SpreedMe.app._searchTerm.length > 25) {
								shortenedName = shortenedName.substring(0, 25) + '…';
							}

							results.push({
								id: "create-group-room",
								displayName: shortenedName,
								type: "createGroupRoom"
							});
							results.push({
								id: "create-public-room",
								displayName: shortenedName,
								type: "createPublicRoom"
							});
						}

						return {
							results: results,
							more: false
						};
					}
				},
				initSelection: function (element, callback) {
					callback({id: element.val()});
				},
				formatResult: function (element) {
					if (element.type === "createPublicRoom") {
						return '<span><div class="avatar icon icon-public"></div>' + t('spreed', '{name} (public)', { name: element.displayName }) + '</span>';
					}

					if (element.type === "createGroupRoom" || element.type === 'group') {
						return '<span><div class="avatar icon icon-contacts"></div>' + escapeHTML(element.displayName) + '</span>';
					}

					return '<span><div class="avatar" data-user="' + escapeHTML(element.id) + '" data-user-display-name="' + escapeHTML(element.displayName) + '"></div>' + escapeHTML(element.displayName) + '</span>';
				},
				formatSelection: function () {
					return '<span class="select2-default" style="padding-left: 0;">' + t('spreed', 'New conversation …') + '</span>';
				}
			});

			$('#select-participants').on('select2-selecting', function(e) {
				switch (e.object.type) {
					case 'user':
						this.connection.createOneToOneVideoCall(e.val);
						break;
					case 'group':
						this.connection.createGroupVideoCall(e.val, '');
						break;
					case 'createPublicRoom':
						if (OCA.SpreedMe.app._searchTerm !== '') {
							this.connection.createPublicVideoCall(OCA.SpreedMe.app._searchTerm);
						}
						break;
					case 'createGroupRoom':
						if (OCA.SpreedMe.app._searchTerm !== '') {
							this.connection.createGroupVideoCall('', OCA.SpreedMe.app._searchTerm);
						}
						break;
					default:
						console.log('Unknown type', e.object.type);
						break;
				}
			}.bind(this));

			$('#select-participants').on('select2-loaded', function() {
				$('.select2-drop').find('.avatar[data-user]').each(function () {
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

			this.registerLocalVideoButtonHandlers();

			$(document).keyup(this._onKeyUp.bind(this));
		},

		registerLocalVideoButtonHandlers: function() {
			$('#video-fullscreen').click(function() {
				if (this.fullscreenDisabled) {
					this.enableFullscreen();
				} else {
					this.disableFullscreen();
				}
			}.bind(this));
		},

		_onKeyUp: function(event) {
			// Define which objects to check for the event properties.
			var key = event.which;

			// Trigger the event only if no input or textarea is focused
			// and the CTRL key is not pressed
			if ($('input:focus').length === 0 &&
				$('textarea:focus').length === 0 &&
				$('div[contenteditable=true]:focus').length === 0 &&
				!event.ctrlKey) {

				// Actual shortcut handling
				switch (key) {
					case 86: // 'v'
						event.preventDefault();
						this._mediaControlsView.toggleVideo();
						break;
					case 77: // 'm'
						event.preventDefault();
						this._mediaControlsView.toggleAudio();
						break;
					case 70: // 'f'
						event.preventDefault();
						if (this.fullscreenDisabled) {
							this.enableFullscreen();
						} else {
							this.disableFullscreen();
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

			this._participantsListChangedCallback = function() {
				// The "participantListChanged" event can be triggered by the
				// signaling before the room is set in the collection.
				if (this._participants.url) {
					this._participants.fetch();
				}
			}.bind(this);

			this.signaling.on('participantListChanged', this._participantsListChangedCallback);

			this._participantsView.listenTo(this._rooms, 'change:active', function(model, active) {
				if (active) {
					this.setRoom(model);
				}
			});

			this._sidebarView.addTab('participants', { label: t('spreed', 'Participants'), icon: 'icon-contacts-dark' }, this._participantsView);

			this._collectionsView = new OCA.SpreedMe.Views.CollectionsView({
				room: this.activeRoom,
				id: 'collectionsTabView'
			});
			this._collectionsView.listenTo(this._rooms, 'change:active', function(model, active) {
				if (active) {
					this.setRoom(model);
				}
			});
			this._sidebarView.addTab('collections', { label: t('spreed', 'Collections'), icon: 'icon-category-integration' }, this._collectionsView);
		},
		_hideParticipantList: function() {
			this._sidebarView.removeTab('participants');

			this.signaling.off('participantListChanged', this._participantsListChangedCallback);

			delete this._participantsListChangedCallback;
			delete this._participantsView;
			delete this._participants;
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
		addParticipantToRoom: function(token, participant, type) {
			$.post(
				OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/participants',
				{
					newParticipant: participant,
					source: type
				}
			).done(function() {
				this.signaling.syncRooms();
			}.bind(this));
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.signaling.syncRooms()
				.then(function() {
					self.stopListening(self.activeRoom, 'change:displayName');
					self.stopListening(self.activeRoom, 'change:participantFlags');

					if (OC.getCurrentUser().uid) {
						roomChannel.trigger('active', token);

						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								self.activeRoom = room;
							}
						});
					}

					self._emptyContentView.setActiveRoom(self.activeRoom);

					self.setPageTitle(self.activeRoom.get('displayName'));
					self.listenTo(self.activeRoom, 'change:displayName', function(model, value) {
						self.setPageTitle(value);
					});

					self.updateContentsLayout();
					self.listenTo(self.activeRoom, 'change:participantFlags', self.updateContentsLayout);

					self.updateSidebarWithActiveRoom();
				});
		},
		updateContentsLayout: function() {
			if (!this.activeRoom) {
				// This should never happen, but just in case
				return;
			}

			var flags = this.activeRoom.get('participantFlags') || 0;
			var inCall = flags & OCA.SpreedMe.app.FLAG_IN_CALL !== 0;
			if (inCall && this._chatViewInMainView === true) {
				this._chatView.$el.detach();
				this._sidebarView.addTab('chat', { label: t('spreed', 'Chat'), icon: 'icon-comment', priority: 100 }, this._chatView);
				this._sidebarView.selectTab('chat');
				this._chatView.reloadMessageList();
				this._chatView.setTooltipContainer(this._chatView.$el);
				this._chatViewInMainView = false;
			} else if (!inCall && !this._chatViewInMainView) {
				this._sidebarView.removeTab('chat');
				this._chatView.$el.prependTo('#app-content-wrapper');
				this._chatView.reloadMessageList();
				this._chatView.setTooltipContainer($('#app'));
				this._chatView.focusChatInput();
				this._chatViewInMainView = true;
			}

			if (inCall) {
				$('#videos').show();
				$('#screens').show();
				$('#emptycontent').hide();
			} else {
				$('#videos').hide();
				$('#screens').hide();
				$('#emptycontent').show();
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
			if (this.activeRoom.get('type') === this.ROOM_TYPE_CHANGELOG) {
				this._sidebarView.close();
			} else if (this.activeRoom.get('type') !== this.ROOM_TYPE_CHANGELOG && $(window).width() > 1111) {
				this._sidebarView.open();
			}

			var callInfoView = new OCA.SpreedMe.Views.CallInfoView({
				model: this.activeRoom,
				guestNameModel: this._localStorageModel
			});
			this._sidebarView.setCallInfoView(callInfoView);

			this._chatView.setRoom(this.activeRoom);
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
		initialize: function() {
			this._emptyContentView = new OCA.SpreedMe.Views.EmptyContentView({
				el: '#app-content-wrapper > #emptycontent',
			});

			this._sidebarView = new OCA.SpreedMe.Views.SidebarView();
			$('#content').append(this._sidebarView.$el);

			if (OC.getCurrentUser().uid) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			} else {
				this.initGuestName();
			}

			this._sidebarView.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this.disable();
			});

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				model: this.activeRoom,
				id: 'chatView',
				guestNameModel: this._localStorageModel
			});

			// Focus the chat input when the chat tab is selected.
			this._chatView.listenTo(this._sidebarView, 'select:tab', function(tabId) {
				if (tabId !== 'chat') {
					return;
				}

				this._chatView.focusChatInput();
			}.bind(this));

			// Opening and closing the sidebar detachs its contents to perform
			// the animation; detaching an element and attaching it again resets
			// its scroll position, so the scroll position of the chat view
			// needs to be saved before the sidebar is closed and restored again
			// once the sidebar is opened.
			this._chatView.listenTo(this._sidebarView, 'opened', function() {
				if (this._sidebarView.getCurrentTabId() !== 'chat') {
					return;
				}

				this._chatView.restoreScrollPosition();
			}.bind(this));
			this._chatView.listenTo(this._sidebarView, 'close', function() {
				if (this._sidebarView.getCurrentTabId() !== 'chat') {
					return;
				}

				this._chatView.saveScrollPosition();
			}.bind(this));

			// Selecting a different tab detachs the contents of the previous
			// tab and attachs the contents of the new tab; detaching an element
			// and attaching it again resets its scroll position, so the scroll
			// position of the chat view needs to be saved when the chat tab is
			// unselected and restored again when the chat tab is selected.
			this._chatView.listenTo(this._sidebarView, 'unselect:tab', function(tabId) {
				if (tabId !== 'chat') {
					return;
				}

				this._chatView.saveScrollPosition();
			}.bind(this));
			this._chatView.listenTo(this._sidebarView, 'select:tab', function(tabId) {
				if (tabId !== 'chat') {
					return;
				}

				this._chatView.restoreScrollPosition();
			}.bind(this));

			// Opening or closing the sidebar changes the width of the main
			// view, so if the chat view is in the main view it needs to be
			// reloaded.
			var reloadMessageListOnSidebarVisibilityChange = function() {
				if (!this._chatViewInMainView) {
					return;
				}

				this._chatView.reloadMessageList();
			}.bind(this);
			this._chatView.listenTo(this._sidebarView, 'opened', reloadMessageListOnSidebarVisibilityChange);
			this._chatView.listenTo(this._sidebarView, 'closed', reloadMessageListOnSidebarVisibilityChange);

			// Resizing the window can change the size of the chat view, both
			// when it is in the main view and in the sidebar, so the chat view
			// needs to be reloaded. The initial reload is not very heavy, so
			// the handler is not debounced for a snappier feel and to reduce
			// flickering.
			// However, resizing the window below certain width causes the
			// navigation bar to be hidden; an explicit handling is needed in
			// this case because the app navigation (or, more specifically, its
			// Snap object) adds a transition to the app content, so the reload
			// needs to be delayed to give the transition time to end and thus
			// give the app content time to get its final size.
			var reloadMessageListOnWindowResize = function() {
				var chatView = this._chatView;

				if ($(window).width() >= 768 || !this._chatViewInMainView) {
					chatView.reloadMessageList();

					return;
				}

				setTimeout(function() {
					chatView.reloadMessageList();
				}, 300);
			}.bind(this);
			$(window).resize(reloadMessageListOnWindowResize);

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this.stopReceivingMessages();
			});

			this.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this._chatView.$el.detach();
				this._chatViewInMainView = false;

				$('#videos').hide();
				$('#screens').hide();
				$('#emptycontent').show();
			});

			this.listenTo(roomChannel, 'joinRoom', function(token) {
				if (OCA.Talk.PublicShareAuth) {
					return;
				}

				if (this._popingState) {
					return;
				}

				OC.Util.History.pushState({
					token: token
				}, OC.generateUrl('/call/' + token));
			});

			this.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				if (OCA.Talk.PublicShareAuth) {
					return;
				}

				this.setPageTitle(null);

				OC.Util.History.replaceState({}, OC.generateUrl('/apps/spreed'));
			});

			this._localVideoView = new OCA.Talk.Views.LocalVideoView({
				app: this,
				webrtc: OCA.SpreedMe.webrtc,
				sharedScreens: OCA.SpreedMe.sharedScreens,
			});
			this._localVideoView.render();
			// Ensure that the local video is not visible in the initial page.
			this._localVideoView.$el.addClass('hidden');
			$('#videos').append(this._localVideoView.$el);

			this._mediaControlsView = this._localVideoView._mediaControlsView;

			$(document).on('click', this.onDocumentClick);
			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));
		},
		onStart: function() {
			this.signaling = OCA.Talk.Signaling.createConnection();
			this.connection = new OCA.Talk.Connection(this);
			this.token = $('#app').attr('data-token');

			this.signaling.on('joinRoom', function(/* token */) {
				this.inRoom = true;
				if (this.pendingNickChange) {
					this.setGuestName(this.pendingNickChange);
					delete this.pendingNickChange;
				}
			}.bind(this));

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
			} else if (this.token) {
				// The token is always defined in the public page (although not
				// in the public share auth page).
				this.activeRoom = new OCA.SpreedMe.Models.Room({ token: this.token });
				this.signaling.setRoom(this.activeRoom);

				this.listenTo(this.activeRoom, 'change:participantType', function(model, participantType) {
					if (participantType === OCA.SpreedMe.app.GUEST_MODERATOR) {
						this._showParticipantList();
						// The public page supports only a single room, so the
						// active room has to be explicitly set as it will not
						// be set in a 'change:active' event.
						this._participantsView.setRoom(this.activeRoom);
					} else {
						this._hideParticipantList();
					}
				});
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
				this._mediaControlsView.setWebRtc(OCA.SpreedMe.webrtc);
				this._mediaControlsView.setSharedScreens(OCA.SpreedMe.sharedScreens);
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

			this._localVideoView.$el.removeClass('hidden');
			this.initAudioVideoSettings(configuration);

			localMediaChannel.trigger('startLocalMedia');
		},
		startWithoutLocalMedia: function(configuration) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(null);
				this.callbackAfterMedia = null;
			}

			this._localVideoView.$el.removeClass('hidden');
			this.initAudioVideoSettings(configuration);

			if (OCA.SpreedMe.webrtc.capabilities.support) {
				localMediaChannel.trigger('startWithoutLocalMedia');
			}
		},
		_onPopState: function(params) {
			if (!_.isUndefined(params.token)) {
				this._popingState = true;
				this.connection.joinRoom(params.token);
				delete this._popingState;
			}
		},
		onDocumentClick: function(event) {
			var uiChannel = Backbone.Radio.channel('ui');

			uiChannel.trigger('document:click', event);
		},
		initAudioVideoSettings: function(configuration) {
			if (configuration.audio !== false) {
				this._mediaControlsView.setAudioAvailable(true);
				this._mediaControlsView.setAudioEnabled(this._mediaControlsView.audioEnabled);
			} else {
				this._mediaControlsView.setAudioEnabled(false);
				this._mediaControlsView.setAudioAvailable(false);
			}

			if (configuration.video !== false) {
				this._mediaControlsView.setVideoAvailable(true);
				this.setVideoEnabled(this._mediaControlsView.videoEnabled);
			} else {
				this.setVideoEnabled(false);
				this._mediaControlsView.setVideoAvailable(false);
			}
		},
		enableFullscreen: function() {
			var fullscreenElem = document.getElementById('content');

			if (fullscreenElem.requestFullscreen) {
				fullscreenElem.requestFullscreen();
			} else if (fullscreenElem.webkitRequestFullscreen) {
				fullscreenElem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
			} else if (fullscreenElem.mozRequestFullScreen) {
				fullscreenElem.mozRequestFullScreen();
			} else if (fullscreenElem.msRequestFullscreen) {
				fullscreenElem.msRequestFullscreen();
			}
			$('#video-fullscreen').attr('data-original-title', t('spreed', 'Exit fullscreen (f)'));

			this.fullscreenDisabled = false;
		},
		disableFullscreen: function() {

			if (document.exitFullscreen) {
				document.exitFullscreen();
			} else if (document.webkitExitFullscreen) {
				document.webkitExitFullscreen();
			} else if (document.mozCancelFullScreen) {
				document.mozCancelFullScreen();
			} else if (document.msExitFullscreen) {
				document.msExitFullscreen();
			}
			$('#video-fullscreen').attr('data-original-title', t('spreed', 'Fullscreen (f)'));

			this.fullscreenDisabled = true;
		},
		setVideoEnabled: function(videoEnabled) {
			if (!this._mediaControlsView.setVideoEnabled(videoEnabled)) {
				return;
			}

			this._localVideoView.setVideoEnabled(videoEnabled);
		},
		// Called from webrtc.js
		disableScreensharingButton: function() {
			this._mediaControlsView.disableScreensharingButton();
		},
		setGuestName: function(name) {
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/guest', 2) + this.token + '/name',
				type: 'POST',
				data: {
					displayName: name
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: function() {
					this._onChangeGuestName(name);
				}.bind(this)
			});
		},
		initGuestName: function() {
			this._localStorageModel = new OCA.SpreedMe.Models.LocalStorageModel({ nick: '' });
			this._localStorageModel.on('change:nick', function(model, newDisplayName) {
				if (!this.token || !this.inRoom) {
					this.pendingNickChange = newDisplayName;
					return;
				}

				this.setGuestName(newDisplayName);
			}.bind(this));

			this._localStorageModel.fetch();
		},
		_onChangeGuestName: function(newDisplayName) {
			this._localVideoView.setAvatar(undefined, newDisplayName);

			if (OCA.SpreedMe.webrtc) {
				console.log('_onChangeGuestName.webrtc');
				OCA.SpreedMe.webrtc.sendDirectlyToAll('status', 'nickChanged', newDisplayName);
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

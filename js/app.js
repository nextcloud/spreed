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

						//Add custom entry to create a new empty group or public room
						if (OCA.SpreedMe.app._searchTerm === '') {
							results.unshift({
								id: "create-public-room",
								displayName: t('spreed', 'New public conversation'),
								type: "createPublicRoom"
							});
							results.unshift({
								id: "create-group-room",
								displayName: t('spreed', 'New group conversation'),
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
								displayName: t('spreed', '{name} (public)', { name: shortenedName }),
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
						return '<span><div class="avatar icon-public-white"></div>' + escapeHTML(element.displayName) + '</span>';
					}

					if (element.type === "createGroupRoom" || element.type === 'group') {
						return '<span><div class="avatar icon-contacts"></div>' + escapeHTML(element.displayName) + '</span>';
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
						this.connection.createPublicVideoCall(OCA.SpreedMe.app._searchTerm);
						break;
					case 'createGroupRoom':
						this.connection.createGroupVideoCall('', OCA.SpreedMe.app._searchTerm);
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
				if (this.fullscreenDisabled) {
					this.enableFullscreen();
				} else {
					this.disableFullscreen();
				}
			}.bind(this));

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
			});
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
						if (this.videoDisabled) {
							this.enableVideo();
						} else {
							this.disableVideo();
						}
						break;
					case 77: // 'm'
						event.preventDefault();
						if (this.audioDisabled) {
							this.enableAudio();
						} else {
							this.disableAudio();
						}
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
					} else {
						// The public page supports only a single room, so the
						// active room is already the room for the given token.
						participants = self.activeRoom.get('participants');
						self.setRoomMessageForGuest(participants);
					}
					// Disable video when entering a room with more than 5 participants.
					if (participants && Object.keys(participants).length > 5) {
						self.disableVideo();
					}

					self.setPageTitle(self.activeRoom.get('displayName'));

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
				$('#video-speaking').show();
				$('#videos').show();
				$('#screens').show();
				$('#emptycontent').hide();
			} else {
				$('#video-speaking').hide();
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
		/**
		 *
		 * @param {string|Object} icon
		 * @param {string} icon.userId
		 * @param {string} icon.displayName
		 * @param {string} message
		 * @param {string} [messageAdditional]
		 * @param {string} [url]
		 */
		setEmptyContentMessage: function(icon, message, messageAdditional, url) {
			var $icon = $('#emptycontent-icon'),
				$emptyContent = $('#emptycontent');

			//Remove previous icon and avatar from emptycontent
			$icon.removeAttr('class').attr('class', '');
			$icon.html('');

			if (url) {
				$('#shareRoomInput').removeClass('hidden').val(url);
				$('#shareRoomClipboardButton').removeClass('hidden');
			} else {
				$('#shareRoomInput').addClass('hidden');
				$('#shareRoomClipboardButton').addClass('hidden');
			}

			if (typeof icon === 'string') {
				$icon.addClass(icon);
			} else {
				var $avatar = $('<div>');
				$avatar.addClass('avatar room-avatar');
				if (icon.userId !== icon.displayName) {
					$avatar.avatar(icon.userId, 128, undefined, false, undefined, icon.displayName);
				} else {
					$avatar.avatar(icon.userId, 128);
				}
				$icon.append($avatar);
			}

			$emptyContent.find('h2').html(message);
			$emptyContent.find('p').text(messageAdditional ? messageAdditional : '');
			this._lastEmptyContent = this._currentEmptyContent;
			this._currentEmptyContent = arguments;
		},
		restoreEmptyContent: function() {
			this.setEmptyContentMessage.apply(this, this._lastEmptyContent);
		},
		setRoomMessageForGuest: function(participants) {
			if (Object.keys(participants).length === 1) {
				var participantId = '',
					participantName = '';

				_.each(participants, function(data, userId) {
					if (OC.getCurrentUser().uid !== userId) {
						participantId = userId;
						participantName = data.name;
					}
				});

				OCA.SpreedMe.app.setEmptyContentMessage(
					{ userId: participantId, displayName: participantName},
					t('spreed', 'Waiting for {participantName} to join the call …', {participantName: participantName})
				);

			} else {
				OCA.SpreedMe.app.setEmptyContentMessage('icon-contacts-dark', t('spreed', 'Waiting for others to join the call …'));
			}
		},
		initialize: function() {
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
				id: 'commentsTabView',
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

				$('#video-speaking').hide();
				$('#videos').hide();
				$('#screens').hide();
				$('#emptycontent').show();
			});

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

			$(window).unload(function () {
				this.connection.leaveCurrentRoom(false);
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
			}
			OCA.SpreedMe.webrtc.startMedia(this.token);
		},
		startLocalMedia: function(configuration) {
			if (this.callbackAfterMedia) {
				this.callbackAfterMedia(configuration);
				this.callbackAfterMedia = null;
			}

			$('.videoView').removeClass('hidden');
			this.initAudioVideoSettings(configuration);
			this.restoreEmptyContent();
		},
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
		enableAudioButton: function() {
			$('#mute').attr('data-original-title', t('spreed', 'Mute audio (m)'))
				.removeClass('audio-disabled icon-audio-off')
				.addClass('icon-audio');
		},
		enableAudio: function() {
			if (this.audioNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}
			OCA.SpreedMe.webrtc.unmute();
			this.enableAudioButton();
			this.audioDisabled = false;
		},
		disableAudioButton: function() {
			$('#mute').attr('data-original-title', t('spreed', 'Unmute audio (m)'))
				.addClass('audio-disabled icon-audio-off')
				.removeClass('icon-audio');
		},
		disableAudio: function() {
			if (this.audioNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}
			OCA.SpreedMe.webrtc.mute();
			this.disableAudioButton();
			this.audioDisabled = true;
		},
		hasAudio: function() {
			$('#mute').removeClass('no-audio-available');
			this.enableAudioButton();
			this.audioNotFound = false;
		},
		hasNoAudio: function() {
			$('#mute').removeClass('audio-disabled icon-audio')
				.addClass('no-audio-available icon-audio-off')
				.attr('data-original-title', t('spreed', 'No audio'));
			this.audioDisabled = true;
			this.audioNotFound = true;
		},
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
		enableVideo: function() {
			if (this.videoNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.resumeVideo();
			this.enableVideoUI();
			this.videoDisabled = false;
		},
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
		disableVideo: function() {
			if (this.videoNotFound || !OCA.SpreedMe.webrtc) {
				return;
			}

			OCA.SpreedMe.webrtc.pauseVideo();
			this.hideVideo();
			this.videoDisabled = true;
		},
		hasVideo: function() {
			$('#hideVideo').removeClass('no-video-available');
			this.enableVideoUI();
			this.videoNotFound = false;
		},
		hasNoVideo: function() {
			$('#hideVideo').removeClass('icon-video')
				.addClass('no-video-available icon-video-off')
				.attr('data-original-title', t('spreed', 'No Camera'));
			this.videoDisabled = true;
			this.videoNotFound = true;
		},
		disableScreensharingButton: function() {
			$('#screensharing-button').attr('data-original-title', t('spreed', 'Enable screensharing'))
					.addClass('screensharing-disabled icon-screen-off')
					.removeClass('icon-screen');
			$('#screensharing-menu').toggleClass('open', false);
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
			var avatar = $('#localVideoContainer').find('.avatar');

			avatar.imageplaceholder('?', newDisplayName, 128);
			avatar.css('background-color', '#b9b9b9');

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

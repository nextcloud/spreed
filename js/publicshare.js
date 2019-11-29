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

(function(OCA) {
	'use strict';

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.PublicShare = {

		init: function() {
			this._boundHideCallUi = this._hideCallUi.bind(this);

			this.setupLayoutForTalkSidebar();

			this.setupSignalingEventHandlers();

			// Open the sidebar by default based on the window width
			// using the same threshold as in the main Talk UI.
			if ($(window).width() > 1111) {
				// Delay showing the Talk sidebar, as if it is shown too soon
				// after the page loads (even if it has loaded) there will be no
				// transition.
				setTimeout(function() {
					this.showTalkSidebar();
				}.bind(this), 1000);
			}
		},

		setupLayoutForTalkSidebar: function() {
			this._talkSidebarTrigger = $('<button id="talk-sidebar-trigger" class="icon-menu-people"></button>');
			this._talkSidebarTrigger.click(function() {
				if ($('#talk-sidebar').hasClass('disappear')) {
					this.showAndUpdateTalkSidebar();

					OCA.SpreedMe.app._chatView.saveScrollPosition();
				} else {
					this.hideTalkSidebar();
				}
			}.bind(this));
			$('.header-right').append(this._talkSidebarTrigger);

			$('#app-content').append($('footer'));

			this._$callContainerWrapper = $('<div id="call-container-wrapper" class="hidden"></div>');

			$('#content').append('<div id="talk-sidebar" class="disappear"></div>');
			$('#talk-sidebar').append(this._$callContainerWrapper);
			$('#call-container-wrapper').append('<div id="call-container"></div>');
			$('#call-container-wrapper').append('<div id="emptycontent"><div id="emptycontent-icon" class="icon-loading"></div><h2></h2><p class="emptycontent-additional"></p></div>');
			$('#call-container').append('<div id="videos"></div>');
			$('#call-container').append('<div id="screens"></div>');

			OCA.SpreedMe.app._emptyContentView = new OCA.SpreedMe.Views.EmptyContentView({
				el: '#call-container-wrapper > #emptycontent'
			});

			OCA.SpreedMe.app._localVideoView.render();
			OCA.SpreedMe.app._mediaControlsView.hideScreensharingButton();
			$('#videos').append(OCA.SpreedMe.app._localVideoView.$el);

			this._$roomNotJoinedMessage = $(
				'<div class="emptycontent room-not-joined">' +
				'    <div class="icon icon-talk"></div>' +
				'    <h2>' + t('spreed', 'Discuss this file') + '</h2>' +
				'    <button class="primary">' + t('spreed', 'Join conversation') + '<span class="icon icon-loading-small hidden"/></button>' +
				'</div>');

			this._$joinRoomButton = this._$roomNotJoinedMessage.find('button');
			this._$joinRoomButton.click(function() {
				// TODO The button should be enabled again and a notification
				// shown in case of failure, but the signaling object currently
				// does not provide a way to know that joining the room failed
				// (in fact, it will redirect to the main Talk UI).
				this._$joinRoomButton.prop('disabled', true);
				this._$joinRoomButton.find('.icon-loading-small').removeClass('hidden');

				this.enableTalkSidebar();
			}.bind(this));

			$('#talk-sidebar').append(this._$roomNotJoinedMessage);
		},

		enableTalkSidebar: function() {
			var self = this;

			var shareToken = $('#sharingToken').val();

			if (this.hideTalkSidebarTimeout) {
				clearTimeout(this.hideTalkSidebarTimeout);
				delete this.hideTalkSidebarTimeout;
			}

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'publicshare/' + shareToken,
				type: 'GET',
				beforeSend: function(request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: function(ocsResponse) {
					if (ocsResponse.ocs.data.userId) {
						// Override "OC.getCurrentUser()" with the user returned
						// by the controller (as the public share page uses the
						// incognito mode, and thus it always returns an
						// anonymous user).
						//
						// When the external signaling server is used it should
						// wait until the current user is set before trying to
						// connect, as otherwise the connection would fail due
						// to a mismatch between the user ID given when
						// connecting to the backend (an anonymous user) and the
						// user that fetched the signaling settings (the actual
						// user). However, if that happens the signaling server
						// will retry the connection again and again, so at some
						// point the anonymous user will have been overriden
						// with the current user and the connection will
						// succeed.
						OCA.Talk.setCurrentUser(ocsResponse.ocs.data.userId, ocsResponse.ocs.data.userDisplayName);
					}

					self.setupRoom(ocsResponse.ocs.data.token);
				},
				error: function() {
					// Just keep sidebar hidden
				}
			});
		},

		setupSignalingEventHandlers: function() {
			var self = this;

			OCA.SpreedMe.app.signaling.on('joinRoom', function(joinedRoomToken) {
				if (OCA.SpreedMe.app.token !== joinedRoomToken) {
					return;
				}

				function setPageTitle(title) {
					if (title) {
						title += ' - ';
					} else {
						title = '';
					}
					title += t('spreed', 'Talk');
					title += ' - ' + oc_defaults.title;
					window.document.title = title;
				}

				OCA.SpreedMe.app.signaling.syncRooms().then(function() {
					self._$roomNotJoinedMessage.remove();

					OCA.SpreedMe.app._chatView.$el.appendTo('#talk-sidebar');
					OCA.SpreedMe.app._chatView.setTooltipContainer($('body'));

					// "joinRoom" will be called again in a forced reconnection
					// during a call with the MCU, so the previous button needs
					// to be removed before adding a new one.
					if (self._callButton) {
						self._callButton.remove();
					}

					self._callButton = new OCA.SpreedMe.Views.CallButton({
						model: OCA.SpreedMe.app.activeRoom,
						connection: OCA.SpreedMe.app.connection,
					});
					// Force initial rendering; changes in the room state will
					// automatically render the button again from now on.
					self._callButton.render();
					self._callButton.$el.insertBefore(OCA.SpreedMe.app._chatView.$el);

					self.stopListening(OCA.SpreedMe.app.activeRoom, 'change:participantFlags', self._updateCallContainer);
					// Signaling uses its own event system, so Backbone methods can not
					// be used.
					OCA.SpreedMe.app.signaling.off('leaveCall', self._boundHideCallUi);

					if (OCA.SpreedMe.app.activeRoom) {
						self.listenTo(OCA.SpreedMe.app.activeRoom, 'change:participantFlags', self._updateCallContainer);
						// Signaling uses its own event system, so Backbone methods can
						// not be used.
						OCA.SpreedMe.app.signaling.on('leaveCall', self._boundHideCallUi);
					}

					OCA.SpreedMe.app._emptyContentView.setActiveRoom(OCA.SpreedMe.app.activeRoom);

					setPageTitle(OCA.SpreedMe.app.activeRoom.get('displayName'));

					OCA.SpreedMe.app._chatView.setRoom(OCA.SpreedMe.app.activeRoom);
					OCA.SpreedMe.app._messageCollection.setRoomToken(OCA.SpreedMe.app.activeRoom.get('token'));
					OCA.SpreedMe.app._messageCollection.receiveMessages();
				});
			});
		},

		setupRoom: function(token) {
			OCA.SpreedMe.app.activeRoom = new OCA.SpreedMe.Models.Room({token: token});
			OCA.SpreedMe.app.signaling.setRoom(OCA.SpreedMe.app.activeRoom);

			OCA.SpreedMe.app.token = token;
			OCA.SpreedMe.app.signaling.joinRoom(token);
		},

		_updateCallContainer: function() {
			var flags = OCA.SpreedMe.app.activeRoom.get('participantFlags') || 0;
			var inCall = flags & OCA.SpreedMe.app.FLAG_IN_CALL !== 0;
			if (inCall) {
				this._showCallUi();
			} else {
				this._hideCallUi();
			}
		},

		_showCallUi: function() {
			if (!this._$callContainerWrapper || !this._$callContainerWrapper.hasClass('hidden')) {
				return;
			}

			this._$callContainerWrapper.removeClass('hidden');
		},

		_hideCallUi: function() {
			if (!this._$callContainerWrapper || this._$callContainerWrapper.hasClass('hidden')) {
				return;
			}

			this._$callContainerWrapper.addClass('hidden');
		},

		leaveRoom: function() {
			this.hideTalkSidebarTimeout = setTimeout(this.hideTalkSidebar, 5000);
		},

		/**
		 * Shows the Talk sidebar and updates its contents.
		 */
		showAndUpdateTalkSidebar: function() {
			this.showTalkSidebar().then(function() {
				this._$joinRoomButton.prop('disabled', false);

				OCA.SpreedMe.app._chatView.restoreScrollPosition();

				// When the sidebar is shown again the message list needs to
				// be reloaded to add the messages that could have been
				// received while detached.
				OCA.SpreedMe.app._chatView.reloadMessageList();

				// Once the sidebar is shown its size has changed, so
				// the chat view needs to handle a size change.
				OCA.SpreedMe.app._chatView.handleSizeChanged();
			}.bind(this));
		},

		/**
		 * Wait for the sidebar to end changing its width.
		 *
		 * The changes on the sidebar width are animated; this method returns a
		 * promise that is resolved the next time that the sidebar width ends
		 * changing.
		 */
		_waitForSidebarWidthChangeEnd: function() {
			var deferred = $.Deferred();

			if ('ontransitionend' in $('#talk-sidebar').get(0)) {
				var resolveOnceSidebarWidthHasChanged = function(event) {
					if (event.propertyName !== 'min-width' && event.propertyName !== 'width') {
						return;
					}

					$('#talk-sidebar').get(0).removeEventListener('transitionend', resolveOnceSidebarWidthHasChanged);

					deferred.resolve();
				};

				$('#talk-sidebar').get(0).addEventListener('transitionend', resolveOnceSidebarWidthHasChanged);
			} else {
				var animationQuickValue = getComputedStyle(document.documentElement).getPropertyValue('--animation-quick');

				// The browser does not support the "ontransitionend" event, so
				// just wait a few milliseconds more than the duration of the
				// transition.
				setTimeout(function() {
					console.log('ontransitionend is not supported; the sidebar should have been fully shown/hidden by now');

					deferred.resolve();
				}, Number.parseInt(animationQuickValue) + 200);
			}

			return deferred.promise();
		},

		/**
		 * Shows the Talk sidebar.
		 *
		 * The sidebar is shown with an animation; this method returns a promise
		 * that is resolved once the sidebar has been fully shown.
		 */
		showTalkSidebar: function() {
			var deferred = $.Deferred();

			if (!$('#talk-sidebar').hasClass('disappear')) {
				deferred.resolve();

				return deferred.promise();
			}

			this._waitForSidebarWidthChangeEnd().then(function() {
				deferred.resolve();
			});

			$('#talk-sidebar').removeClass('hidden-important');

			// Defer removing the disappear class to ensure that a transition
			// will be triggered, as if it is removed at the same time as the
			// "display: none" property the new width will be immediately set.
			setTimeout(function() {
				$('#talk-sidebar').removeClass('disappear');
			}, 0);

			return deferred.promise();
		},

		hideTalkSidebar: function() {
			$('#talk-sidebar').addClass('disappear');

			this._waitForSidebarWidthChangeEnd().then(function() {
				// "display" CSS properties can not be animated, so the sidebar
				// needs to be explicitly hidden once the transition ends.
				$('#talk-sidebar').addClass('hidden-important');
			});

			delete this.hideTalkSidebarTimeout;
		},
	};

	_.extend(OCA.Talk.PublicShare, Backbone.Events);

	OCA.SpreedMe.app = new OCA.Talk.Embedded();

	OCA.SpreedMe.app.on('start', function() {
		OCA.Talk.PublicShare.init();
	});

	// Unlike in the regular Talk app when Talk is embedded the signaling
	// settings are not initially included in the HTML, so they need to be
	// explicitly loaded before starting the app.
	OCA.Talk.Signaling.loadSettings().then(function() {
		OCA.SpreedMe.app.start();
	});

})(OCA);

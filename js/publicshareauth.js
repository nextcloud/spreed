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

	var roomsChannel = Backbone.Radio.channel('rooms');

	OCA.Talk = OCA.Talk || {};
	OCA.Talk.PublicShareAuth = {

		init: function() {
			var self = this;

			this.setupRequestPasswordButton();
			this.setupLayoutForTalkSidebar();

			this.setupSignalingEventHandlers();

			$('#request-password-button').click(function() {
				$('.request-password-wrapper + .error-message').hide();

				$('#request-password-button').prop('disabled', 'true');

				$('.request-password-wrapper .icon')
						.removeClass('icon-confirm-white')
						.addClass('icon-loading-small-dark');

				self.requestPassword();
			});
		},

		setupRequestPasswordButton: function() {
			// "submit-wrapper" is used to mimic the login button and thus get
			// automatic colouring of the confirm icon by the Theming app
			$('main').append('<div id="submit-wrapper" class="request-password-wrapper">' +
							'    <input id="request-password-button" class="primary" type="button" value="' + t('spreed', 'Request password') + '" >' +
							'    <div class="icon icon-confirm-white"></div>' +
							'</div>');
		},

		setupLayoutForTalkSidebar: function() {
			$('body').append('<div id="notification-container"><div id="notification"></div></div>');

			$('body').append('<div id="content"></div>');
			$('#content').append($('.wrapper'));
			$('#content').append($('footer'));

			$('body').append('<div id="talk-sidebar" class="disappear"></div>');
			$('#talk-sidebar').append('<div id="call-container"></div>');
			$('#talk-sidebar').append('<div id="emptycontent"><div id="emptycontent-icon" class="icon-loading"></div><h2></h2><p class="emptycontent-additional"></p></div>');
			$('#call-container').append('<div id="videos"></div>');
			$('#call-container').append('<div id="screens"></div>');

			OCA.SpreedMe.app.mainCallElementSelector = '#call-container';

			OCA.SpreedMe.app._emptyContentView = new OCA.SpreedMe.Views.EmptyContentView({
				el: '#talk-sidebar > #emptycontent'
			});

			OCA.SpreedMe.app._localVideoView.render();
			$('#videos').append(OCA.SpreedMe.app._localVideoView.$el);

			$('body').addClass('talk-sidebar-enabled');
		},

		requestPassword: function() {
			var self = this;

			var shareToken = $('#sharingToken').val();

			if (this.hideTalkSidebarTimeout) {
				clearTimeout(this.hideTalkSidebarTimeout);
				delete this.hideTalkSidebarTimeout;
			}

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'publicshareauth',
				type: 'POST',
				data: {
					shareToken: shareToken,
				},
				beforeSend: function(request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: function(ocsResponse) {
					self.setupRoom(ocsResponse.ocs.data.token);
				},
				error: function() {
					$('.request-password-wrapper .icon')
							.removeClass('icon-loading-small-dark')
							.addClass('icon-confirm-white');
					$('#request-password-button').prop('disabled', '');

					var errorMessage = $('.request-password-wrapper + .error-message');
					if (errorMessage.length > 0) {
						errorMessage.show();
					} else {
						$('.request-password-wrapper').after('<p class="warning error-message hidden">' + t('spreed', 'Error requesting the password.') + '</p>');
					}
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
					OCA.SpreedMe.app._chatView.$el.appendTo('#talk-sidebar');
					OCA.SpreedMe.app._chatView.setTooltipContainer($('body'));

					OCA.SpreedMe.app._emptyContentView.setActiveRoom(OCA.SpreedMe.app.activeRoom);

					setPageTitle(OCA.SpreedMe.app.activeRoom.get('displayName'));

					OCA.SpreedMe.app._chatView.setRoom(OCA.SpreedMe.app.activeRoom);
					OCA.SpreedMe.app._messageCollection.setRoomToken(OCA.SpreedMe.app.activeRoom.get('token'));
					OCA.SpreedMe.app._messageCollection.receiveMessages();

					self.showTalkSidebar();

					OCA.SpreedMe.app.connection.joinCall(joinedRoomToken);
				});
			});

			// TODO This should listen to "leaveRoom" on signaling instead, but
			// that would cause an ugly flicker due to the order in which the UI
			// elements would be modified (as the empty content message and the
			// "incall" CSS class are both modified when handling
			// "leaveCurrentRoom").
			roomsChannel.on('leaveCurrentRoom', function() {
				OCA.SpreedMe.app._chatView.$el.detach();

				self.leaveRoom();
			});
		},

		setupRoom: function(token) {
			OCA.SpreedMe.app.activeRoom = new OCA.SpreedMe.Models.Room({token: token});
			OCA.SpreedMe.app.signaling.setRoom(OCA.SpreedMe.app.activeRoom);

			OCA.SpreedMe.app.token = token;
			OCA.SpreedMe.app.signaling.joinRoom(token);
		},

		leaveRoom: function() {
			$('.request-password-wrapper .icon')
					.removeClass('icon-loading-small-dark')
					.addClass('icon-confirm-white');
			$('#request-password-button').prop('disabled', '');

			this.hideTalkSidebarTimeout = setTimeout(this.hideTalkSidebar, 5000);
		},

		showTalkSidebar: function() {
			$('#talk-sidebar').removeClass('disappear');
		},

		hideTalkSidebar: function() {
			$('#talk-sidebar').addClass('disappear');

			delete this.hideTalkSidebarTimeout;
		},
	};

	OCA.SpreedMe.app = new OCA.Talk.Embedded();

	OCA.SpreedMe.app.on('start', function() {
		OCA.Talk.PublicShareAuth.init();
	});

	// Unlike in the regular Talk app when Talk is embedded the signaling
	// settings are not initially included in the HTML, so they need to be
	// explicitly loaded before starting the app.
	OCA.Talk.Signaling.loadSettings().then(function() {
		OCA.SpreedMe.app.start();
	});

})(OCA);

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
	OCA.Talk.PublicShareAuth = {

		init: function() {
			var self = this;

			// this.setupRequestPasswordButton();
			this.setupCallButton();
			this.setupLayoutForTalkSidebar();

			$('#request-password-button').click(function() {
				$('.request-password-wrapper + .error-message').hide();

				$('#request-password-button').prop('disabled', 'true');

				$('.request-password-wrapper .icon')
						.removeClass('icon-confirm-white')
						.addClass('icon-loading-small-dark');

				self.requestPassword();
			});
		},

		setupCallButton: function() {
			var url = OC.generateUrl('apps/spreed/shareauth/' + $('#sharingToken').val());

			$('main').append('<div id="submit-wrapper" class="request-password-wrapper">' +
				'    <a href="' + url + '" target="_blank" class="primary button">' + t('spreed', 'Request password') + '</a>' +
				'    <div class="icon icon-confirm-white"></div>' +
				'</div>');
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
			$('body').append('<div id="content"></div>');
			$('#content').append($('.wrapper'));
			$('#content').append($('footer'));

			$('body').append('<div id="talk-sidebar" class="disappear"></div>');
			$('#talk-sidebar').append('<div id="emptycontent"><div id="emptycontent-icon" class="icon-loading"></div><h2></h2><p></p></div>');

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

		setupRoom: function(token) {
			var self = this;

			OCA.SpreedMe.app.activeRoom = new OCA.SpreedMe.Models.Room({token: token});
			OCA.SpreedMe.app.signaling.setRoom(OCA.SpreedMe.app.activeRoom);

			OCA.SpreedMe.app.signaling.on('leaveRoom', function(leftRoomToken) {
				if (token === leftRoomToken) {
					self.leaveRoom();
				}
			});

			// Prevent updateContentsLayout from executing, as it is not needed
			// when not having a full UI and messes with the tooltip container.
			OCA.SpreedMe.app.updateContentsLayout = function() {
			};

			OCA.SpreedMe.app.connection.joinRoom(token);

			OCA.SpreedMe.app._chatView.$el.prependTo('#talk-sidebar');
			OCA.SpreedMe.app._chatView.setTooltipContainer($('body'));

			OCA.SpreedMe.app.signaling.on('joinRoom', function() {
				self.showTalkSidebar();
			});
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

	OCA.SpreedMe.app = new OCA.Talk.Application();
	OCA.SpreedMe.app.start();

	OCA.Talk.PublicShareAuth.init();
})(OCA);

/* global Marionette */

/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

(function(OCA, Marionette) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	var VideoView = Marionette.View.extend({

		tagName: 'div',
		className: 'videoContainer',

		id: function() {
			return 'container_' + this.options.peerId + '_video_incoming';
		},

		template: OCA.Talk.Views.Templates['videoview'],

		ui: {
			'avatar': '.avatar',
			'hideRemoteVideoButton': '.hideRemoteVideo',
			'screenSharingIndicator': '.screensharingIndicator',
		},

		initialize: function() {
			this.render();

			this.getUI('avatar').addClass('icon-loading');
			this.getUI('avatar').css('opacity', '0.5');

			this.getUI('hideRemoteVideoButton').attr('data-original-title', t('spreed', 'Disable video'));
			this.getUI('hideRemoteVideoButton').hide();

			this.getUI('screenSharingIndicator').attr('data-original-title', t('spreed', 'Show screen'));
		},

		onRender: function() {
			this.getUI('hideRemoteVideoButton').get(0).onclick = function() {
				OCA.SpreedMe.videos._toggleRemoteVideo(this.options.peerId);
			}.bind(this);

			this.getUI('hideRemoteVideoButton').tooltip({
				placement: 'top',
				trigger: 'hover'
			});

			this.getUI('screenSharingIndicator').tooltip({
				placement: 'top',
				trigger: 'hover'
			});
		},

		setAvatar: function(userId, guestName) {
			if (userId && userId.length) {
				this.getUI('avatar').avatar(userId, 128);
			} else {
				this.getUI('avatar').imageplaceholder('?', guestName, 128);
				this.getUI('avatar').css('background-color', '#b9b9b9');
			}
		},

	});

	OCA.Talk.Views.VideoView = VideoView;

})(OCA, Marionette);

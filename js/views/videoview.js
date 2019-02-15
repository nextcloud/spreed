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

	var ConnectionStatus = {
		NEW: 'new',
		CHECKING: 'checking',
		CONNECTED: 'connected',
		COMPLETED: 'completed',
		DISCONNECTED: 'disconnected',
		DISCONNECTED_LONG: 'disconnected-long',
		FAILED: 'failed',
		FAILED_NO_RESTART: 'failed-no-restart',
		CLOSED: 'closed',
	};

	var VideoView = Marionette.View.extend({

		tagName: 'div',
		className: 'videoContainer',

		id: function() {
			return 'container_' + this.options.peerId + '_video_incoming';
		},

		template: OCA.Talk.Views.Templates['videoview'],

		ui: {
			'video': 'video',
			'avatarContainer': '.avatar-container',
			'avatar': '.avatar',
			'nameIndicator': '.nameIndicator',
			'muteIndicator': '.muteIndicator',
			'hideRemoteVideoButton': '.hideRemoteVideo',
			'screenSharingIndicator': '.screensharingIndicator',
			'iceFailedIndicator': '.iceFailedIndicator',
		},

		events: {
			'click @ui.screenSharingIndicator': 'switchToScreen',
		},

		initialize: function() {
			this._connectionStatus = ConnectionStatus.NEW;

			// Video is enabled by default, even if it is not initially
			// available.
			this._videoEnabled = true;
			this._screenVisible = false;

			this.render();

			this.getUI('avatar').addClass('icon-loading');
			this.getUI('avatar').css('opacity', '0.5');

			this.getUI('hideRemoteVideoButton').attr('data-original-title', t('spreed', 'Disable video'));
			this.getUI('hideRemoteVideoButton').hide();

			this.getUI('screenSharingIndicator').attr('data-original-title', t('spreed', 'Show screen'));
		},

		onRender: function() {
			this.getUI('hideRemoteVideoButton').get(0).onclick = function() {
				if (this._videoEnabled) {
					this.setVideoEnabled(false);
				} else {
					this.setVideoEnabled(true);
				}

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.options.peerId);
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

		setParticipant: function(userId, participantName) {
			if (userId && userId.length) {
				this.getUI('avatar').avatar(userId, 128);
			} else {
				this.getUI('avatar').imageplaceholder('?', participantName, 128);
				this.getUI('avatar').css('background-color', '#b9b9b9');

				// "Guest" placeholder is not shown until the initial connection
				// for consistency with regular users.
				if (this._connectionStatus !== ConnectionStatus.NEW) {
					participantName = participantName || t('spreed', 'Guest');
				}
			}

			this.getUI('nameIndicator').text(participantName);
		},

		/**
		 * Sets the current status of the connection.
		 *
		 * @param OCA.Talk.Views.VideoView.ConnectionStatus the connection
		 *        status.
		 */
		setConnectionStatus: function(connectionStatus) {
			this._connectionStatus = connectionStatus;

			this.getUI('avatar').removeClass('icon-loading');
			this.getUI('iceFailedIndicator').addClass('not-failed');

			if (connectionStatus === ConnectionStatus.CHECKING ||
					connectionStatus === ConnectionStatus.DISCONNECTED_LONG ||
					connectionStatus === ConnectionStatus.FAILED) {
				this.getUI('avatar').addClass('icon-loading');

				return;
			}

			if (connectionStatus === ConnectionStatus.CONNECTED ||
					connectionStatus === ConnectionStatus.COMPLETED) {
				this.getUI('avatar').css('opacity', '1');

				return;
			}

			if (connectionStatus === ConnectionStatus.FAILED_NO_RESTART) {
				this.getUI('muteIndicator').hide();
				this.getUI('hideRemoteVideoButton').hide();
				this.getUI('screenSharingIndicator').hide();
				this.getUI('iceFailedIndicator').removeClass('not-failed');

				return;
			}
		},

		setAudioAvailable: function(audioAvailable) {
			if (!audioAvailable) {
				this.getUI('muteIndicator')
						.removeClass('audio-on')
						.addClass('audio-off');
				this.setSpeaking(false);

				return;
			}

			this.getUI('muteIndicator')
					.removeClass('audio-off')
					.addClass('audio-on');
		},

		setSpeaking: function(speaking) {
			this.$el.toggleClass('speaking', speaking);
		},

		/**
		 * Sets the element with the video stream.
		 *
		 * @param HTMLVideoElement|null videoElement the element to set, or null
		 *        to remove the current one.
		 */
		setVideoElement: function(videoElement) {
			this.getUI('video').remove();

			if (videoElement) {
				this.$el.prepend(videoElement);

				videoElement.oncontextmenu = function() {
					return false;
				};
			}

			this.bindUIElements();
		},

		setVideoAvailable: function(videoAvailable) {
			if (!videoAvailable) {
				this.getUI('avatarContainer').show();
				this.getUI('video').hide();
				this.getUI('hideRemoteVideoButton').hide();

				return;
			}

			this.getUI('hideRemoteVideoButton').show();

			if (this._videoEnabled) {
				this.getUI('avatarContainer').hide();
				this.getUI('video').show();
			}
		},

		setVideoEnabled: function(videoEnabled) {
			this._videoEnabled = videoEnabled;

			if (!videoEnabled) {
				this.getUI('avatarContainer').show();
				this.getUI('video').hide();
				this.getUI('hideRemoteVideoButton')
						.attr('data-original-title', t('spreed', 'Enable video'))
						.removeClass('icon-video')
						.addClass('icon-video-off');

				return;
			}

			this.getUI('avatarContainer').hide();
			this.getUI('video').show();
			this.getUI('hideRemoteVideoButton')
					.attr('data-original-title', t('spreed', 'Disable video'))
					.removeClass('icon-video-off')
					.addClass('icon-video');
		},

		setScreenAvailable: function(screenAvailable) {
			if (!screenAvailable) {
				this.getUI('screenSharingIndicator')
						.removeClass('screen-on')
						.addClass('screen-off');

				return;
			}

			this.getUI('screenSharingIndicator')
					.removeClass('screen-off')
					.addClass('screen-on');
		},

		setScreenVisible: function(screenVisible) {
			this._screenVisible = screenVisible;

			this.getUI('screenSharingIndicator').toggleClass('screen-visible', screenVisible);
		},

		switchToScreen: function() {
			if (!this._screenVisible) {
				OCA.SpreedMe.sharedScreens.switchScreenToId(this.options.peerId);
			}

			this.getUI('screenSharingIndicator').tooltip('hide');
		},

	});

	OCA.Talk.Views.VideoView = VideoView;
	OCA.Talk.Views.VideoView.ConnectionStatus = ConnectionStatus;

})(OCA, Marionette);

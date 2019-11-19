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

		participantAvatarSize: 128,

		ui: {
			'audio': 'audio',
			'video': 'video',
			'avatarContainer': '.avatar-container',
			'avatar': '.avatar',
			'nameIndicator': '.nameIndicator',
			'mediaIndicator': '.mediaIndicator',
			'muteIndicator': '.muteIndicator',
			'hideRemoteVideoButton': '.hideRemoteVideo',
			'screenSharingIndicator': '.screensharingIndicator',
			'iceFailedIndicator': '.iceFailedIndicator',
		},

		events: {
			'click @ui.hideRemoteVideoButton': 'toggleVideo',
			'click @ui.screenSharingIndicator': 'switchToScreen',
		},

		initialize: function() {
			this._connectionStatus = ConnectionStatus.NEW;

			// Video is enabled by default, even if it is not initially
			// available.
			this._videoEnabled = true;
			this._screenVisible = false;

			this.render();

			this.$el.addClass('not-connected');

			this.getUI('avatar').addClass('icon-loading');

			this.getUI('hideRemoteVideoButton').attr('data-original-title', t('spreed', 'Disable video'));
			this.getUI('hideRemoteVideoButton').addClass('hidden');

			this.getUI('screenSharingIndicator').attr('data-original-title', t('spreed', 'Show screen'));
		},

		onRender: function() {
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
			// Needed for guest avatars, as if no name is given the avatar
			// should show "?" instead of the first letter of the "Guest"
			// placeholder.
			var rawParticipantName = participantName;

			// "Guest" placeholder is not shown until the initial connection for
			// consistency with regular users.
			if (!(userId && userId.length) && this._connectionStatus !== ConnectionStatus.NEW) {
				participantName = participantName || t('spreed', 'Guest');
			}

			if (this.hasOwnProperty('_userId') && this.hasOwnProperty('_rawParticipantName') && this.hasOwnProperty('_participantName') &&
					userId === this._userId && rawParticipantName === this._rawParticipantName && participantName === this._participantName) {
				// Do not set again the avatar if it has already been set to
				// workaround the MCU setting the participant again and again
				// and thus causing a loading icon to be shown on the avatar
				// again and again.
				return;
			}

			this._userId = userId;
			this._rawParticipantName = rawParticipantName;
			this._participantName = participantName;

			// Restore icon if needed after "avatar()" resets it.
			var restoreIconLoadingCallback = function() {
				if (this._connectionStatus === ConnectionStatus.NEW ||
						this._connectionStatus === ConnectionStatus.CHECKING ||
						this._connectionStatus === ConnectionStatus.DISCONNECTED_LONG ||
						this._connectionStatus === ConnectionStatus.FAILED) {
					this.getUI('avatar').addClass('icon-loading');
				}
			}.bind(this);

			if (userId && userId.length) {
				this.getUI('avatar').avatar(userId, this.participantAvatarSize, undefined, undefined, restoreIconLoadingCallback);
			} else {
				this.getUI('avatar').imageplaceholder('?', rawParticipantName, this.participantAvatarSize);
				this.getUI('avatar').css('background-color', '#b9b9b9');
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

			this.$el.addClass('not-connected');

			this.getUI('iceFailedIndicator').addClass('not-failed');

			if (connectionStatus === ConnectionStatus.CHECKING ||
					connectionStatus === ConnectionStatus.DISCONNECTED_LONG ||
					connectionStatus === ConnectionStatus.FAILED) {
				this.getUI('avatar').addClass('icon-loading');

				return;
			}

			this.getUI('avatar').removeClass('icon-loading');

			if (connectionStatus === ConnectionStatus.CONNECTED ||
					connectionStatus === ConnectionStatus.COMPLETED) {
				this.$el.removeClass('not-connected');

				return;
			}

			if (connectionStatus === ConnectionStatus.FAILED_NO_RESTART) {
				this.getUI('muteIndicator').addClass('hidden');
				this.getUI('hideRemoteVideoButton').addClass('hidden');
				this.getUI('screenSharingIndicator').addClass('hidden');
				this.getUI('iceFailedIndicator').removeClass('not-failed');

				return;
			}
		},

		/**
		 * Sets the element with the audio stream.
		 *
		 * @param HTMLVideoElement|null audioElement the element to set, or null
		 *        to remove the current one.
		 */
		setAudioElement: function(audioElement) {
			this.getUI('audio').remove();

			if (audioElement) {
				this.$el.prepend(audioElement);
			}

			this.bindUIElements();

			this.getUI('audio').addClass('hidden');
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

			// Hide the video until it is explicitly marked as available and
			// enabled.
			this.getUI('video').addClass('hidden');
		},

		setVideoAvailable: function(videoAvailable) {
			if (!videoAvailable) {
				this.getUI('avatarContainer').removeClass('hidden');
				this.getUI('video').addClass('hidden');
				this.getUI('hideRemoteVideoButton').addClass('hidden');

				return;
			}

			this.getUI('hideRemoteVideoButton').removeClass('hidden');

			if (this._videoEnabled) {
				this.getUI('avatarContainer').addClass('hidden');
				this.getUI('video').removeClass('hidden');
			}
		},

		setVideoEnabled: function(videoEnabled) {
			this._videoEnabled = videoEnabled;

			if (!videoEnabled) {
				this.getUI('avatarContainer').removeClass('hidden');
				this.getUI('video').addClass('hidden');
				this.getUI('hideRemoteVideoButton')
						.attr('data-original-title', t('spreed', 'Enable video'))
						.removeClass('icon-video')
						.addClass('icon-video-off');

				return;
			}

			this.getUI('avatarContainer').addClass('hidden');
			this.getUI('video').removeClass('hidden');
			this.getUI('hideRemoteVideoButton')
					.attr('data-original-title', t('spreed', 'Disable video'))
					.removeClass('icon-video-off')
					.addClass('icon-video');
		},

		toggleVideo: function() {
			if (this._videoEnabled) {
				this.setVideoEnabled(false);
			} else {
				this.setVideoEnabled(true);
			}

			OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.options.peerId);
		},

		setPromoted: function(promoted) {
			this.$el.toggleClass('promoted', promoted);
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

		/**
		 * Creates a dummy video container element to show the indicators when
		 * this video view is promoted.
		 *
		 * @return jQuery The jQuery wrapper for the dummy element.
		 */
		newDummyVideoContainer: function() {
			var $dummy = $('<div>')
					.addClass('videoContainer videoContainer-dummy')
					.append(this.getUI('nameIndicator').clone())
					.append(this.getUI('mediaIndicator').clone());

			// Cloning does not copy event handlers by default; it could be
			// forced with a parameter, but the tooltip would have to be
			// explicitly set on the new element anyway. Due to this the click
			// handler is explicitly copied too.
			$dummy.find('.hideRemoteVideo').click(this.toggleVideo.bind(this));
			$dummy.find('.hideRemoteVideo').tooltip({
				placement: 'top',
				trigger: 'hover'
			});

			return $dummy;
		},

	});

	OCA.Talk.Views.VideoView = VideoView;
	OCA.Talk.Views.VideoView.ConnectionStatus = ConnectionStatus;

})(OCA, Marionette);

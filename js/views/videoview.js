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

	var ConnectionState = OCA.Talk.Models.CallParticipantModel.ConnectionState;

	var VideoView = Marionette.View.extend({

		tagName: 'div',
		className: 'videoContainer',

		id: function() {
			return 'container_' + this.model.get('peerId') + '_video_incoming';
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

		modelEvents: {
			'change:connectionState': function(model, connectionState) {
				this._setConnectionState(connectionState);
				// "_setParticipant" depends on "connectionState"
				this._setParticipant(this._userId, this._rawParticipantName);
			},
			'change:userId': function(model, userId) {
				this._setParticipant(userId, this.model.get('name'));
			},
			'change:name': function(model, name) {
				this._setParticipant(this.model.get('userId'), name);
			},
			'change:stream': '_setStream',
			'change:audioAvailable': '_setAudioAvailable',
			'change:videoAvailable': '_setVideoAvailable',
		},

		initialize: function() {
			// Video is enabled by default, even if it is not initially
			// available.
			this._videoEnabled = true;
			this._screenVisible = false;

			this.render();
		},

		onRender: function() {
			this.getUI('hideRemoteVideoButton').attr('data-original-title', t('spreed', 'Disable video'));

			this.getUI('screenSharingIndicator').attr('data-original-title', t('spreed', 'Show screen'));

			// Match current model state.
			this._setConnectionState(this.model.get('connectionState'));
			this._setParticipant(this.model.get('userId'), this.model.get('name'));
			this._setStream(this.model, this.model.get('stream'));
			this._setAudioAvailable(this.model, this.model.get('audioAvailable'));
			this._setVideoAvailable(this.model, this.model.get('videoAvailable'));

			this.getUI('hideRemoteVideoButton').tooltip({
				placement: 'top',
				trigger: 'hover'
			});

			this.getUI('screenSharingIndicator').tooltip({
				placement: 'top',
				trigger: 'hover'
			});
		},

		_setParticipant: function(userId, participantName) {
			// Needed for guest avatars, as if no name is given the avatar
			// should show "?" instead of the first letter of the "Guest"
			// placeholder.
			var rawParticipantName = participantName;

			// "Guest" placeholder is not shown until the initial connection for
			// consistency with regular users.
			if (!(userId && userId.length) && this.model.get('connectionState') !== ConnectionState.NEW) {
				participantName = participantName || t('spreed', 'Guest');
			}

			// Restore icon if needed after "avatar()" resets it.
			var restoreIconLoadingCallback = function() {
				if (this.model.get('connectionState') === ConnectionState.NEW ||
						this.model.get('connectionState') === ConnectionState.CHECKING ||
						this.model.get('connectionState') === ConnectionState.DISCONNECTED_LONG ||
						this.model.get('connectionState') === ConnectionState.FAILED) {
					this.getUI('avatar').addClass('icon-loading');
				}
			}.bind(this);

			if (userId && userId.length) {
				this.getUI('avatar').avatar(userId, this.participantAvatarSize, undefined, undefined, restoreIconLoadingCallback);
			} else if (userId !== undefined) {
				this.getUI('avatar').imageplaceholder('?', rawParticipantName, this.participantAvatarSize);
				this.getUI('avatar').css('background-color', '#b9b9b9');
			}

			if (rawParticipantName !== undefined) {
				this.getUI('nameIndicator').text(participantName);
			}

			OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));
		},

		/**
		 * Sets the current state of the connection.
		 *
		 * @param OCA.Talk.Models.CallParticipantModel.ConnectionState the
		 *        connection state.
		 */
		_setConnectionState: function(connectionState) {
			this.$el.addClass('not-connected');

			this.getUI('iceFailedIndicator').addClass('not-failed');

			if (connectionState === ConnectionState.NEW ||
					connectionState === ConnectionState.CHECKING ||
					connectionState === ConnectionState.DISCONNECTED_LONG ||
					connectionState === ConnectionState.FAILED) {
				this.getUI('avatar').addClass('icon-loading');

				return;
			}

			this.getUI('avatar').removeClass('icon-loading');

			if (connectionState === ConnectionState.CONNECTED ||
					connectionState === ConnectionState.COMPLETED) {
				this.$el.removeClass('not-connected');

				return;
			}

			if (connectionState === ConnectionState.FAILED_NO_RESTART) {
				this.getUI('muteIndicator').addClass('hidden');
				this.getUI('hideRemoteVideoButton').addClass('hidden');
				this.getUI('screenSharingIndicator').addClass('hidden');
				this.getUI('iceFailedIndicator').removeClass('not-failed');

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));

				return;
			}
		},

		_setStream: function(model, stream) {
			if (!stream) {
				this._setAudioElement(null);
				this._setVideoElement(null);

				return;
			}

			// If there is a video track Chromium does not play audio in a video
			// element until the video track starts to play; an audio element is
			// thus needed to play audio when the remote peer starts with the
			// camera available but disabled.
			var audio = OCA.Talk.Views.attachMediaStream(stream, null, { audio: true });
			var video = OCA.Talk.Views.attachMediaStream(stream);

			video.muted = true;

			// At least Firefox, Opera and Edge move the video to a wrong
			// position instead of keeping it unchanged	when
			// "transform: scaleX(1)" is used ("transform: scaleX(-1)" is fine);
			// as it should have no effect the transform is removed.
			if (video.style.transform === 'scaleX(1)') {
				video.style.transform = '';
			}

			this._setAudioElement(audio);
			this._setVideoElement(video);
		},

		/**
		 * Sets the element with the audio stream.
		 *
		 * @param HTMLVideoElement|null audioElement the element to set, or null
		 *        to remove the current one.
		 */
		_setAudioElement: function(audioElement) {
			this.getUI('audio').remove();

			if (audioElement) {
				this.$el.prepend(audioElement);
			}

			this.bindUIElements();

			this.getUI('audio').addClass('hidden');
		},

		_setAudioAvailable: function(model, audioAvailable) {
			if (audioAvailable === undefined) {
				this.getUI('muteIndicator')
						.removeClass('audio-on')
						.removeClass('audio-off');

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));

				return;
			}

			if (!audioAvailable) {
				this.getUI('muteIndicator')
						.removeClass('audio-on')
						.addClass('audio-off');
				this.setSpeaking(false);

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));

				return;
			}

			this.getUI('muteIndicator')
					.removeClass('audio-off')
					.addClass('audio-on');

			OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));
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
		_setVideoElement: function(videoElement) {
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

		_setVideoAvailable: function(model, videoAvailable) {
			if (!videoAvailable) {
				this.getUI('avatarContainer').removeClass('hidden');
				this.getUI('video').addClass('hidden');
				this.getUI('hideRemoteVideoButton').addClass('hidden');

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));

				return;
			}

			this.getUI('hideRemoteVideoButton').removeClass('hidden');

			if (this._videoEnabled) {
				this.getUI('avatarContainer').addClass('hidden');
				this.getUI('video').removeClass('hidden');
			}

			OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));
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

				OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));

				return;
			}

			this.getUI('avatarContainer').addClass('hidden');
			this.getUI('video').removeClass('hidden');
			this.getUI('hideRemoteVideoButton')
					.attr('data-original-title', t('spreed', 'Disable video'))
					.removeClass('icon-video-off')
					.addClass('icon-video');

			OCA.SpreedMe.speakers.updateVideoContainerDummyIfLatestSpeaker(this.model.get('peerId'));
		},

		toggleVideo: function() {
			if (this._videoEnabled) {
				this.setVideoEnabled(false);
			} else {
				this.setVideoEnabled(true);
			}
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
				OCA.SpreedMe.sharedScreens.switchScreenToId(this.model.get('peerId'));
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

})(OCA, Marionette);

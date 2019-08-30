/* global Marionette */

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

(function(OC, OCA, Marionette) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	var MediaControlsView  = Marionette.View.extend({

		tagName: 'div',
		className: 'nameIndicator',

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['mediacontrolsview'](context);
		},

		templateContext: function() {
			return {
				muteAudioButtonTitle: t('spreed', 'Mute audio'),
				hideVideoButtonTitle: t('spreed', 'Disable video'),
				screensharingButtonTitle: t('spreed', 'Share screen'),
				shareScreenButtonTitle: t('spreed', 'Share whole screen'),
				shareWindowButtonTitle: t('spreed', 'Share a single window'),
				showScreenButtonTitle: t('spreed', 'Show your screen'),
				stopScreenButtonTitle: t('spreed', 'Stop screensharing')
			};
		},

		ui: {
			'audioButton': '#mute',
			'volumeIndicator': '#muteWrapper .volume-indicator',
			'videoButton': '#hideVideo',
			'screensharingButton': '#screensharing-button',
			'screensharingMenu': '#screensharing-menu',
			'shareScreenEntry': '#share-screen-entry',
			'shareScreenButton': '#share-screen-button',
			'shareWindowEntry': '#share-window-entry',
			'shareWindowButton': '#share-window-button',
			'showScreenEntry': '#show-screen-entry',
			'showScreenButton': '#show-screen-button',
			'stopScreenEntry': '#stop-screen-entry',
			'stopScreenButton': '#stop-screen-button',
		},

		events: {
			'click @ui.audioButton': 'toggleAudio',
			'click @ui.videoButton': 'toggleVideo',
			'click @ui.screensharingButton': 'toggleScreensharingMenu',
			'click @ui.shareScreenButton': 'shareScreen',
			'click @ui.shareWindowButton': 'shareWindow',
			'click @ui.showScreenButton': 'showScreen',
			'click @ui.stopScreenButton': 'stopScreen',
		},

		initialize: function(options) {
			this._app = options.app;
			this._webrtc = options.webrtc;
			this._sharedScreens = options.sharedScreens;

			this._handleVolumeChangeBound = this._handleVolumeChange.bind(this);

			this._audioAvailable = true;
			this._videoAvailable = true;

			this.audioEnabled = !localStorage.getItem('audioDisabled');
			this.videoEnabled = !localStorage.getItem('videoDisabled');
		},

		setWebRtc: function(webrtc) {
			if (this._webrtc && this._webrtc.webrtc) {
				this._webrtc.webrtc.off('volumeChange', this._handleVolumeChangeBound);
			}

			this._webrtc = webrtc;

			this._webrtc.webrtc.on('volumeChange', this._handleVolumeChangeBound);
		},

		setSharedScreens: function(sharedScreens) {
			this._sharedScreens = sharedScreens;
		},

		toggleAudio: function() {
			if (!this._audioAvailable) {
				return;
			}

			if (this.audioEnabled) {
				this.setAudioEnabled(false);
				localStorage.setItem('audioDisabled', true);
			} else {
				this.setAudioEnabled(true);
				localStorage.removeItem('audioDisabled');
			}
		},

		setAudioEnabled: function(audioEnabled) {
			if (!this._audioAvailable || !this._webrtc) {
				return;
			}

			if (audioEnabled) {
				this._webrtc.unmute();

				this.getUI('audioButton').attr('data-original-title', t('spreed', 'Mute audio (m)'))
					.removeClass('audio-disabled icon-audio-off')
					.addClass('icon-audio');
			} else {
				this._webrtc.mute();

				this.getUI('audioButton').attr('data-original-title', t('spreed', 'Unmute audio (m)'))
					.addClass('audio-disabled icon-audio-off')
					.removeClass('icon-audio');
			}

			this.audioEnabled = audioEnabled;
		},

		/**
		 * Sets the audio as available or not available.
		 *
		 * "setAudioEnabled(bool)" is expected to be called with the appropriate
		 * value after the audio is set as available.
		 */
		setAudioAvailable: function(audioAvailable) {
			if (audioAvailable) {
				this.getUI('audioButton').removeClass('no-audio-available');

				this.getUI('volumeIndicator').removeClass('hidden');
			} else {
				this.getUI('audioButton').removeClass('audio-disabled icon-audio')
					.addClass('no-audio-available icon-audio-off')
					.attr('data-original-title', t('spreed', 'No audio'));

				this.getUI('volumeIndicator').addClass('hidden');
			}

			this._audioAvailable = audioAvailable;
		},

		setSpeakingWhileMutedNotification: function(message) {
			if (!message) {
				this.getUI('audioButton').tooltip('dispose');

				return;
			}

			this.getUI('audioButton').tooltip('hide')
					.attr('data-original-title', message)
					.tooltip('_fixTitle')
					.tooltip({placement: 'bottom', trigger: 'manual'})
					.tooltip('show');
		},

		_handleVolumeChange: function(currentVolume, threshold) {
			// WebRTC volume goes from -100 (silence) to 0 (loudest sound in the
			// system); for the volume indicator only sounds above the threshold
			// are taken into account.
			var currentVolumeProportion = 0;
			if (currentVolume > threshold) {
				currentVolumeProportion = (threshold - currentVolume) / threshold;
			}

			var maximumVolumeIndicatorHeight = this.getUI('volumeIndicator').parent().outerHeight() - (parseInt(this.getUI('volumeIndicator').css('bottom'), 10) * 2);

			this.getUI('volumeIndicator').height(maximumVolumeIndicatorHeight * currentVolumeProportion);
		},

		toggleVideo: function() {
			if (!this._videoAvailable) {
				return;
			}

			if (this.videoEnabled) {
				this._app.setVideoEnabled(false);
				localStorage.setItem('videoDisabled', true);
			} else {
				this._app.setVideoEnabled(true);
				localStorage.removeItem('videoDisabled');
			}
		},

		setVideoEnabled: function(videoEnabled) {
			if (!this._videoAvailable || !this._webrtc) {
				return false;
			}

			if (videoEnabled) {
				this._webrtc.resumeVideo();

				this.getUI('videoButton').attr('data-original-title', t('spreed', 'Disable video (v)'))
					.removeClass('local-video-disabled video-disabled icon-video-off')
					.addClass('icon-video');
				this.getUI('audioButton').removeClass('local-video-disabled');
				this.getUI('screensharingButton').removeClass('local-video-disabled');
			} else {
				this._webrtc.pauseVideo();

				this.getUI('videoButton').attr('data-original-title', this._getEnableVideoButtonTitle())
					.addClass('local-video-disabled video-disabled icon-video-off')
					.removeClass('icon-video');
				this.getUI('audioButton').addClass('local-video-disabled');
				this.getUI('screensharingButton').addClass('local-video-disabled');
			}

			this.videoEnabled = videoEnabled;

			return true;
		},

		_getEnableVideoButtonTitle: function() {
			if (!this._app.signaling || this._app.signaling.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video (v)');
			}

			return t('spreed', 'Enable video (v) - Your connection will be briefly interrupted when enabling the video for the first time');
		},

		/**
		 * Sets the video as available or not available.
		 *
		 * "setVideoEnabled(bool)" is expected to be called with the appropriate
		 * value after the video is set as available.
		 */
		setVideoAvailable: function(videoAvailable) {
			if (videoAvailable) {
				this.getUI('videoButton').removeClass('no-video-available');
			} else {
				this.getUI('videoButton').removeClass('icon-video')
					.addClass('no-video-available icon-video-off')
					.attr('data-original-title', t('spreed', 'No Camera'));
			}

			this._videoAvailable = videoAvailable;
		},

		toggleScreensharingMenu: function() {
			if (!this._webrtc.capabilities.supportScreenSharing) {
				if (window.location.protocol === 'https:') {
					OC.Notification.showTemporary(t('spreed', 'Screensharing is not supported by your browser.'));
				} else {
					OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
				}
				return;
			}

			// The standard "getDisplayMedia" does not support pre-filtering the
			// type of display sources, so the unified menu is used in that case
			// too.
			var splitShare = false;
			if (window.navigator.userAgent.match('Firefox') && !window.navigator.mediaDevices.getDisplayMedia) {
				var ffver = parseInt(window.navigator.userAgent.match(/Firefox\/(.*)/)[1], 10);
				splitShare = (ffver >= 52);
			}

			if (this._webrtc.getLocalScreen()) {
				this.getUI('shareScreenEntry').addClass('hidden');
				this.getUI('shareWindowEntry').addClass('hidden');
				this.getUI('showScreenEntry').removeClass('hidden');
				this.getUI('stopScreenEntry').removeClass('hidden');
				this.getUI('screensharingMenu').toggleClass('open');
			} else {
				if (splitShare) {
					this.getUI('shareScreenEntry').removeClass('hidden');
					this.getUI('shareWindowEntry').removeClass('hidden');
					this.getUI('showScreenEntry').addClass('hidden');
					this.getUI('stopScreenEntry').addClass('hidden');
					this.getUI('screensharingMenu').toggleClass('open');
					return;
				}

				this.startShareScreen();
			}
		},

		shareScreen: function() {
			if (!this._webrtc.getLocalScreen()) {
				this.startShareScreen('screen');
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		shareWindow: function() {
			if (!this._webrtc.getLocalScreen()) {
				this.startShareScreen('window');
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		showScreen: function() {
			if (this._webrtc.getLocalScreen()) {
				var currentUser = this._webrtc.connection.getSessionid();
				this._sharedScreens.switchScreenToId(currentUser);
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		stopScreen: function() {
			this._webrtc.stopScreenShare();
		},

		startShareScreen: function(mode) {
			this.getUI('screensharingButton').prop('disabled', true);

			this._webrtc.shareScreen(mode, function(err) {
				this.getUI('screensharingButton').prop('disabled', false);
				if (!err) {
					this.getUI('screensharingButton').attr('data-original-title', t('spreed', 'Screensharing options'))
						.removeClass('screensharing-disabled icon-screen-off')
						.addClass('icon-screen');
					return;
				}

				switch (err.name) {
					case 'HTTPS_REQUIRED':
						OC.Notification.showTemporary(t('spreed', 'Screensharing requires the page to be loaded through HTTPS.'));
						break;
					case 'PERMISSION_DENIED':
					case 'NotAllowedError':
					case 'CEF_GETSCREENMEDIA_CANCELED':  // Experimental, may go away in the future.
						break;
					case 'FF52_REQUIRED':
						OC.Notification.showTemporary(t('spreed', 'Sharing your screen only works with Firefox version 52 or newer.'));
						break;
					case 'EXTENSION_UNAVAILABLE':
						var  extensionURL = null;
						if (window.chrome) {// Chrome
							extensionURL = 'https://chrome.google.com/webstore/detail/screensharing-for-nextclo/kepnpjhambipllfmgmbapncekcmabkol';
						}

						if (extensionURL) {
							var text = t('spreed', 'Screensharing extension is required to share your screen.');
							var element = '<a href="' + extensionURL + '" target="_blank">' + escapeHTML(text) + '</a>';

							OC.Notification.showTemporary(element, {isHTML: true});
						} else {
							OC.Notification.showTemporary(t('spreed', 'Please use a different browser like Firefox or Chrome to share your screen.'));
						}
						break;
					default:
						OC.Notification.showTemporary(t('spreed', 'An error occurred while starting screensharing.'));
						console.log('Could not start screensharing', err);
						break;
				}
			}.bind(this));
		},

		disableScreensharingButton: function() {
			this.getUI('screensharingButton').attr('data-original-title', t('spreed', 'Enable screensharing'))
					.addClass('screensharing-disabled icon-screen-off')
					.removeClass('icon-screen');
			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		hideScreensharingButton: function() {
			this.getUI('screensharingButton').addClass('hidden');
		},

	});

	OCA.SpreedMe.Views.MediaControlsView = MediaControlsView;

})(OC, OCA, Marionette);

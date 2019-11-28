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

		modelEvents: {
			'change:audioAvailable': '_setAudioAvailable',
			'change:audioEnabled': '_setAudioEnabled',
			'change:volume': '_setVolume',
			'change:videoAvailable': '_setVideoAvailable',
			'change:videoEnabled': '_setVideoEnabled',
			'change:sharedScreenId': '_setSharedScreenId',
		},

		initialize: function(options) {
			this._app = options.app;
			this._sharedScreens = options.sharedScreens;
		},

		setSharedScreens: function(sharedScreens) {
			this._sharedScreens = sharedScreens;
		},

		onRender: function() {
			// Match current model state.
			this._setAudioAvailable(this.model, this.model.get('audioAvailable'));
			this._setVideoAvailable(this.model, this.model.get('videoAvailable'));
			this._setSharedScreenId(this.model, this.model.get('sharedScreenId'));
		},

		toggleAudio: function() {
			if (!this.model.get('audioAvailable')) {
				return;
			}

			if (this.model.get('audioEnabled')) {
				this.model.disableAudio();
			} else {
				this.model.enableAudio();
			}
		},

		_setAudioEnabled: function(model, audioEnabled) {
			if (audioEnabled) {
				this.getUI('audioButton').attr('data-original-title', t('spreed', 'Mute audio (m)'))
					.removeClass('audio-disabled icon-audio-off')
					.addClass('icon-audio');
			} else {
				this.getUI('audioButton').attr('data-original-title', t('spreed', 'Unmute audio (m)'))
					.addClass('audio-disabled icon-audio-off')
					.removeClass('icon-audio');
			}
		},

		_setAudioAvailable: function(model, audioAvailable) {
			if (audioAvailable) {
				this.getUI('audioButton').removeClass('no-audio-available');

				this.getUI('volumeIndicator').removeClass('hidden');

				this._setAudioEnabled(model, model.get('audioEnabled'));
			} else {
				this.getUI('audioButton').removeClass('audio-disabled icon-audio')
					.addClass('no-audio-available icon-audio-off')
					.attr('data-original-title', t('spreed', 'No audio'));

				this.getUI('volumeIndicator').addClass('hidden');
			}
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

		_setVolume: function(currentVolume, threshold) {
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
			if (!this.model.get('videoAvailable')) {
				return;
			}

			if (this.model.get('videoEnabled')) {
				this.model.disableVideo();
			} else {
				this.model.enableVideo();
			}
		},

		_setVideoEnabled: function(model, videoEnabled) {
			if (videoEnabled) {
				this.getUI('videoButton').attr('data-original-title', t('spreed', 'Disable video (v)'))
					.removeClass('local-video-disabled video-disabled icon-video-off')
					.addClass('icon-video');
				this.getUI('audioButton').removeClass('local-video-disabled');
				this.getUI('screensharingButton').removeClass('local-video-disabled');
			} else {
				this.getUI('videoButton').attr('data-original-title', this._getEnableVideoButtonTitle())
					.addClass('local-video-disabled video-disabled icon-video-off')
					.removeClass('icon-video');
				this.getUI('audioButton').addClass('local-video-disabled');
				this.getUI('screensharingButton').addClass('local-video-disabled');
			}
		},

		_getEnableVideoButtonTitle: function() {
			if (!this._app.signaling || this._app.signaling.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video (v)');
			}

			return t('spreed', 'Enable video (v) - Your connection will be briefly interrupted when enabling the video for the first time');
		},

		_setVideoAvailable: function(model, videoAvailable) {
			if (videoAvailable) {
				this.getUI('videoButton').removeClass('no-video-available');

				this._setVideoEnabled(model, model.get('videoEnabled'));
			} else {
				this.getUI('videoButton').removeClass('icon-video')
					.addClass('no-video-available icon-video-off')
					.attr('data-original-title', t('spreed', 'No Camera'));
			}
		},

		toggleScreensharingMenu: function() {
			if (!this.model.getWebRtc().capabilities.supportScreenSharing) {
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

			if (this.model.get('sharedScreenId')) {
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
			if (!this.model.get('sharedScreenId')) {
				this.startShareScreen('screen');
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		shareWindow: function() {
			if (!this.model.get('sharedScreenId')) {
				this.startShareScreen('window');
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		showScreen: function() {
			if (this.model.get('sharedScreenId')) {
				this._sharedScreens.switchScreenToId(this.model.get('sharedScreenId'));
			}

			this.getUI('screensharingMenu').toggleClass('open', false);
		},

		stopScreen: function() {
			this.model.stopSharingScreen();
		},

		startShareScreen: function(mode) {
			this.getUI('screensharingButton').prop('disabled', true);

			this.model.shareScreen(mode, function(err) {
				this.getUI('screensharingButton').prop('disabled', false);
				if (!err) {
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

		_setSharedScreenId: function(model, sharedScreenId) {
			if (sharedScreenId) {
				this.getUI('screensharingButton').attr('data-original-title', t('spreed', 'Screensharing options'))
					.removeClass('screensharing-disabled icon-screen-off')
					.addClass('icon-screen');

				return;
			}

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

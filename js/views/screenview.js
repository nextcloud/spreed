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

	var ScreenView = Marionette.View.extend({

		tagName: 'div',
		className: 'screenContainer',

		id: function() {
			return this.options.callParticipantModel? 'container_' + this.options.callParticipantModel.get('peerId') + '_screen_incoming': 'localScreenContainer';
		},

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['screenview'](context);
		},

		ui: {
			'video': 'video',
			'nameIndicator': '.nameIndicator',
		},

		initialize: function(options) {
			this._localMediaModel = options.localMediaModel;
			this._callParticipantModel = options.callParticipantModel;

			this.listenTo(this._localMediaModel, 'change:localScreen', this._setScreen);
			this.listenTo(this._callParticipantModel, 'change:screen', this._setScreen);

			this.render();

			if (this._localMediaModel) {
				this.getUI('nameIndicator').text(t('spreed', 'Your screen'));
			}
		},

		onRender: function() {
			// Match current model state.
			if (this._localMediaModel) {
				this._setScreen(this._localMediaModel, this._localMediaModel.get('localScreen'));
			} else {
				this._setScreen(this._callParticipantModel, this._callParticipantModel.get('screen'));
			}
		},

		setParticipantName: function(participantName) {
			if (this._localMediaModel) {
				return;
			}

			var nameIndicator;
			if (participantName) {
				nameIndicator = t('spreed', "{participantName}'s screen", {participantName: participantName});
			} else {
				nameIndicator = t('spreed', "Guest's screen");
			}

			this.getUI('nameIndicator').text(nameIndicator);
		},

		_setScreen: function(model, screen) {
			if (!screen) {
				this._setVideoElement(null);

				return;
			}

			this._setVideoElement(OCA.Talk.Views.attachMediaStream(screen));
		},

		/**
		 * Sets the element with the video stream.
		 *
		 * @param {HTMLVideoElement|null} videoElement the element to set, or null
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
		},

	});

	OCA.Talk.Views.ScreenView = ScreenView;

})(OCA, Marionette);

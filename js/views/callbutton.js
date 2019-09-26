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

(function(OCA, Marionette) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.Talk = OCA.Talk || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};
	OCA.Talk.Views = OCA.Talk.Views || {};

	var roomsChannel = Backbone.Radio.channel('rooms');

	var CallButton = Marionette.View.extend({

		className: 'call-button',

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['callbutton'](context);
		},

		templateContext: function() {
			return {
				isReadOnly: this.model.get('readOnly') === 1,
				isInCall: (this.model.get('participantFlags') & OCA.SpreedMe.app.FLAG_IN_CALL) !== 0,
				canStartCall: this.model.get('canStartCall'),
				hasCall: this.model.get('hasCall'),
				leaveCallText: t('spreed', 'Leave call'),
				joinCallText: t('spreed', 'Join call'),
				startCallText: t('spreed', 'Start call'),
				readOnlyText: t('spreed', 'Calls are disabled in this conversation.'),
			};
		},

		ui: {
			'joinCallButton': 'button.join-call',
			'leaveCallButton': 'button.leave-call',
			'workingIcon': '.icon-loading-small',
		},

		events: {
			'click @ui.joinCallButton': 'joinCall',
			'click @ui.leaveCallButton': 'leaveCall',
		},

		modelEvents: {
			'change:canStartCall': function() {
				this.render();
			},
			'change:hasCall': function() {
				this.render();
			},
			'change:participantFlags': function() {
				this.render();
			},
			'change:readOnly': function() {
				this.render();
			},
		},

		/**
		 * @param {OCA.SpreedMe.Models.Room} options.model
		 * @param {OCA.Talk.Connection} options.connection
		 */
		initialize: function(options) {
			this._connection = options.connection;

			// While joining or leaving a call the button is disabled; it will
			// be rendered again and thus enabled once the operation finishes
			// and the model changes.
			this.listenTo(roomsChannel, 'joinCall', this._waitForCallToBeJoined);
			this.listenTo(roomsChannel, 'leaveCurrentCall', this._waitForCallToBeLeft);
		},

		joinCall: function() {
			this._connection.joinCall(this.model.get('token'));
		},

		leaveCall: function() {
			this._connection.leaveCurrentCall();
		},

		_waitForCallToBeJoined: function() {
			this.getUI('joinCallButton').prop('disabled', true);
			this.getUI('workingIcon').removeClass('hidden');
		},

		_waitForCallToBeLeft: function() {
			this.getUI('leaveCallButton').prop('disabled', true);
			this.getUI('workingIcon').removeClass('hidden');
		},

	});

	OCA.SpreedMe.Views.CallButton = CallButton;

})(OCA, Marionette);

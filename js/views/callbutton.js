/* global Marionette, Handlebars */

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

(function(OCA, Marionette, Handlebars) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'{{#if isInCall}}' +
		'	<button class="leave-call primary">' + t('spreed', 'Leave call') + '</button>' +
		'{{else}}' +
		'	{{#if hasCall}}' +
		'	<button class="join-call call-ongoing primary">' + t('spreed', 'Join call') + '</button>' +
		'	{{else}}' +
		'	<button class="join-call primary">' + t('spreed', 'Start call') + '</button>' +
		'	{{/if}}' +
		'{{/if}}';

	var CallButton  = Marionette.View.extend({

		className: 'call-button',

		template: Handlebars.compile(TEMPLATE),

		templateContext: function() {
			return {
				isInCall: (this.model.get('participantFlags') & OCA.SpreedMe.app.FLAG_IN_CALL) !== 0,
				hasCall: this.model.get('hasCall'),
			};
		},

		ui: {
			'joinCallButton': 'button.join-call',
			'leaveCallButton': 'button.leave-call',
		},

		events: {
			'click @ui.joinCallButton': 'joinCall',
			'click @ui.leaveCallButton': 'leaveCall',
		},

		modelEvents: {
			'change:hasCall': function() {
				this.render();
			},
			'change:participantFlags': function() {
				this.render();
			},
		},

		/**
		 * @param {OCA.SpreedMe.Models.Room} options.model
		 * @param {OCA.Talk.Connection} options.connection
		 */
		initialize: function(options) {
			this._connection = options.connection;
		},

		joinCall: function() {
			this._connection.joinCall(this.model.get('token'));
		},

		leaveCall: function() {
			this._connection.leaveCurrentCall();
		},

	});

	OCA.SpreedMe.Views.CallButton = CallButton;

})(OCA, Marionette, Handlebars);

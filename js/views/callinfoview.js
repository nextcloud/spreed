/* global Marionette, Handlebars */

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
		'<span class="room-name">{{displayName}}</span>';

	var CallInfoView  = Marionette.View.extend({

		tagName: 'h3',

		template: Handlebars.compile(TEMPLATE),
		templateContext: function() {
			return {
				displayName: this.model.get('displayName')
			};
		},

		modelEvents: {
			'change:displayName': function() {
				this.render();
			},
		}

	});

	OCA.SpreedMe.Views.CallInfoView = CallInfoView;

})(OCA, Marionette, Handlebars);

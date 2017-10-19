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

(function(OC, OCA, Marionette, Handlebars) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var TEMPLATE =
		'<button class="add-person-button">' +
			'<span class="icon-add"></span>' +
			'<span>' + t('spreed', 'Add person') + '</span>' +
		'</button>' +
		'<ul class="participantWithList">' +
		'</ul>';

	OCA.SpreedMe.Views.ParticipantView = Marionette.View.extend({

		tagName: 'div',

		ui: {
			addButton: '.add-person-button',
			participantList: '.participantWithList'
		},

		regions: {
			participantList: '@ui.participantList'
		},

		template: Handlebars.compile(TEMPLATE),

		initialize: function(options) {
			this._participantListView = new OCA.SpreedMe.Views.ParticipantListView({ collection: options.collection });

			// In Marionette 3.0 the view is not rendered automatically if
			// needed when showing a child view, so it must be rendered
			// explicitly to ensure that the DOM element in which the child view
			// will be appended exists.
			this.render();
			this.showChildView('participantList', this._participantListView, { replaceElement: true } );
		}

	});

})(OC, OCA, Marionette, Handlebars);

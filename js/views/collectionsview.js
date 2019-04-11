/* global Marionette */

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

(function(OC, OCA, Marionette) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	OCA.SpreedMe.Views.CollectionsView = Marionette.View.extend({

		template: function(context) {
			// OCA.Talk.Views.Templates may not have been initialized when this
			// view is initialized, so the template can not be directly
			// assigned.
			return OCA.Talk.Views.Templates['collectionsview'](context);
		},

		initialize: function(options) {
			this.room = options.room;
			this.render();
		},

		/**
		 * @param {OCA.SpreedMe.Models.Room} room
		 */
		setRoom: function(room) {
			this.room = room;
			OCA.Talk.CollectionsTabView.setRoomModel(this.room);
		},

		onAttach: function () {
			OCA.Talk.CollectionsTabView.init(this.el, this.room);
		}

	});

})(OC, OCA, Marionette);

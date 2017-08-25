/* global Backbone, OC, OCA */

/**
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

(function(OCA, OC, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Models = OCA.SpreedMe.Models || {};

	OCA.SpreedMe.Models.ParticipantCollection = Backbone.Collection.extend({
		initialize: function() {
			console.log("initialize");
			console.log(arguments);
		},
		model: OCA.SpreedMe.Models.Participant,
		room: undefined,

		/**
		 * @param {OCA.SpreedMe.Models.Room} room
		 * @returns {Array}
		 */
		setRoom: function(room) {
			this.room = room;
			this.url = OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.room.get('token') + '/participants';
		},
		// comparator: function(model) {
		// 	return -(model.get('lastPing'));
		// },
		/**
		 * @param result
		 * @returns {Array}
		 */
		parse: function(result) {
			console.log(result);
			return result.ocs.data;
		}
	});

})(OCA, OC, Backbone);

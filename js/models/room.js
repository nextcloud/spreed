/* global Backbone, OCA */

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

(function(OCA, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Models = OCA.SpreedMe.Models || {};

	/**
	 * Model for rooms.
	 *
	 * Room can be used as the model of a RoomCollection or as a standalone
	 * model. When used as a standalone model the token must be provided in the
	 * constructor options.
	 */
	var Room = Backbone.Model.extend({
		defaults: {
			name: '',
			token: '',
			count: 0,
			active: false,
			lastPing: 0
		},
		url: function() {
			return OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.get('token');
		},
		parse: function(result) {
			// When the model is created by a RoomCollection "Room.parse" will
			// be called with the result already parsed by
			// "RoomCollection.parse", so the given result is already the
			// attributes hash to be set on the model.
			return (result.ocs === undefined)? result : result.ocs.data;
		}
	});

	OCA.SpreedMe.Models.Room = Room;

})(OCA, Backbone);

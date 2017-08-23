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
		room: undefined,
		initialise: function(models, options) {
			this.room = options.room;
		},
		model: OCA.SpreedMe.Models.Participant,
		// comparator: function(model) {
		// 	return -(model.get('lastPing'));
		// },
		url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + '/' + this.room.get('token') + 'participants',
		/**
		 * @param result
		 * @returns {Array}
		 */
		parse: function(result) {
			return result.ocs.data;
		}
	});

})(OCA, OC, Backbone);

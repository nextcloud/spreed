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
		model: OCA.SpreedMe.Models.Participant,
		room: undefined,

		/**
		 * Returns the unique identifier for each participant model in the
		 * collection.
		 */
		modelId: function (attrs) {
			return attrs['userId']? ('userId-' + attrs['userId']) : ('sessionId' + attrs['sessionId']);
		},

		/**
		 * @param {OCA.SpreedMe.Models.Room} room
		 * @returns {Array}
		 */
		setRoom: function(room) {
			this.stopListening(this.room, 'change:participants');
			this.stopListening(this.room, 'change:numGuests');

			this.room = room;
			this.url = OC.linkToOCS('apps/spreed/api/v1/room', 2) + this.room.get('token') + '/participants';

			this.fetch();

			this.listenTo(this.room, 'change:participants', function() {
				this.fetch();
			});
			this.listenTo(this.room, 'change:numGuests', function() {
				this.fetch();
			});
		},

		/**
		 * @param result
		 * @returns {Array}
		 */
		parse: function(result) {
			return result.ocs.data;
		},

		/**
		 * Sort participants:
		 * - Moderators first
		 * - Online status
		 * - Alphabetic
		 *
		 * @param {OCA.SpreedMe.Models.Participant} modelA
		 * @param {OCA.SpreedMe.Models.Participant} modelB
		 * @returns {*}
		 */
		comparator: function(modelA, modelB) {
			var onlineA = modelA.get('sessionId') !== '' && modelA.get('sessionId') !== '0',
				onlineB = modelB.get('sessionId') !== '' && modelB.get('sessionId') !== '0',
				moderateA = modelA.get('participantType') === OCA.SpreedMe.app.OWNER ||
					modelA.get('participantType') === OCA.SpreedMe.app.MODERATOR,
				moderateB = modelB.get('participantType') === OCA.SpreedMe.app.OWNER ||
					modelB.get('participantType') === OCA.SpreedMe.app.MODERATOR,
				guestA = modelA.get('participantType') === OCA.SpreedMe.app.GUEST,
				guestB = modelB.get('participantType') === OCA.SpreedMe.app.GUEST;

			if (moderateA !== moderateB) {
				return moderateB - moderateA;
			}

			if (onlineA !== onlineB) {
				return onlineB - onlineA;
			}

			if (guestA !== guestB) {
				return guestA - guestB;
			}

			return modelA.get('displayName').localeCompare(modelB.get('displayName'));
		}
	});

})(OCA, OC, Backbone);

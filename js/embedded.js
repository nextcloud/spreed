/* global Marionette, Backbone, _, $ */

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

(function(OC, OCA, Marionette, Backbone, _, $) {
	'use strict';

	OCA.Talk = OCA.Talk || {};

	var roomChannel = Backbone.Radio.channel('rooms');

	OCA.Talk.Embedded = Marionette.Application.extend({
		OWNER: 1,
		MODERATOR: 2,
		USER: 3,
		GUEST: 4,
		USERSELFJOINED: 5,

		/* Must stay in sync with values in "lib/Room.php". */
		FLAG_DISCONNECTED: 0,
		FLAG_IN_CALL: 1,
		FLAG_WITH_AUDIO: 2,
		FLAG_WITH_VIDEO: 4,

		/** @property {OCA.SpreedMe.Models.Room} activeRoom  */
		activeRoom: null,

		/** @property {String} token  */
		token: null,

		/** @property {OCA.Talk.Connection} connection  */
		connection: null,

		/** @property {OCA.Talk.Signaling.base} signaling  */
		signaling: null,

		/** @property {OCA.SpreedMe.Models.RoomCollection} _rooms  */
		_rooms: null,

		_registerPageEvents: function() {
			// Initialize button tooltips
			$('[data-toggle="tooltip"]').tooltip({trigger: 'hover'}).click(function() {
				$(this).tooltip('hide');
			});
		},

		/**
		 * @param {string} token
		 */
		_setRoomActive: function(token) {
			if (OC.getCurrentUser().uid) {
				this._rooms.forEach(function(room) {
					room.set('active', room.get('token') === token);
				});
			}
		},
		syncAndSetActiveRoom: function(token) {
			var self = this;
			this.signaling.syncRooms()
				.then(function() {
					self.stopListening(self.activeRoom, 'change:participantFlags');

					if (OC.getCurrentUser().uid) {
						roomChannel.trigger('active', token);

						self._rooms.forEach(function(room) {
							if (room.get('token') === token) {
								self.activeRoom = room;
							}
						});
					}
				});
		},

		setEmptyContentMessage: function() {
		},

		initialize: function() {
			if (OC.getCurrentUser().uid) {
				this._rooms = new OCA.SpreedMe.Models.RoomCollection();
				this.listenTo(roomChannel, 'active', this._setRoomActive);
			}

			this._messageCollection = new OCA.SpreedMe.Models.ChatMessageCollection(null, {token: null});
			this._chatView = new OCA.SpreedMe.Views.ChatView({
				collection: this._messageCollection,
				id: 'chatView'
			});

			this._messageCollection.listenTo(roomChannel, 'leaveCurrentRoom', function() {
				this.stopReceivingMessages();
			});
		},
		onStart: function() {
			this.signaling = OCA.Talk.Signaling.createConnection();
			this.connection = new OCA.Talk.Connection(this);

			$(window).unload(function () {
				this.connection.leaveCurrentRoom(false);
				this.signaling.disconnect();
			}.bind(this));

			this._registerPageEvents();
		},
	});

})(OC, OCA, Marionette, Backbone, _, $);

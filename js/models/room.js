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
	 *
	 * Besides fetching the data from the server it supports renaming the room
	 * by calling "save('name', nameToSet, options)".
	 */
	var Room = Backbone.Model.extend({
		defaults: {
			id: '',
			token: '',
			name: '',
			type: 0,
			displayName: '',
			objectType: '',
			objectId: '',
			participantType: 0,
			participantFlags: 0,
			count: 0,
			hasPassword: false,
			hasCall: false,
			lastActivity: 0,
			unreadMessages: 0,
			unreadMention: false,
			isFavorite: false,
			notificationLevel: 0,
			lastPing: 0,
			sessionId: '0',
			participants: [],
			numGuests: 0,
			guestList: '',
			lastMessage: [],
			active: false
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
		},
		validate: function(attributes) {
			if (!attributes.name) {
				return t('spreed', 'Room name can not be empty');
			}
		},
		save: function(key, value, options) {
			if (typeof key !== 'string') {
				throw 'Room.save only supports single attributes';
			}

			var supportedKeys = [
				'name',
			];

			if (supportedKeys.indexOf(key) === -1) {
				throw 'Room.save does not support the "' + key + '" key';
			}

			if (options && options.patch !== undefined && !options.patch) {
				throw 'Room.save does not support "options.patch = false"';
			}

			options = options || {};

			// "patch: true" is needed to send only the changed attribute
			// instead of a complete representation of the model.
			options.patch = true;

			return Backbone.Model.prototype.save.call(this, key, value, options);
		},
		sync: function(method, model, options) {
			// When saving a model "Backbone.Model.save" calls "sync" with an
			// "update" method, which by default sends a "PUT" request that
			// contains all the attributes of the model. In order to send only
			// the attributes to be saved "patch: true" must be set in the
			// options. However, this causes a "PATCH" request instead of a
			// "PUT" request to be sent, so the "method" must be changed from
			// "patch" to "update", as the backend expects a "PUT" request.
			// Moreover, the endpoint to rename a room expects the name to be
			// provided in a "roomName" attribute instead of a "name"
			// attribute, so that has to be changed too.
			if (method === 'patch' && options.attrs.name !== undefined) {
				method = 'update';

				options.attrs.roomName = options.attrs.name;
				delete options.attrs.name;
			}

			return Backbone.Model.prototype.sync.call(this, method, model, options);
		},
		join: function() {
			OCA.SpreedMe.app.connection.joinRoom(this.get('token'));
		},
		leave: function() {
			if (!this.get('active')) {
				return;
			}

			OCA.SpreedMe.app.connection.leaveCurrentRoom();
		},
		removeSelf: function(options) {
			var self = this;

			// Removing self can fail, so wait for the server response to remove
			// the model from its collection and to leave the room.
			var success = options? options.success: undefined;
			options = _.extend({}, options, {
				url: this.url() + '/participants/self',
				wait: true,
				success: function() {
					self.leave();

					if (success) {
						success.apply(this, arguments);
					}
				}
			});

			return Backbone.Model.prototype.destroy.call(this, options);
		},
		destroy: function(options) {
			// Destroying a room is not expected to fail, so leave the room
			// without waiting for the server response for a snappier UI.
			this.leave();

			return Backbone.Model.prototype.destroy.call(this, options);
		},
	});

	OCA.SpreedMe.Models.Room = Room;

})(OCA, Backbone);

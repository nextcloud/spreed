/* global Backbone, Hashes, OCA */

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

(function(OCA, Backbone, Hashes) {
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
	 * by calling "save('name', nameToSet, options)", making the room public or
	 * private by calling "save('type', roomType, options)" or, preferably,
	 * "setPublic(isPublic, options)", and setting the password by calling
	 * "save('password', password, options)" or
	 * "setPassword(password, options)".
	 *
	 * After an attribute of a room is successfully saved all the rooms will be
	 * fetched again, as the saving could have triggered changes in other
	 * attributes too in the server.
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
			canStartCall: false,
			lastActivity: 0,
			unreadMessages: 0,
			unreadMention: false,
			isFavorite: false,
			notificationLevel: 0,
			lobbyState: 0,
			lobbyTimer: 0,
			lastPing: 0,
			sessionId: '0',
			participants: [],
			numGuests: 0,
			guestList: '',
			lastMessage: [],
			active: false
		},
		initialize: function() {
			this.listenTo(this, 'change:sessionId', function() {
				this.set('hashedSessionId', new Hashes.SHA1().hex(this.attributes.sessionId));
			});
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

			if (attributes.type && this.attributes.type && attributes.type !== this.attributes.type) {
				// These error messages are not expected to be ever shown to the
				// user, so they are not internationalized.
				if (this.attributes.type !== OCA.SpreedMe.app.ROOM_TYPE_GROUP && this.attributes.type !== OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
					return 'Room type can not be changed';
				}

				if (this.attributes.type === OCA.SpreedMe.app.ROOM_TYPE_GROUP && attributes.type !== OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
					return 'Group room type can only be changed to public';
				}

				if (this.attributes.type === OCA.SpreedMe.app.ROOM_TYPE_PUBLIC && attributes.type !== OCA.SpreedMe.app.ROOM_TYPE_GROUP) {
					return 'Public room type can only be changed to group';
				}
			}

			if (attributes.lobbyTimer && this.attributes.lobbyState !== OCA.SpreedMe.app.LOBBY_NON_MODERATORS) {
				return 'Lobby timer can be set only when lobby state is non moderators';
			}
		},
		save: function(key, value, options) {
			if (typeof key !== 'string') {
				throw 'Room.save only supports single attributes';
			}

			var supportedKeys = [
				'lobbyState',
				'lobbyTimer',
				'name',
				'password',
				'type',
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

			options = this._wrapOptionsToFetchRoomsOnSuccess(options);

			if (key === 'password') {
				// Prevent the password from being stored in the attributes of
				// this Room object; a "change:password" event will be always
				// fired (with a value of "undefined", not the actual password
				// value either).
				options.unset = true;
			}

			return Backbone.Model.prototype.save.call(this, key, value, options);
		},
		_wrapOptionsToFetchRoomsOnSuccess: function(options) {
			var success = options.success;

			return _.extend(options, {
				success: function() {
					// When the external signaling server is used the rooms are
					// automatically fetched after an attribute change. Due to
					// this fetching the rooms is delegated to the signaling, as
					// it will either immediately fetch the rooms when the
					// internal signaling server is used or wait for the
					// automatic fetch when the external signaling server is
					// used.
					OCA.SpreedMe.app.signaling.syncRooms();

					if (success) {
						success.apply(this, arguments);
					}
				}
			});
		},
		sync: function(method, model, options) {
			// When saving a model "Backbone.Model.save" calls "sync" with an
			// "update" method, which by default sends a "PUT" request that
			// contains all the attributes of the model. In order to send only
			// the attributes to be saved "patch: true" must be set in the
			// options. However, this causes a "PATCH" request to be sent, so
			// the "method" must be changed from "patch" to "create", "update"
			// or "delete" if the backend expects a "POST", "PUT" or "DELETE"
			// request instead.

			if (method === 'patch' && options.attrs.name !== undefined) {
				method = 'update';

				// The endpoint to rename a room expects the name to be provided
				// in a "roomName" attribute instead of a "name" attribute.
				options.attrs.roomName = options.attrs.name;
				delete options.attrs.name;
			}

			if (method === 'patch' && options.attrs.type !== undefined) {
				// The room type can only be changed between group and public.
				if (options.attrs.type === OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
					method = 'create';
				} else {
					method = 'delete';
				}

				options.url = this.url() + '/public';
			}

			if (method === 'patch' && options.attrs.password !== undefined) {
				method = 'update';

				options.url = this.url() + '/password';
			}

			if (method === 'patch' && options.attrs.lobbyState !== undefined) {
				method = 'update';

				options.url = this.url() + '/webinary/lobby';

				// The endpoint to set the lobby state expects the state to be
				// provided in a "state" attribute instead of a "lobbyState"
				// attribute.
				options.attrs.state = options.attrs.lobbyState;
				delete options.attrs.lobbyState;
			}

			if (method === 'patch' && options.attrs.lobbyTimer !== undefined) {
				method = 'update';

				options.url = this.url() + '/webinary/lobby';

				// The endpoint to set the lobby state expects the state and
				// timer to be provided in "state" and "timer" attribute instead
				// of "lobbyState" and "lobbyTimer" attributes.
				options.attrs.state = this.attributes.lobbyState;
				options.attrs.timer = options.attrs.lobbyTimer;
				delete options.attrs.lobbyTimer;
			}

			return Backbone.Model.prototype.sync.call(this, method, model, options);
		},
		setPublic: function(isPublic, options) {
			var roomType = isPublic? OCA.SpreedMe.app.ROOM_TYPE_PUBLIC: OCA.SpreedMe.app.ROOM_TYPE_GROUP;

			this.save('type', roomType, options);
		},
		setPassword: function(password, options) {
			this.save('password', password, options);
		},
		setLobbyState: function(lobbyState, options) {
			this.save('lobbyState', lobbyState, options);
		},
		setLobbyTimer: function(lobbyTimer, options) {
			this.save('lobbyTimer', lobbyTimer, options);
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
		isCurrentParticipantInLobby: function() {
			var isModerator = this.get('participantType') !== OCA.SpreedMe.app.USER &&
								this.get('participantType') !== OCA.SpreedMe.app.USERSELFJOINED &&
								this.get('participantType') !== OCA.SpreedMe.app.GUEST;

			if (this.get('lobbyState') === OCA.SpreedMe.app.LOBBY_NON_MODERATORS && !isModerator) {
				return true;
			}

			return false;
		},
	});

	OCA.SpreedMe.Models.Room = Room;

})(OCA, Backbone, Hashes);

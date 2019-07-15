/* global Backbone, OC, OCA */

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

(function(OCA, OC, Backbone) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Models = OCA.SpreedMe.Models || {};

	/**
	 * Model for chat messages.
	 *
	 * ChatMessage can be used as the model of a ChatMessageCollection or as a
	 * standalone model. When used as a standalone model the room token must be
	 * provided in the constructor options (as "token").
	 *
	 * In any case, "create" is the only synchronization method allowed; chat
	 * messages can not be edited nor deleted, and they can not be got
	 * individually either, but as a list through ChatMessageCollection.
	 *
	 * To send a new message create a standalone ChatMessage object and call
	 * "save".
	 */
	var ChatMessage = Backbone.Model.extend({

		defaults: {
			actorType: '',
			actorId: '',
			actorDisplayName: '',
			timestamp: 0,
			message: '',
			messageParameters: [],
			replyTo: 0
		},

		url: function() {
			if (this.token === undefined) {
				throw 'Missing parameter token';
			}

			return OC.linkToOCS('apps/spreed/api/v1/chat', 2) + this.token;
		},

		initialize: function(options) {
			// Only needed in standalone mode; when used as the model of a
			// ChatMessageCollection the synchronization is performed by the
			// collection instead.
			this.token = options.token;
		},

		sync: function(method, model, options) {
			if (method !== 'create') {
				throw 'Synchronization method not supported by ChatMessage: ' + method;
			}

			return Backbone.Model.prototype.sync.call(this, method, model, options);
		},

		updateGuestName: function(data) {
			if (this.get('actorType') === 'guests' && this.get('actorId') === data.sessionId && this.get('actorDisplayName') !== data.displayName) {
				this.set('actorDisplayName', data.displayName);
			}
		}

	});

	OCA.SpreedMe.Models.ChatMessage = ChatMessage;

})(OCA, OC, Backbone);

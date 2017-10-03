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
	 * Collection for chat messages.
	 *
	 * The ChatMessageCollection gives read access to all the chat messages from
	 * a specific chat room. The room token must be provided in the constructor
	 * options (as "token").
	 *
	 * "read" is the only synchronization method allowed; chat messages can not
	 * be edited nor deleted, and to send a new message a standalone ChatMessage
	 * should be used instead.
	 *
	 * To get the messages from the server "receiveMessages" should be used. It
	 * will enable polling to the server and automatically update the collection
	 * when new messages are received. Once enabled, the polling will go on
	 * indefinitely. Due to this "stopReceivingMessages" must be called once
	 * the ChatMessageCollection is no longer needed.
	 */
	var ChatMessageCollection = Backbone.Collection.extend({

		model: OCA.SpreedMe.Models.ChatMessage,

		initialize: function(models, options) {
			if (options.token === undefined) {
				throw 'Missing parameter token';
			}

			this.token = options.token;

			this.url = OC.linkToOCS('apps/spreed/api/v1/chat', 2) + this.token;

			this.offset = 0;
		},

		parse: function(result) {
			return result.ocs.data;
		},

		set: function(models, options) {
			// The server returns the messages sorted from newest to oldest,
			// which causes some issues with the default implementation of
			// collections. If several messages were received at once they would
			// be added to the collection in that same order, so the newest
			// message would be the first one added and the oldest message would
			// be the last one added (and the model id of the newest one would
			// be lower than the model id of the oldest one). If another group
			// of messages were received now then the newest message would be
			// added to the collection after the oldest message from the
			// previous group. Therefore, the models in the collection would not
			// follow an absolute order from the newest message to the oldest
			// one, but a local order for each group of messages fetched.
			//
			// Just sorting the collection is not a solution either. Setting
			// "sort: true" as a fetch option would keep the collection sorted
			// (although the ids of the models would still have the same problem
			// described above), but the "add" events would be triggered anyway
			// in the original order of the messages passed to "set".
			//
			// The best solution, besides changing the server to return the
			// messages sorted from oldest to newest, is to sort the models
			// passed to "set" from oldest to newest.
			if (models !== undefined && models !== null && models.ocs !== undefined && models.ocs.data !== undefined) {
				models.ocs.data = _.sortBy(models.ocs.data, function(model) {
					return model.timestamp;
				});
			}

			return Backbone.Collection.prototype.set.call(this, models, options);
		},

		receiveMessages: function() {
			this.receiveMessagesAgain = true;

			this.fetch({
				data: {
					// The notOlderThan parameter could be used to limit the
					// messages to those shown since the user opened the chat
					// window. However, it can not be used as a way to keep
					// track of the last message received. For example, even if
					// unlikely, if two messages were sent at the same time and
					// received the same timestamp in two different PHP
					// processes, it could happen that one of them was committed
					// to the database and read by another process waiting for
					// new messages while the second message was not committed
					// yet and thus not returned. Then, when the reading process
					// checks the messages again, it would miss the second one
					// due to its timestamp being the same as the last one it
					// received.
					offset: this.offset
				},
				success: _.bind(this._successfulFetch, this),
				error: _.bind(this._failedFetch, this)
			});
		},

		stopReceivingMessages: function() {
			this.receiveMessagesAgain = false;
		},

		_successfulFetch: function(collection, response) {
			this.offset += response.ocs.data.length;

			if (this.receiveMessagesAgain) {
				this.receiveMessages();
			}
		},

		_failedFetch: function() {
			if (this.receiveMessagesAgain) {
				this.receiveMessages();
			}
		}

	});

	OCA.SpreedMe.Models.ChatMessageCollection = ChatMessageCollection;

})(OCA, OC, Backbone);

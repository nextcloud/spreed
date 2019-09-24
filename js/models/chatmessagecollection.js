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
	 * options (as "token"), either as an actual room token or as null. It is
	 * possible to change the room of a ChatMessageCollection at any time by
	 * calling "setRoomToken". In any case, although null is supported as a
	 * temporal or reset value, note that an actual room token must be set
	 * before synchronizing the collection.
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
	 *
	 * Note that when "receiveMessages" is called it will first load the latest
	 * messages already sent and, then, it will start to poll for new messages.
	 * If further older messages needs to be loaded this can be done by calling
	 * "loadOlderMessages"; whether there are still older messages or not can
	 * be checked by calling "canLoadOlderMessages".
	 */
	var ChatMessageCollection = Backbone.Collection.extend({

		model: OCA.SpreedMe.Models.ChatMessage,

		initialize: function(models, options) {
			if (options.token === undefined) {
				throw 'Missing parameter token';
			}

			this._handler = this._messagesReceived.bind(this);
			this.setRoomToken(options.token);
		},

		parse: function(result) {
			return result.ocs.data;
		},

		/**
		 * Changes the room that this ChatMessageCollection gets its messages
		 * from.
		 *
		 * When a token is set this collection is reset, so the messages from
		 * the previous room are removed.
		 *
		 * If polling was currently being done to the previous room it will be
		 * automatically stopped. Note, however, that "receiveMessages" must be
		 * explicitly called if needed.
		 *
		 * @param {?string} token the token of the room.
		 */
		setRoomToken: function(token) {
			this.stopReceivingMessages();

			this.token = token;

			if (token !== null) {
				this.signaling = OCA.SpreedMe.app.signaling;
			} else {
				this.signaling = null;
			}

			this._canLoadOlderMessages = true;

			this._olderKnownMessageId = 0;
			this._newerKnownMessageId = 0;

			this._loadOlderMessagesPromise = null;

			this.reset();
		},

		updateGuestName: function(sessionId, newDisplayName) {
			this.invoke('updateGuestName', {sessionId: sessionId, displayName: newDisplayName});
		},

		/**
		 * Handle messages received by the signaling.
		 */
		_messagesReceived: function(messages) {
			if (messages.length > 0) {
				this._newerKnownMessageId = messages[messages.length - 1].id;
			}

			if (messages.length > 0 && !this._olderKnownMessageId) {
				this._olderKnownMessageId = messages[0].id;
			}

			this.trigger('add:start');
			this.set(messages);
			this.trigger('add:end');
		},

		canLoadOlderMessages: function() {
			return this._canLoadOlderMessages;
		},

		/**
		 * Loads the next batch of messages older than the oldest loaded
		 * message.
		 *
		 * This method does not block; it returns a Promise that will be
		 * resolved after the messages are loaded, or if there are no more
		 * messages to load. In case of error, the Promise will be rejected.
		 *
		 * Several calls to this method may return the same Promise if the
		 * previous load has not finished yet.
		 *
		 * @return {Promise} the promise that signals the end of the load.
		 */
		loadOlderMessages: function() {
			if (this._loadOlderMessagesPromise) {
				return this._loadOlderMessagesPromise;
			}

			var currentLoadOlderMessagesPromise = $.Deferred();
			this._loadOlderMessagesPromise = currentLoadOlderMessagesPromise;

			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1/chat', 2) + this.token,
				method: 'GET',
				data: {
					lookIntoFuture: 0,
					lastKnownMessageId: this._olderKnownMessageId,
					limit: 50,
				},
				dataType: 'json',
				success: function(data, status) {
					if (status === "notmodified") {
						this._canLoadOlderMessages = false;

						currentLoadOlderMessagesPromise.resolve();

						return;
					}

					// Remove the promise before resolving it to allow chained
					// loads.
					this._loadOlderMessagesPromise = null;

					// The known message IDs are updated before the elements are
					// actually set because "loadOlderMessages()" could be
					// called again when the "add:start" and "add:end" events
					// are handled.
					if (data.ocs.data.length > 0) {
						this._olderKnownMessageId = data.ocs.data[data.ocs.data.length - 1].id;
					}

					if (data.ocs.data.length > 0 && !this._newerKnownMessageId) {
						this._newerKnownMessageId = data.ocs.data[0].id;
					}

					this.trigger('add:start', {at: 0});
					// The elements are prepended one by one, as due to the
					// parameters set by Backbone for the "add" event the chat
					// view can not identify several elements prepended at once.
					for (var i = 0; i<data.ocs.data.length; i++) {
						this.set(data.ocs.data[i], {at: 0});
					}
					this.trigger('add:end', {at: 0});

					currentLoadOlderMessagesPromise.resolve();
				}.bind(this),
				error: function() {
					this._loadOlderMessagesPromise = null;

					currentLoadOlderMessagesPromise.reject();
				}.bind(this),
			});

			return this._loadOlderMessagesPromise;
		},

		receiveMessages: function() {
			this._receiveMessages = true;

			this.loadOlderMessages().then(function() {
				if (this.signaling && this._receiveMessages) {
					this.signaling.on("chatMessagesReceived", this._handler);
					this.signaling.startReceiveMessages(this._newerKnownMessageId);
				}
			}.bind(this)).fail(function() {
				this.receiveMessages();
			}.bind(this));
		},

		stopReceivingMessages: function() {
			this._receiveMessages = false;

			if (this.signaling) {
				this.signaling.off("chatMessagesReceived", this._handler);
				this.signaling.stopReceiveMessages();
			}
		}

	});

	OCA.SpreedMe.Models.ChatMessageCollection = ChatMessageCollection;

})(OCA, OC, Backbone);

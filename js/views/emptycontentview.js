/* global Marionette, $ */

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

(function(OCA, Marionette, $) {

	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};
	OCA.SpreedMe.Views = OCA.SpreedMe.Views || {};

	var roomsChannel = Backbone.Radio.channel('rooms');
	var localMediaChannel = Backbone.Radio.channel('localMedia');

	/**
	 * View for the main empty content message.
	 *
	 * This view does not render its own elements; an existing element must be
	 * provided when the view is created. In the main UI of Talk that element
	 * comes from the templates rendered by the server, which ensures that even
	 * if the UI takes a while to load the user will not see just an empty
	 * screen (which would happen if the view itself rendered the elements).
	 */
	var EmptyContentView  = Marionette.View.extend({

		template: false,

		ui: {
			'icon': '#emptycontent-icon',
			'message': 'h2',
			'messageAdditional': '.emptycontent-additional',
			'shareRoomInput': '.share-room-input',
			'shareRoomClipboardButton': '.shareRoomClipboard',
		},

		/**
		 * @param {string} options.el selector for the existing empty content
		 *                 element.
		 */
		initialize: function(/*options*/) {
			// Force render to create the UI bindings to the existing elements.
			this.render();

			this.listenTo(roomsChannel, 'leaveCurrentRoom', this.setEmptyContentMessageWhenConversationEnded);

			this.listenTo(localMediaChannel, 'webRtcNotSupported', function() {
				this._disableUpdatesOnActiveRoomChanges();

				this.setEmptyContentMessageWhenWebRtcIsNotSupported();
			});
			this.listenTo(localMediaChannel, 'waitingForPermissions', function() {
				this._disableUpdatesOnActiveRoomChanges();

				this.setEmptyContentMessageWhenWaitingForMediaPermissions();
			});
			this.listenTo(localMediaChannel, 'startLocalMedia', function() {
				this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall();

				this._enableUpdatesOnActiveRoomChanges();
			});
			this.listenTo(localMediaChannel, 'startWithoutLocalMedia', function() {
				this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall();

				this._enableUpdatesOnActiveRoomChanges();
			});
		},

		setActiveRoom: function(activeRoom) {
			this.stopListening(this._activeRoom, 'destroy', this.setInitialEmptyContentMessage);
			this._disableUpdatesOnActiveRoomChanges();

			this._activeRoom = activeRoom;

			if (!this._activeRoom.isCurrentParticipantInLobby()) {
				this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall();
			} else {
				this.setEmptyContentMessageWhenWaitingInLobby();
			}

			this.listenTo(this._activeRoom, 'destroy', function() {
				this.stopListening(this._activeRoom, 'destroy', this.setInitialEmptyContentMessage);
				this._disableUpdatesOnActiveRoomChanges();

				this._activeRoom = null;

				// 'leaveCurrentRoom' is sometimes triggered before the
				// 'destroy' event, so when the room is destroyed the initial
				// message overwrites the conversation ended message.
				this.setInitialEmptyContentMessage();
			});
			this._enableUpdatesOnActiveRoomChanges();
		},

		_disableUpdatesOnActiveRoomChanges: function() {
			this.stopListening(this._activeRoom, 'change:participants', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.stopListening(this._activeRoom, 'change:numGuests', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.stopListening(this._activeRoom, 'change:participantType', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.stopListening(this._activeRoom, 'change:type', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);

			this.stopListening(this._activeRoom, 'change:lobbyState', this.setEmptyContentMessageWhenWaitingInLobby);
			this.stopListening(this._activeRoom, 'change:lobbyTimer', this.setEmptyContentMessageWhenWaitingInLobby);
			this.stopListening(this._activeRoom, 'change:participantType', this.setEmptyContentMessageWhenWaitingInLobby);
		},

		_enableUpdatesOnActiveRoomChanges: function() {
			this.listenTo(this._activeRoom, 'change:participants', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.listenTo(this._activeRoom, 'change:numGuests', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.listenTo(this._activeRoom, 'change:participantType', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);
			this.listenTo(this._activeRoom, 'change:type', this.setEmptyContentMessageWhenWaitingForOthersToJoinTheCall);

			this.listenTo(this._activeRoom, 'change:lobbyState', this.setEmptyContentMessageWhenWaitingInLobby);
			this.listenTo(this._activeRoom, 'change:lobbyTimer', this.setEmptyContentMessageWhenWaitingInLobby);
			this.listenTo(this._activeRoom, 'change:participantType', this.setEmptyContentMessageWhenWaitingInLobby);
		},

		/**
		 *
		 * @param {string|Object} icon
		 * @param {string} icon.userId
		 * @param {string} icon.displayName
		 * @param {string} message
		 * @param {string} [messageAdditional]
		 * @param {string} [url]
		 */
		setEmptyContentMessage: function(icon, message, messageAdditional, url) {
			//Remove previous icon and avatar from emptycontent
			this.getUI('icon').removeAttr('class').attr('class', '');
			this.getUI('icon').html('');

			if (url) {
				this.getUI('shareRoomInput').removeClass('hidden').val(url);
				this.getUI('shareRoomClipboardButton').removeClass('hidden');
			} else {
				this.getUI('shareRoomInput').addClass('hidden');
				this.getUI('shareRoomClipboardButton').addClass('hidden');
			}

			if (typeof icon === 'string') {
				this.getUI('icon').addClass(icon);
			} else {
				var $avatar = $('<div>');
				$avatar.addClass('avatar room-avatar');
				if (icon.userId !== icon.displayName) {
					$avatar.avatar(icon.userId, 128, undefined, false, undefined, icon.displayName);
				} else {
					$avatar.avatar(icon.userId, 128);
				}
				this.getUI('icon').append($avatar);
			}

			this.getUI('message').html(message);
			this.getUI('messageAdditional').text(messageAdditional ? messageAdditional : '');
		},

		setInitialEmptyContentMessage: function() {
			this.setEmptyContentMessage(
				'icon-talk',
				t('spreed', 'Join a conversation or start a new one'),
				t('spreed', 'Say hi to your friends and colleagues!')
			);
		},

		setEmptyContentMessageWhenWaitingInLobby: function() {
			if (!this._activeRoom.isCurrentParticipantInLobby()) {
				return;
			}

			var icon = 'icon-lobby';

			var messageAdditional = t('spreed', 'You are currently waiting in the lobby');
			if (this._activeRoom.get('lobbyTimer')) {
				// PHP timestamp is second-based; JavaScript timestamp is
				// millisecond based.
				var startTime = OC.Util.formatDate(this._activeRoom.get('lobbyTimer') * 1000);
				messageAdditional = t('spreed', 'You are currently waiting in the lobby. This meeting is scheduled for {startTime}', {startTime: startTime});
			}

			this.setEmptyContentMessage(
				icon,
				this._activeRoom.get('name'),
				messageAdditional
			);
		},

		setEmptyContentMessageWhenWaitingForOthersToJoinTheCall: function() {
			if (this._activeRoom.isCurrentParticipantInLobby()) {
				return;
			}

			var icon = '';
			var message = '';
			var messageAdditional = '';
			var url = '';

			var isGuest = (OCA.Talk.getCurrentUser().uid === null);

			var participants = this._activeRoom.get('participants');
			var numberOfParticipants = Object.keys(participants).length;

			if (this._activeRoom.get('type') === OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
				icon = 'icon-public';
			} else {
				icon = 'icon-contacts-dark';
			}

			if (numberOfParticipants === 1 && this._activeRoom.get('numGuests') === 0) {
				message = t('spreed', 'No other people in this call');
			} else if ((!isGuest && numberOfParticipants === 2 && this._activeRoom.get('numGuests') === 0) ||
						(isGuest && numberOfParticipants === 1 && this._activeRoom.get('numGuests') === 1)) {
				var participantId = '',
					participantName = '';

				_.each(participants, function(data, userId) {
					if (OCA.Talk.getCurrentUser().uid !== userId) {
						participantId = userId;
						participantName = data.name;
					}
				});

				icon = { userId: participantId, displayName: participantName};

				message = t('spreed', 'Waiting for {participantName} to join the call …', {participantName: participantName});
			} else {
				message = t('spreed', 'Waiting for others to join the call …');
			}

			var canModerate = this._activeRoom.get('participantType') === OCA.SpreedMe.app.OWNER ||
								this._activeRoom.get('participantType') === OCA.SpreedMe.app.MODERATOR;

			if (this._activeRoom.get('type') === OCA.SpreedMe.app.ROOM_TYPE_GROUP && canModerate) {
				messageAdditional = t('spreed', 'You can invite others in the participant tab of the sidebar');
			} else if (this._activeRoom.get('type') === OCA.SpreedMe.app.ROOM_TYPE_PUBLIC) {
				messageAdditional = t('spreed', 'Share this link to invite others!');

				canModerate = canModerate ||
								this._activeRoom.get('participantType') === OCA.SpreedMe.app.GUEST_MODERATOR;
				if (canModerate) {
					messageAdditional = t('spreed', 'You can invite others in the participant tab of the sidebar or share this link to invite others!');
				}

				url = window.location.protocol + '//' + window.location.host + OC.generateUrl('/call/' + this._activeRoom.get('token'));
			}

			if (this._activeRoom.get('objectType') === 'share:password' || this._activeRoom.get('objectType') === 'file') {
				messageAdditional = '';
				url = '';
			}

			this.setEmptyContentMessage(icon, message, messageAdditional, url);
		},

		setEmptyContentMessageWhenWebRtcIsNotSupported: function() {
			this.setEmptyContentMessage(
				'icon-video-off',
				t('spreed', 'WebRTC is not supported in your browser :-/'),
				t('spreed', 'Please use a different browser like Firefox or Chrome')
			);
		},

		setEmptyContentMessageWhenWaitingForMediaPermissions: function() {
			this.setEmptyContentMessage(
				'icon-video-off',
				t('spreed', 'Waiting for camera and microphone permissions'),
				t('spreed', 'Please, give your browser access to use your camera and microphone in order to use this app.')
			);
		},

		setEmptyContentMessageWhenConversationEnded: function() {
			// 'leaveCurrentRoom' is sometimes triggered after the 'destroy'
			// event, so do not overwrite the initial message with the
			// conversation ended message.
			if (!this._activeRoom) {
				return;
			}

			this.setEmptyContentMessage(
				'icon-video-off',
				t('spreed', 'This conversation has ended')
			);
		},

	});

	OCA.SpreedMe.Views.EmptyContentView = EmptyContentView;

})(OCA, Marionette, $);

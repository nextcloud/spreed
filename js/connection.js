// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.Talk = OCA.Talk || {};

	var roomsChannel = Backbone.Radio.channel('rooms');


	function Connection(app) {
		this.app = app;

		// Todo this should not be here
		var selectParticipants = $('#select-participants');
		selectParticipants.keyup(function () {
			selectParticipants.tooltip('hide');
			selectParticipants.removeClass('error');
		});

		this.app.signaling.on('roomChanged', function() {
			this.leaveCurrentRoom();
		}.bind(this));

		this.app.signaling.on('pullMessagesStoppedOnFail', function() {
			this.leaveCurrentRoom();
		}.bind(this));
	}

	OCA.Talk.Connection = Connection;
	OCA.Talk.Connection.prototype = {
		/** @property {OCA.Talk.Application} app */
		app: null,

		_createCallSuccessHandle: function(ocsResponse) {
			var token = ocsResponse.ocs.data.token;
			this.joinRoom(token);
		},
		createOneToOneVideoCall: function(recipientUserId) {
			console.log("Creating one-to-one video call", recipientUserId);
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'room',
				type: 'POST',
				data: {
					invite: recipientUserId,
					roomType: 1
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createCallSuccessHandle, this)
			});
		},
		createGroupVideoCall: function(groupId, roomName) {
			console.log("Creating group video call", groupId);
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'room',
				type: 'POST',
				data: {
					invite: groupId,
					roomType: 2,
					roomName: roomName
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createCallSuccessHandle, this)
			});
		},
		createPublicVideoCall: function(roomName) {
			console.log("Creating a new public room.");
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'room',
				type: 'POST',
				data: {
					roomType: 3,
					roomName: roomName
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createCallSuccessHandle, this)
			});
		},
		joinRoom: function(token) {
			if (this.app.signaling.currentRoomToken === token) {
				return;
			}

			this.app.signaling.leaveCurrentRoom();
			this.app.token = token;
			this.app.signaling.joinRoom(token);

			roomsChannel.trigger('joinRoom', token);

			$('#video-fullscreen').removeClass('hidden');
		},
		leaveCurrentRoom: function() {
			$('#video-fullscreen').addClass('hidden');
			this.app.signaling.leaveCurrentRoom();

			$(this.app.mainCallElementSelector).removeClass('incall');

			roomsChannel.trigger('leaveCurrentRoom');
		},
		joinCall: function(token) {
			if (this.app.signaling.currentCallToken === token) {
				return;
			}

			roomsChannel.trigger('joinCall', token);

			var self = this;
			this.app.callbackAfterMedia = function(configuration) {
				var flags = OCA.SpreedMe.app.FLAG_IN_CALL;
				if (configuration) {
					if (configuration.audio) {
						flags |= OCA.SpreedMe.app.FLAG_WITH_AUDIO;
					}
					if (configuration.video && self.app.signaling.getSendVideoIfAvailable()) {
						flags |= OCA.SpreedMe.app.FLAG_WITH_VIDEO;
					}
				}
				self.app.signaling.joinCall(token, flags);
				self.app.signaling.syncRooms();
			};

			this.app.setupWebRTC();
		},
		leaveCurrentCall: function() {
			roomsChannel.trigger('leaveCurrentCall');

			this.app.signaling.leaveCurrentCall();
			this.app.signaling.syncRooms();
			$(this.app.mainCallElementSelector).removeClass('incall');
		},
	};

})(OCA, OC, $);

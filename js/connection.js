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
			this.leaveCurrentCall(false);
		}.bind(this));

		// Todo this blocks multi room support
		this.leaveAllCalls();
	}

	OCA.Talk.Connection = Connection;
	OCA.Talk.Connection.prototype = {
		/** @property {OCA.Talk.Application} app */
		app: null,

		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		_createCallSuccessHandle: function(ocsResponse) {
			var token = ocsResponse.ocs.data.token;
			OC.Util.History.pushState({
				token: token
			}, OC.generateUrl('/call/' + token));
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
			this.app.signaling.joinRoom(token);
			this.app.syncAndSetActiveRoom(token);
		},
		joinCall: function(token) {
			if (this.app.signaling.currentCallToken === token) {
				return;
			}

			this.app.signaling.leaveCurrentCall();
			this.app.signaling.joinCall(token);
			this.app.signaling.syncRooms();

			this.app.setupWebRTC();

			$('#emptycontent').hide();
		},
		leaveCall: function(token) {
			if (this.app.signaling.currentCallToken !== token) {
				return;
			}

			this.app.signaling.leaveCurrentCall();
			this.app.signaling.syncRooms();
			$('#app-content').removeClass('incall');
		},
		leaveCurrentCall: function(deleter) {
			this.app.signaling.leaveRoom();
			OC.Util.History.pushState({}, OC.generateUrl('/apps/spreed'));
			$('#app-content').removeClass('incall');
			this.showRoomDeletedMessage(deleter);
			roomsChannel.trigger('leaveCurrentCall');
		},
		leaveAllCalls: function() {
			if (this.app.signaling) {
				// We currently only support a single active call.
				this.app.signaling.leaveCurrentCall();
				this.app.signaling.leaveCurrentRoom();
			}
		},
		showRoomDeletedMessage: function(deleter) {
			if (deleter) {
				this.app.setEmptyContentMessage(
					'icon-video',
					t('spreed', 'Join a conversation or start a new one')
				);
			} else {
				this.app.setEmptyContentMessage(
					'icon-video-off',
					t('spreed', 'This call has ended')
				);
			}
		}
	};

})(OCA, OC, $);

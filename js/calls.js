// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var signaling;

	function initCalls(signaling_connection) {
		signaling = signaling_connection;

		var selectParticipants = $('#select-participants');
		selectParticipants.keyup(function () {
			selectParticipants.tooltip('hide');
			selectParticipants.removeClass('error');
		});

		signaling.on('roomChanged', function() {
			OCA.SpreedMe.Calls.leaveCurrentCall(false);
		});

		OCA.SpreedMe.Calls.leaveAllCalls();
	}

	var roomsChannel = Backbone.Radio.channel('rooms');

	OCA.SpreedMe.Calls = {
		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		_createCallSuccessHandle: function(ocsResponse) {
			var token = ocsResponse.ocs.data.token;
			OC.Util.History.pushState({
				token: token
			}, OC.generateUrl('/call/' + token));
			this.join(token);
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
		createGroupVideoCall: function(groupId) {
			console.log("Creating group video call", groupId);
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'room',
				type: 'POST',
				data: {
					invite: groupId,
					roomType: 2
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createCallSuccessHandle, this)
			});
		},
		createPublicVideoCall: function() {
			console.log("Creating a new public room.");
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'room',
				type: 'POST',
				data: {
					roomType: 3
				},
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createCallSuccessHandle, this)
			});
		},
		join: function(token) {
			if (signaling.currentCallToken === token) {
				return;
			}

			$('#emptycontent').hide();
			$('.videoView').addClass('hidden');
			$('#app-content').addClass('icon-loading');

			OCA.SpreedMe.webrtc.leaveRoom();
			OCA.SpreedMe.webrtc.joinRoom(token);
		},
		leaveCurrentCall: function(deleter) {
			OCA.SpreedMe.webrtc.leaveRoom();
			OC.Util.History.pushState({}, OC.generateUrl('/apps/spreed'));
			$('#app-content').removeClass('incall');
			this.showRoomDeletedMessage(deleter);
			roomsChannel.trigger('leaveCurrentCall');
		},
		leaveAllCalls: function() {
			if (signaling) {
				// We currently only support a single active call.
				signaling.leaveCurrentCall();
			}
		},
		showRoomDeletedMessage: function(deleter) {
			if (deleter) {
				OCA.SpreedMe.app.setEmptyContentMessage(
					'icon-video',
					t('spreed', 'Looking great today! :)'),
					t('spreed', 'Time to call your friends')
				);
			} else {
				OCA.SpreedMe.app.setEmptyContentMessage(
					'icon-video-off',
					t('spreed', 'This call has ended')
				);
			}
		}
	};

	OCA.SpreedMe.initCalls = initCalls;

})(OCA, OC, $);

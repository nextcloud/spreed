// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	var signaling;

	function initRooms(signaling_connection) {
		signaling = signaling_connection;

		var editRoomname = $('#edit-roomname');
		editRoomname.keyup(function () {
			editRoomname.tooltip('hide');
			editRoomname.removeClass('error');
		});
	}

	Backbone.Radio.channel('rooms');

	OCA.SpreedMe.Rooms = {
		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		_createRoomSuccessHandle: function(token) {
			OC.Util.History.pushState({
				token: token
			}, OC.generateUrl('/call/' + token));
			this.join(token);
		},
		createOneToOneVideoCall: function(recipientUserId) {
			console.log("Creating one-to-one video call", recipientUserId);
			signaling.createOneToOneVideoCall(recipientUserId)
				.then(_.bind(this._createRoomSuccessHandle, this));
		},
		createGroupVideoCall: function(groupId) {
			console.log("Creating group video call", groupId);
			signaling.createGroupVideoCall(groupId)
				.then(_.bind(this._createRoomSuccessHandle, this));
		},
		createPublicVideoCall: function() {
			console.log("Creating a new public room.");
			signaling.createPublicVideoCall()
				.then(_.bind(this._createRoomSuccessHandle, this));
		},
		join: function(token) {
			if (signaling.currentRoom === token) {
				return;
			}

			$('#emptycontent').hide();
			$('.videoView').addClass('hidden');
			$('#app-content').addClass('icon-loading');

			OCA.SpreedMe.webrtc.leaveRoom();
			OCA.SpreedMe.webrtc.joinRoom(token);
		},
		leaveCurrentRoom: function() {
			OCA.SpreedMe.webrtc.leaveRoom();
			OC.Util.History.pushState({}, OC.generateUrl('/apps/spreed'));
			$('#app-content').removeClass('incall');
		},
		leaveAllRooms: function() {
			signaling.leaveAllRooms();
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

	OCA.SpreedMe.initRooms = initRooms;

})(OCA, OC, $);

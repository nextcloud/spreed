// TODO(fancycode): Should load through AMD if possible.
/* global OC, OCA */

(function(OCA, OC, $) {
	'use strict';

	OCA.SpreedMe = OCA.SpreedMe || {};

	function initRooms() {

		var editRoomname = $('#edit-roomname');
		editRoomname.keyup(function () {
			editRoomname.tooltip('hide');
			editRoomname.removeClass('error');
		});
	}

	var currentRoom = '';
	Backbone.Radio.channel('rooms');

	OCA.SpreedMe.Rooms = {
		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		_createRoomSuccessHandle: function(data) {
			OC.Util.History.pushState({
				token: data.token
			}, OC.generateUrl('/apps/spreed?token=' + data.token));
			this.join(data.token);
		},
		createOneToOneVideoCall: function(recipientUserId) {
			console.log(recipientUserId);
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/oneToOne'),
				type: 'PUT',
				data: 'targetUserName='+recipientUserId,
				success: _.bind(this._createRoomSuccessHandle, this)
			});
		},
		createGroupVideoCall: function(groupId) {
			console.log(groupId);
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/group'),
				type: 'PUT',
				data: 'targetGroupName='+groupId,
				success: _.bind(this._createRoomSuccessHandle, this)
			});
		},
		createPublicVideoCall: function() {
			console.log("Creating a new public room.");
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/public'),
				type: 'PUT',
				success: _.bind(this._createRoomSuccessHandle, this)
			});
		},
		join: function(token) {
			if (OCA.SpreedMe.Rooms.currentRoom() === token) {
				return;
			}

			$('#emptycontent').hide();
			$('.videoView').addClass('hidden');
			$('#app-content').addClass('icon-loading');

			OCA.SpreedMe.webrtc.leaveRoom();

			currentRoom = token;
			OCA.SpreedMe.webrtc.joinRoom(token);
			OCA.SpreedMe.Rooms.ping();
		},
		leaveCurrentRoom: function() {
			OCA.SpreedMe.webrtc.leaveRoom();

			currentRoom = '';
		},
		currentRoom: function() {
			return currentRoom;
		},
		peers: function(token) {
			return $.ajax({
				url: OC.generateUrl('/apps/spreed/api/room/{token}/peers', {token: token})
			});
		},
		ping: function() {
			if (OCA.SpreedMe.Rooms.currentRoom() === '') {
				return;
			}

			$.post(
				OC.generateUrl('/apps/spreed/api/ping'),
				{
					token: OCA.SpreedMe.Rooms.currentRoom()
				}
			).fail(function() {
				OCA.SpreedMe.Rooms.leaveCurrentRoom();
				OCA.SpreedMe.Rooms.showRoomDeletedMessage(false);
			});
		},
		leaveAllRooms: function() {
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/leave'),
				method: 'DELETE',
				async: false
			});
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

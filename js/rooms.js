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

	var currentRoomId = 0;
	Backbone.Radio.channel('rooms');

	OCA.SpreedMe.Rooms = {
		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		createOneToOneVideoCall: function(recipientUserId) {
			var self = this;
			console.log(recipientUserId);
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/oneToOne'),
				type: 'PUT',
				data: 'targetUserName='+recipientUserId,
				success: function(data) {
					self.join(data.roomId);
				}
			});
		},
		createGroupVideoCall: function(groupId) {
			var self = this;
			console.log(groupId);
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/group'),
				type: 'PUT',
				data: 'targetGroupName='+groupId,
				success: function(data) {
					self.join(data.roomId);
				}
			});
		},
		createPublicVideoCall: function() {
			var self = this;
			console.log("Creating a new public room.");
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/public'),
				type: 'PUT',
				success: function(data) {
					self.join(data.roomId);
				}
			});
		},
		join: function(roomId) {
			if (OCA.SpreedMe.Rooms.currentRoom() === roomId) {
				return;
			}

			$('#emptycontent').hide();
			$('.videoView').addClass('hidden');
			$('#app-content').addClass('icon-loading');

			OCA.SpreedMe.webrtc.leaveRoom();

			currentRoomId = roomId;
			OC.Util.History.pushState({
				roomId: roomId
			});
			OCA.SpreedMe.webrtc.joinRoom(roomId);
			OCA.SpreedMe.Rooms.ping();
		},
		currentRoom: function() {
			return currentRoomId;
		},
		peers: function(roomId) {
			return $.ajax({
				url: OC.generateUrl('/apps/spreed/api/room/{roomId}/peers', {roomId: roomId})
			});
		},
		ping: function() {
			if (OCA.SpreedMe.Rooms.currentRoom() === 0) {
				return;
			}

			$.post(
				OC.generateUrl('/apps/spreed/api/ping'),
				{
					roomId: OCA.SpreedMe.Rooms.currentRoom()
				}
			);
		},
		leaveAllRooms: function() {
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/leave'),
				method: 'DELETE',
				async: false
			});
		},
	};

	OCA.SpreedMe.initRooms = initRooms;

})(OCA, OC, $);

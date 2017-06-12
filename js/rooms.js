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
	var pingFails = 0;
	Backbone.Radio.channel('rooms');

	OCA.SpreedMe.Rooms = {
		showCamera: function() {
			$('.videoView').removeClass('hidden');
		},
		_createRoomSuccessHandle: function(ocsResponse) {
			var data = ocsResponse.ocs.data;
			OC.Util.History.pushState({
				token: data.token
			}, OC.generateUrl('/call/' + data.token));
			this.join(data.token);
		},
		createOneToOneVideoCall: function(recipientUserId) {
			console.log(recipientUserId);
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'oneToOne',
				type: 'PUT',
				data: 'targetUserName='+recipientUserId,
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createRoomSuccessHandle, this)
			});
		},
		createGroupVideoCall: function(groupId) {
			console.log(groupId);
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'group',
				type: 'PUT',
				data: 'targetGroupName='+groupId,
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				success: _.bind(this._createRoomSuccessHandle, this)
			});
		},
		createPublicVideoCall: function() {
			console.log("Creating a new public room.");
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'public',
				type: 'PUT',
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
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
			OC.Util.History.pushState({}, OC.generateUrl('/apps/spreed'));
			$('#app-content').removeClass('incall');
			currentRoom = '';
		},
		currentRoom: function() {
			return currentRoom;
		},
		peers: function(token) {
			return $.ajax({
				beforeSend: function (request) {
					request.setRequestHeader('Accept', 'application/json');
				},
				url: OC.linkToOCS('apps/spreed/api/v1/room', 2) + token + '/peers'
			});
		},
		ping: function() {
			if (OCA.SpreedMe.Rooms.currentRoom() === '') {
				return;
			}

			$.post(
				OC.linkToOCS('apps/spreed/api/v1', 2) + 'ping',
				{
					token: OCA.SpreedMe.Rooms.currentRoom()
				}
			).done(function() {
				pingFails = 0;
			}).fail(function(xhr) {
				// If there is an error when pinging, retry for 3 times.
				if (xhr.status !== 404 && pingFails < 3) {
					pingFails++;
					return;
				}
				OCA.SpreedMe.Rooms.leaveCurrentRoom();
				OCA.SpreedMe.Rooms.showRoomDeletedMessage(false);
			});
		},
		leaveAllRooms: function() {
			$.ajax({
				url: OC.linkToOCS('apps/spreed/api/v1', 2) + 'leave',
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

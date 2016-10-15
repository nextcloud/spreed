// TODO(fancycode): Should load through AMD if possible.
/* global webrtc: false */

$(document).ready(function() {

	var editRoomname = $('#edit-roomname');
	editRoomname.keyup(function() {
		editRoomname.tooltip('hide');
		editRoomname.removeClass('error');
	});

	OCA.SpreedMe = OCA.SpreedMe || {};
	var currentRoomId = 0;

	OCA.SpreedMe.Rooms = {
		create: function(roomName) {
			$.post(
				OC.generateUrl('/apps/spreed/api/room'),
				{
					roomName: roomName
				},
				function(data) {
					var roomId = data.roomId;
					OCA.SpreedMe.Rooms.join(roomId);
				}
			).fail(function(jqXHR, status, error) {
				var message;
				try {
					message = JSON.parse(jqXHR.responseText).message;
				} catch (e) {
					// Ignore exception, received no/invalid JSON.
				}
				if (!message) {
					message = jqXHR.responseText || error;
				}
				editRoomname.prop('title', message);
				editRoomname.tooltip({placement: 'right', trigger: 'manual'});
				editRoomname.tooltip('show');
				editRoomname.addClass('error');
			});
		},
		list: function() {
			$.ajax({
				url: OC.generateUrl('/apps/spreed/api/room'),
				success: function(data) {
					$('#app-navigation ul').html('');
					data.forEach(function(element) {
						$('#app-navigation ul').append('<li><a href="#'+escapeHTML(element['id'])+'">'+escapeHTML(element['name'])+' <span class="utils">' + escapeHTML(element['count']) + '</span></a></li>');
					});
					$('#app-navigation').removeClass('icon-loading');
				}
			});
		},
		join: function(roomId) {
			$('#emptycontent').hide();
			$('.videoView').addClass('hidden');
			$('#app-content').addClass('icon-loading');

			currentRoomId = roomId;
			webrtc.joinRoom(roomId);
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
			$.post(
				OC.generateUrl('/apps/spreed/api/ping'),
				{
					currentRoom: OCA.SpreedMe.Rooms.currentRoom()
				}
			);
		}
	};

});

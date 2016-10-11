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
					if (data.status !== 'success') {
						editRoomname.prop('title', data.message);
						editRoomname.tooltip({placement: 'right', trigger: 'manual'});
						editRoomname.tooltip('show');
						editRoomname.addClass('error');
						return;
					}

					var roomId = data.roomId;
					OCA.SpreedMe.Rooms.join(roomId);
				}
			);
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
	}

});

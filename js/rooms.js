(function(OCA) {

	OCA.SpreedMe = OCA.SpreedMe || {};
	var currentRoomId = 0;

	OCA.SpreedMe.Rooms = {
		create: function(roomName) {
			$.post(
				OC.generateUrl('/apps/spreedme/api/room'),
				{
					roomName: roomName
				}
			);
		},
		list: function() {
			$.ajax({
				url: OC.generateUrl('/apps/spreedme/api/room'),
				success: function(data) {
					$('#app-navigation ul').html('');
					data.forEach(function(element) {
						$('#app-navigation ul').append('<li><a href="#'+escapeHTML(element['id'])+'">'+escapeHTML(element['name'])+' (' + escapeHTML(element['count']) + ')</a></li>');
					});
					$('#app-navigation').removeClass('icon-loading');
				}
			});

		},
		join: function(roomId) {
			currentRoomId = roomId;
			webrtc.joinRoom(roomId);
			OCA.SpreedMe.Rooms.ping();
		},
		currentRoom: function() {
			return currentRoomId;
		},
		peers: function(roomId) {
			return $.ajax({
				url: OC.generateUrl('/apps/spreedme/api/room/{roomId}/peers', {roomId: roomId})
			});
		},
		ping: function() {
			$.post(
				OC.generateUrl('/apps/spreedme/api/ping'),
				{
					currentRoom: OCA.SpreedMe.Rooms.currentRoom()
				}
			);
		}
	}
})(OCA);
/* global OC, OCP, OCA, $, _, Handlebars */

(function(OC, OCP, OCA, $) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.SignalingServer = {

		$signaling: undefined,

		init: function() {
			this.$signaling = $('div.signaling-server');
			this.$signaling.find('input').on('change', this.saveServer);
		},

		saveServer: function() {
			// this.$signaling.find('input').removeClass('error');
			// this.$signaling.find('.icon-checkmark-color').addClass('hidden');

			// OCP.AppConfig.setValue('spreed', $(this).attr('name'), $(this).value, {
			// 	success: function() {
			// 		self.temporaryShowSuccess($server);
			// 	}
			// });
		},

		temporaryShowSuccess: function($server) {
			var $icon = $server.find('.icon-checkmark-color');
			$icon.removeClass('hidden');
			setTimeout(function() {
				$icon.addClass('hidden');
			}, 2000);
		}

	};


})(OC, OCP, OCA, $);

$(document).ready(function(){
	OCA.VideoCalls.Admin.SignalingServer.init();
});

/* global OC, OCP, OCA, $, _ */

(function(OC, OCP, OCA, $, _) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.StunServer = {

		TEMPLATE: '<div class="stun-server">' +
		'	<input type="text" name="stun_server" placeholder="stunserver:port" value="{{server}}" />' +
		'	<a class="icon icon-delete" title="' + t('spreed', 'Delete server') + '"></a>' +
		'	<a class="icon icon-add" title="' + t('spreed', 'Add new server') + '"></a>' +
		'</div>',
		$list: undefined,
		template: undefined,

		init: function() {
			this.template = Handlebars.compile(this.TEMPLATE);
			this.$list = $('div.stun-servers');
			this.renderList();
		},

		renderList: function() {
			var servers = this.$list.data('servers');

			_.each(servers, function(server) {
				this.$list.append(
					this.renderServer(server)
				);
			}.bind(this));

			if (servers.length === 0) {
				this.addNewTemplate('stun.nextcloud.com:443');
			}
		},

		addNewTemplate: function(server) {
			server = server || '';
			this.$list.append(
				this.renderServer(server)
			);
		},

		deleteServer: function(e) {
			e.stopPropagation();

			var $server = $(e.currentTarget).parents('div.stun-server').first();
			$server.remove();

			this.saveServers();

			if (this.$list.find('div.stun-server').length === 0) {
				this.addNewTemplate('stun.nextcloud.com:443');
			}

		},

		saveServers: function() {
			var servers = [];

			this.$list.find('input').each(function() {
				var server = this.value,
					parts = server.split(':');
				if (parts.length !== 2) {
					$(this).addClass('error');
				} else {
					if (parts[1].match(/^([1-9]\d{0,4})$/) === null ||
						parseInt(parts[1]) > Math.pow(2, 16)) { //65536
						$(this).addClass('error');
					} else {
						servers.push(this.value);
						$(this).removeClass('error');
					}
				}
			});

			OCP.AppConfig.setValue('spreed', 'stun_server', JSON.stringify(servers));
		},

		renderServer: function(server) {
			var $template = $(this.template({
				server: server
			}));

			$template.find('a.icon-add').on('click', this.addNewTemplate.bind(this));
			$template.find('a.icon-delete').on('click', this.deleteServer.bind(this));
			$template.find('input').on('change', this.saveServers.bind(this));

			return $template;
		}

	};


})(OC, OCP, OCA, $, _);

$(document).ready(function(){
	OCA.VideoCalls.Admin.StunServer.init();
});

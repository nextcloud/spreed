/* global OC, OCP, OCA, $, _, Handlebars */

(function(OC, OCP, OCA, $, _, Handlebars) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.TurnServer = {

		TEMPLATE: '<div class="turn-server">' +
		'	<input type="text" class="server" placeholder="turn.example.org" value="{{server}}">' +
		'	<input type="text" class="secret" placeholder="' + t('spreed', 'Shared secret') + '" value="{{secret}}">' +
		'	<select class="protocols" title="' + t('spreed', 'TURN server protocols') + '">' +
		'	{{#select protocols}}' +
		'		<option value="udp,tcp">' + t('spreed', 'UDP and TCP') + '</option>' +
		'		<option value="udp">' + t('spreed', 'UDP only') + '</option>' +
		'		<option value="tcp">' + t('spreed', 'TCP only') + '</option>' +
		'	{{/select}}' +
		'	</select>' +
		'	<a class="icon icon-delete" title="' + t('spreed', 'Delete server') + '"></a>' +
		'	<a class="icon icon-add" title="' + t('spreed', 'Add new server') + '"></a>' +
		'	<span class="icon icon-checkmark-color hidden" title="' + t('spreed', 'Saved') + '"></span>' +
		'</div>',
		$list: undefined,
		template: undefined,

		init: function() {
			Handlebars.registerHelper('select', this._handlebarSelectOption);
			this.template = Handlebars.compile(this.TEMPLATE);
			this.$list = $('div.turn-servers');
			this.renderList();

		},

		_handlebarSelectOption: function(value, options) {
			var $el = $('<select />').html(options.fn(this));
			$el.find('[value="' + value + '"]').attr({'selected':'selected'});
			return $el.html();
		},

		renderList: function() {
			var servers = this.$list.data('servers');

			_.each(servers, function(server) {
				this.$list.append(
					this.renderServer(server)
				);
			}.bind(this));

			if (servers.length === 0) {
				this.addNewTemplate();
			}
		},

		addNewTemplate: function() {
			var $server = this.renderServer({});
			this.$list.append($server);
			return $server;
		},

		deleteServer: function(e) {
			e.stopPropagation();

			var $server = $(e.currentTarget).parents('div.turn-server').first();
			$server.remove();

			this.saveServers();

			if (this.$list.find('div.turn-server').length === 0) {
				var $newServer = this.addNewTemplate();
				this.temporaryShowSuccess($newServer);
			}
		},

		saveServers: function() {
			var servers = [],
				$error = [],
				$success = [],
				self = this;

			this.$list.find('input').removeClass('error');
			this.$list.find('.icon-checkmark-color').addClass('hidden');

			this.$list.find('div.turn-server').each(function() {
				var $row = $(this),
					$server = $row.find('input.server'),
					$secret = $row.find('input.secret'),
					$protocols = $row.find('select.protocols'),
					data = {
						server: $server.val().trim(),
						secret: $secret.val().trim(),
						protocols: $protocols.val()
					};
				if (data.server === '') {
					$error.push($server);
					if (data.secret === '') {
						$error.push($secret);
					}
					return;
				}
				
				// remove HTTP/HTTPS prefix if provided
				if (data.server.startsWith('https://')) {
					data.server = data.server.substr(8);
				} else if (data.server.startsWith('http://')) {
					data.server = data.server.substr(7);
				}
				
				if (data.secret === '') {
					$error.push($secret);
					return;
				}

				$success.push($(this));
				servers.push(data);
			});

			OCP.AppConfig.setValue('spreed', 'turn_servers', JSON.stringify(servers), {
				success: function() {
					_.each($error, function($input) {
						$input.addClass('error');
					});
					_.each($success, function($server) {
						self.temporaryShowSuccess($server);
					});
				}
			});
		},

		temporaryShowSuccess: function($server) {
			var $icon = $server.find('.icon-checkmark-color');
			$icon.removeClass('hidden');
			setTimeout(function() {
				$icon.addClass('hidden');
			}, 2000);
		},

		renderServer: function(server) {
			var $template = $(this.template(server));

			$template.find('a.icon-add').on('click', this.addNewTemplate.bind(this));
			$template.find('a.icon-delete').on('click', this.deleteServer.bind(this));
			$template.find('input').on('change', this.saveServers.bind(this));
			$template.find('select').on('change', this.saveServers.bind(this));

			return $template;
		}

	};


})(OC, OCP, OCA, $, _, Handlebars);

$(document).ready(function(){
	OCA.VideoCalls.Admin.TurnServer.init();
});

/* global OC, OCP, OCA, $, _, Handlebars */

(function(OC, OCP, OCA, $, _, Handlebars) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.SignalingServer = {

		TEMPLATE: '<div class="signaling-server">' +
		'	<input type="text" class="server" placeholder="wss://signaling.example.org" value="{{server}}">' +
		'	<input type="checkbox" id="verify{{seed}}" name="verify{{seed}}" class="checkbox verify" value="1" {{#if verify}} checked="checked"{{/if}}>' +
		'	<label for="verify{{seed}}">' + t('spreed', 'Validate SSL certificate') + '</label>' +
		'	<a class="icon icon-delete" title="' + t('spreed', 'Delete server') + '"></a>' +
		'	<a class="icon icon-add" title="' + t('spreed', 'Add new server') + '"></a>' +
		'	<span class="icon icon-checkmark-color hidden" title="' + t('spreed', 'Saved') + '"></span>' +
		'</div>',
		$list: undefined,
		$secret: undefined,
		template: undefined,
		seed: 0,

		init: function() {
			this.template = Handlebars.compile(this.TEMPLATE);
			this.$list = $('div.signaling-servers');
			this.$secret = $('#signaling_secret');
			this.renderList();

			this.$secret.on('change', this.saveServers.bind(this));
		},

		renderList: function() {
			var data = this.$list.data('servers');

			var hasServers = false;
			if (!_.isUndefined(data.secret)) {
				_.each(data.servers, function (server) {
					this.$list.append(
						this.renderServer(server)
					);
				}.bind(this));

				hasServers = data.servers.length !== 0;

				this.$secret.val(data.secret);
			}

			if (!hasServers) {
				this.addNewTemplate();
			}

			this.$secret.parents('p').first().removeClass('hidden');
		},

		addNewTemplate: function() {
			var $server = this.renderServer({
				validate: true
			});
			this.$list.append($server);
			return $server;
		},

		deleteServer: function(e) {
			e.stopPropagation();

			var $server = $(e.currentTarget).parents('div.signaling-server').first();
			$server.remove();

			this.saveServers();

			if (this.$list.find('div.signaling-server').length === 0) {
				var $newServer = this.addNewTemplate();
				this.temporaryShowSuccess($newServer);
			}
		},

		saveServers: function() {
			var servers = [],
				$error = [],
				$success = [],
				self = this,
				$secret = this.$secret,
				secret = this.$secret.val().trim();

			this.$list.find('input').removeClass('error');
			this.$secret.removeClass('error');
			this.$list.find('.icon-checkmark-color').addClass('hidden');

			this.$list.find('div.signaling-server').each(function() {
				var $row = $(this),
					$server = $row.find('input.server'),
					$verify = $row.find('input.verify'),
					data = {
						server: $server.val().trim(),
						verify: $verify.val() === '1'
					};

				if (data.server === '') {
					$error.push($server);
					return;
				}

				if (secret === '') {
					$error.push($secret);
					return;
				}

				$success.push($(this));
				servers.push(data);
			});

			OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
				servers: servers,
				secret: secret
			}), {
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
			server.seed = this.seed++;
			var $template = $(this.template(server));

			$template.find('a.icon-add').on('click', this.addNewTemplate.bind(this));
			$template.find('a.icon-delete').on('click', this.deleteServer.bind(this));
			$template.find('input').on('change', this.saveServers.bind(this));

			return $template;
		}

	};


})(OC, OCP, OCA, $, _, Handlebars);

$(document).ready(function(){
	OCA.VideoCalls.Admin.SignalingServer.init();
});

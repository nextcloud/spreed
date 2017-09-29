/* global OC, OCP, OCA, $, _, Handlebars */

(function(OC, OCP, OCA, $, _, Handlebars) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.TurnServer = {

		TEMPLATE: '<div class="turn-server">' +
		'	<input type="text" class="server" placeholder="https://turn.example.org" value="{{server}}">' +
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
			this.$list.append(
				this.renderServer({})
			);
		},

		deleteServer: function(e) {
			e.stopPropagation();

			var $server = $(e.currentTarget).parents('div.turn-server').first();
			$server.remove();

			this.saveServers();

			if (this.$list.find('div.turn-server').length === 0) {
				this.addNewTemplate();
			}
		},

		saveServers: function() {
			var servers = [];

			this.$list.find('input').removeClass('error');
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
					$server.addClass('error');
				}
				if (data.secret === '') {
					$secret.addClass('error');
				}
				servers.push(data);
			});

			OCP.AppConfig.setValue('spreed', 'turn_server', JSON.stringify(servers));
		},

		renderServer: function(server) {
			var $template = $(this.template(server));

			$template.find('a.icon-add').on('click', this.addNewTemplate.bind(this));
			$template.find('a.icon-delete').on('click', this.deleteServer.bind(this));
			$template.find('input').on('change', this.saveServers.bind(this));

			return $template;
		}

	};


})(OC, OCP, OCA, $, _, Handlebars);

$(document).ready(function(){
	OCA.VideoCalls.Admin.TurnServer.init();
});

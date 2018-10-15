/* global OC, OCP, OCA, $, _, Handlebars, jsSHA */

(function(OC, OCP, OCA, $, _, Handlebars) {
	'use strict';

	OCA.VideoCalls = OCA.VideoCalls || {};
	OCA.VideoCalls.Admin = OCA.VideoCalls.Admin || {};
	OCA.VideoCalls.Admin.TurnServer = {

		$list: undefined,
		template: undefined,

		init: function() {
			Handlebars.registerHelper('select', this._handlebarSelectOption);
			this.template = OCA.VideoCalls.Admin.Templates['turn-server'];
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

		notifyTurnResult: function($button, $candidates, $timeout) {
			console.log("Received candidates", $candidates);
			$button.removeClass('icon-loading');
			var $types = $candidates.map(function($cand) {
				return $cand.type;
			});
			var $class;
			if ($types.indexOf('relay') === -1) {
				$class = 'icon-error';
			} else {
				$class = 'icon-checkmark';
			}
			$button.addClass($class);
			$button.removeClass('icon-category-monitoring');
			setTimeout(function() {
				$button.removeClass($class);
				$button.addClass('icon-category-monitoring');
			}, 7000);
			clearTimeout($timeout);
		},

		// Parse a candidate:foo string into an object, for easier use by other methods.
		parseCandidate: function($text) {
			var $candidateStr = 'candidate:';
			var $pos = $text.indexOf($candidateStr) + $candidateStr.length;
			var $parts = $text.substr($pos).split(' ');
			var $foundation = $parts[0];
			var $component = $parts[1];
			var $protocol = $parts[2];
			var $priority = $parts[3];
			var $address = $parts[4];
			var $port = $parts[5];
			var $type = $parts[7];
			return {
				'component': $component,
				'type': $type,
				'foundation': $foundation,
				'protocol': $protocol,
				'address': $address,
				'port': $port,
				'priority': $priority
			};
		},

		iceCallback: function($pc, $button, $candidates, $timeout, e) {
			if (e.candidate) {
				$candidates.push(this.parseCandidate(e.candidate.candidate));
			} else if (!('onicegatheringstatechange' in RTCPeerConnection.prototype)) {
				$pc.close();
				this.notifyTurnResult($button, $candidates, $timeout);
			}
		},

		gatheringStateChange: function($pc, $button, $candidates, $timeout) {
			if ($pc.iceGatheringState !== 'complete') {
				return;
			}

			$pc.close();
			this.notifyTurnResult($button, $candidates, $timeout);
		},

		testServer: function(e) {
			e.stopPropagation();

			var $button = $(e.currentTarget);
			var $row = $button.parents('div.turn-server').first();
			var $server = $row.find('input.server').val();
			var $secret = $row.find('input.secret').val();
			var $protocols = $row.find('select.protocols').val().split(',');
			if (!$server || !$secret || !$protocols.length) {
				return;
			}

			var $urls = [];
			var i;
			for (i = 0; i < $protocols.length; i++) {
				$urls.push('turn:' + $server + '?transport=' + $protocols[i]);
			}

			var $now = new Date();
			var $expires = Math.round($now.getTime() / 1000) + (5 * 60);
			var $username = $expires + ':turn-test-user';
			var $hmac = new jsSHA("SHA-1", "TEXT");
			$hmac.setHMACKey($secret, "TEXT");
			$hmac.update($username);
			var $password = $hmac.getHMAC("B64");
			var $iceServer = {
				'username': $username,
				'credential': $password,
				'urls': $urls
			};

			// Create a PeerConnection with no streams, but force a m=audio line.
			var $config = {
				iceServers: [
					$iceServer
				],
				iceTransportPolicy: 'relay'
			};
			var $offerOptions = {
				offerToReceiveAudio: 1
			};
			console.log('Creating PeerConnection with', $config);
			var $candidates = [];
			$button.addClass('icon-loading');
			var $pc = new RTCPeerConnection($config);
			var $timeout = setTimeout(function() {
				this.notifyTurnResult($button, $candidates, $timeout);
				$pc.close();
			}.bind(this), 10000);
			$pc.onicecandidate = this.iceCallback.bind(this, $pc, $button, $candidates, $timeout);
			$pc.onicegatheringstatechange = this.gatheringStateChange.bind(this, $pc, $button, $candidates, $timeout);
			$pc.createOffer(
				$offerOptions
			).then(
				function(description) {
					$pc.setLocalDescription(description);
				},
				function(error) {
					console.log("Error creating offer", error);
					this.notifyTurnResult($button, $candidates, $timeout);
					$pc.close();
				}.bind(this)
			);
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
			var $template = $(this.template(_.extend(
				{
					turnTXT: t('spreed', 'TURN server URL'),
					sharedSecretTXT: t('spreed', 'Shared secret'),
					sharedSecretDescTXT: t('spreed', 'TURN server shared secret'),
					UDPTCPTXT: t('spreed', 'UDP and TCP'),
					UDPTXT: t('spreed', 'UDP only'),
					TCPTXT: t('spreed', 'TCP only'),
					testTXT: t('spreed', 'Test server'),
					deleteTXT: t('spreed', 'Delete server'),
					newTXT: t('spreed', 'Add new server'),
					savedTXT: t('spreed', 'Saved'),
					protocolsTXT: t('spreed', 'TURN server protocols'),
				},server)));

			$template.find('a.icon-add').on('click', this.addNewTemplate.bind(this));
			$template.find('a.icon-delete').on('click', this.deleteServer.bind(this));
			$template.find('a.icon-category-monitoring').on('click', this.testServer.bind(this));
			$template.find('input').on('change', this.saveServers.bind(this));
			$template.find('select').on('change', this.saveServers.bind(this));

			return $template;
		}

	};


})(OC, OCP, OCA, $, _, Handlebars);

$(document).ready(function(){
	OCA.VideoCalls.Admin.TurnServer.init();
});

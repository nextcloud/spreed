<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license GNU AGPL version 3 or any later version
 -
 - This program is free software: you can redistribute it and/or modify
 - it under the terms of the GNU Affero General Public License as
 - published by the Free Software Foundation, either version 3 of the
 - License, or (at your option) any later version.
 -
 - This program is distributed in the hope that it will be useful,
 - but WITHOUT ANY WARRANTY; without even the implied warranty of
 - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 - GNU Affero General Public License for more details.
 -
 - You should have received a copy of the GNU Affero General Public License
 - along with this program. If not, see <http://www.gnu.org/licenses/>.
 -
 -->

<template>
	<div id="stun_server" class="videocalls section">
		<h2>{{ t('spreed', 'STUN servers') }}</h2>

		<p class="settings-hint">{{ t('spreed', 'A STUN server is used to determine the public IP address of participants behind a router.') }}</p>

		<div class="stun-servers">
			<StunServer v-for="(s, i) in servers" :server="servers[i]" :key="i"
						@removeServer="removeServer(i)" @newServer="newServer"></StunServer>
		</div>
	</div>
</template>

<script>
	import StunServer from './components/StunServer';

	export default {
		name: 'app',

		data () {
			return {
				servers: []
			}
		},

		components: {
			StunServer
		},

		methods: {
			removeServer (i) {
				console.log(i);
				console.log(this.servers);
				this.servers.splice(i, 1);
				console.log(this.servers);
			},

			newServer () {
				this.servers.push('');
			},

			addDefaultServer () {
				this.servers.push('stun.nextcloud.com:443');
			},

			saveServers () {

			}
		},

		mounted () {
			this.servers = OCP.InitialState.loadState('talk', 'stun_servers');
		}
	}
</script>

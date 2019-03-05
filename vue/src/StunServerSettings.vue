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
		<h2>
			{{ t('spreed', 'STUN servers') }}
			<span class="icon icon-checkmark-color" v-if="saved" :title="t('spreed', 'Saved')"></span>
			<a class="icon icon-add" @click="newServer" v-else-if="!loading" v-tooltip.auto="t('spreed', 'Add a new server')">
				<span class="hidden-visually">{{ t('spreed', 'Add a new server') }}</span>
			</a>
			<span class="icon icon-loading-small" v-else></span>
		</h2>

		<p class="settings-hint">{{ t('spreed', 'A STUN server is used to determine the public IP address of participants behind a router.') }}</p>

		<div class="stun-servers">
			<StunServer
				v-for="(server, index) in servers"
				:server.sync="servers[index]"
				:key="index"
				:index="index"
				:loading="loading"
				@removeServer="removeServer" />
		</div>
	</div>
</template>

<script>
import { Tooltip } from 'nextcloud-vue'
import debounce from 'debounce'
import StunServer from './components/StunServer';

export default {
	name: 'app',

	data () {
		return {
			servers: [],
			loading: false,
			saved: false
		}
	},

	directives: {
		tooltip: Tooltip
	},

	components: {
		StunServer
	},

	watch: {
		servers: debounce(function(cur, old) {
			// ignore empty inputs and make sure the
			// array was not empty before (initial load)
			if (cur.join('') !== old.join('') && old.length !== 0) {
				this.updateServers()
			}
		}, 1000)
	},

	methods: {
		removeServer(index) {
			this.servers.splice(index, 1);
			if (this.servers.length === 0) {
				this.addDefaultServer()
			}
		},

		newServer() {
			this.servers.push('');
		},

		addDefaultServer() {
			this.servers.push('stun.nextcloud.com:443');
		},

		async updateServers() {
			this.loading = true
			// TODO: your request instead of the timeout
			setTimeout(() => {
				this.loading = false
				this.toggleSave()
			}, 2000)
		},

		toggleSave() {
			this.saved = true
			setTimeout(() => {
				this.saved = false
			}, 3000)
		}
	},

	beforeMount () {
		this.servers = OCP.InitialState.loadState('talk', 'stun_servers');
	}
}
</script>

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
	<div id="signaling_server" class="videocalls section">
		<h2>
			{{ t('spreed', 'Signaling servers') }}
			<span v-if="saved" class="icon icon-checkmark-color" :title="t('spreed', 'Saved')" />
			<a v-else-if="!loading" v-tooltip.auto="t('spreed', 'Add a new server')" class="icon icon-add"
				@click="newServer">
				<span class="hidden-visually">{{ t('spreed', 'Add a new server') }}</span>
			</a>
			<span v-else class="icon icon-loading-small" />
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'An external signaling server should optionally be used for larger installations. Leave empty to use the internal signaling server.') }}
		</p>

		<ul class="turn-servers">
			<transition-group name="fade" tag="li">
				<signaling-server
					v-for="(server, index) in servers"
					:key="`server${index}`"
					:server.sync="servers[index].server"
					:verify.sync="servers[index].verify"
					:index="index"
					:loading="loading"
					@removeServer="removeServer"
					@update:server="debounceUpdateServers"
					@update:verify="debounceUpdateServers" />
			</transition-group>
		</ul>

		<div class="signaling-secret">
			<h4>{{ t('spreed', 'Shared secret') }}</h4>
			<input type="text" name="signaling_secret" :disabled="loading"
				:placeholder="t('spreed', 'Shared secret')" :value="secret"
				:aria-label="t('spreed', 'Shared secret')" @update="debounceUpdateServers">
		</div>
	</div>
</template>

<script>
import { Tooltip } from 'nextcloud-vue'
import debounce from 'debounce'
import SignalingServer from './components/SignalingServer'

export default {
	name: 'App',

	directives: {
		tooltip: Tooltip
	},

	components: {
		SignalingServer
	},

	data() {
		return {
			servers: [],
			secret: '',
			loading: false,
			saved: false
		}
	},

	beforeMount() {
		const state = OCP.InitialState.loadState('talk', 'signaling_servers')
		this.servers = state.servers
		this.secret = state.secret
	},

	methods: {
		removeServer(index) {
			this.servers.splice(index, 1)
			this.debounceUpdateServers()
		},

		newServer() {
			this.servers.push({
				server: '',
				verify: false
			})
		},

		debounceUpdateServers: debounce(function() {
			this.updateServers()
		}, 1000),

		async updateServers() {
			this.loading = true
			// TODO: your request instead of the timeout
			setTimeout(() => {
				this.loading = false
				this.toggleSave()
			}, 2000)
			var servers = []

			this.servers.forEach((server) => {
				const data = {
					server: server.server,
					verify: server.verify
				}

				if (data.server === '') {
					return
				}

				servers.push(data)
			})

			this.servers = servers

			const self = this

			this.loading = true
			OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
				servers: servers,
				secret: this.secret
			}), {
				success() {
					self.loading = false
					self.toggleSave()
				}
			})
		},

		toggleSave() {
			this.saved = true
			setTimeout(() => {
				this.saved = false
			}, 3000)
		}
	}
}
</script>

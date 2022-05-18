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
			{{ t('spreed', 'High-performance backend') }}
			<span v-if="saved" class="icon icon-checkmark-color" :title="t('spreed', 'Saved')" />
			<a v-else-if="!loading && showAddServerButton"
				v-tooltip.auto="t('spreed', 'Add a new server')"
				class="icon icon-add"
				@click="newServer">
				<span class="hidden-visually">{{ t('spreed', 'Add a new server') }}</span>
			</a>
			<span v-else-if="loading" class="icon icon-loading-small" />
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'An external signaling server should optionally be used for larger installations. Leave empty to use the internal signaling server.') }}
			<span v-if="!servers.length">{{ t('spreed', 'Please note that calls with more than 4 participants without external signaling server, participants can experience connectivity issues and cause high load on participating devices.') }}</span>
		</p>

		<p v-if="!isCacheConfigured"
			class="settings-hint warning">
			{{ t('spreed', 'It is highly recommended to set up a distributed cache when using Nextcloud Talk together with a High Performance Back-end.') }}
		</p>

		<div v-if="!servers.length" class="signaling-warning">
			<input id="hide_warning"
				v-model="hideWarning"
				type="checkbox"
				name="hide_warning"
				class="checkbox"
				:disabled="loading"
				@change="updateHideWarning">
			<label for="hide_warning">{{ t('spreed', 'Don\'t warn about connectivity issues in calls with more than 4 participants') }}</label>
		</div>

		<ul class="turn-servers">
			<transition-group name="fade" tag="li">
				<SignalingServer v-for="(server, index) in servers"
					:key="`server${index}`"
					:server.sync="servers[index].server"
					:verify.sync="servers[index].verify"
					:index="index"
					:loading="loading"
					@remove-server="removeServer"
					@update:server="debounceUpdateServers"
					@update:verify="debounceUpdateServers" />
			</transition-group>
		</ul>

		<div class="signaling-secret">
			<h4>{{ t('spreed', 'Shared secret') }}</h4>
			<input v-model="secret"
				type="text"
				name="signaling_secret"
				:disabled="loading"
				:placeholder="t('spreed', 'Shared secret')"
				:aria-label="t('spreed', 'Shared secret')"
				@input="debounceUpdateServers">
		</div>
	</div>
</template>

<script>
import SignalingServer from '../../components/AdminSettings/SignalingServer.vue'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { loadState } from '@nextcloud/initial-state'
import debounce from 'debounce'
import { SIGNALING } from '../../constants.js'

export default {
	name: 'SignalingServers',

	directives: {
		tooltip: Tooltip,
	},

	components: {
		SignalingServer,
	},

	data() {
		return {
			servers: [],
			secret: '',
			hideWarning: false,
			loading: false,
			saved: false,
			isCacheConfigured: loadState('spreed', 'has_cache_configured'),
			isClusteredMode: loadState('spreed', 'signaling_mode') === SIGNALING.MODE.CLUSTER_CONVERSATION,
		}
	},

	computed: {
		showAddServerButton() {
			return this.isClusteredMode || this.servers.length === 0
		},
	},

	beforeMount() {
		const state = loadState('spreed', 'signaling_servers')
		this.servers = state.servers
		this.secret = state.secret
		this.hideWarning = state.hideWarning
	},

	methods: {
		removeServer(index) {
			this.servers.splice(index, 1)
			this.debounceUpdateServers()
		},

		newServer() {
			this.servers.push({
				server: '',
				verify: false,
			})
		},

		updateHideWarning() {
			const self = this
			self.loading = true

			OCP.AppConfig.setValue('spreed', 'hide_signaling_warning', this.hideWarning ? 'yes' : 'no', {
				success() {
					self.loading = false
					self.toggleSave()
				},
			})
		},

		debounceUpdateServers: debounce(function() {
			this.updateServers()
		}, 1000),

		async updateServers() {
			this.loading = true

			this.servers = this.servers.filter(server => server.server.trim() !== '')

			const self = this
			OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
				servers: this.servers,
				secret: this.secret,
			}), {
				success() {
					self.loading = false
					self.toggleSave()
				},
			})
		},

		toggleSave() {
			this.saved = true
			setTimeout(() => {
				this.saved = false
			}, 3000)
		},
	},
}
</script>

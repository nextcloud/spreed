<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 - @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
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
	<div id="recording_server" class="videocalls section recording-server">
		<h2>
			{{ t('spreed', 'Recording backend') }}

			<NcButton v-if="!loading && showAddServerButton"
				class="recording-server__add-icon"
				type="tertiary-no-background"
				:aria-label="t('spreed', 'Add a new recording backend server')"
				@click="newServer">
				<template #icon>
					<Plus :size="20" />
				</template>
			</NcButton>
		</h2>

		<ul class="recording-servers">
			<transition-group name="fade" tag="li">
				<RecordingServer v-for="(server, index) in servers"
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

		<div class="recording-secret">
			<h4>{{ t('spreed', 'Shared secret') }}</h4>
			<input v-model="secret"
				type="text"
				name="recording_secret"
				:disabled="loading"
				:placeholder="t('spreed', 'Shared secret')"
				:aria-label="t('spreed', 'Shared secret')"
				@input="debounceUpdateServers">
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import RecordingServer from '../../components/AdminSettings/RecordingServer.vue'

export default {
	name: 'RecordingServers',

	components: {
		NcButton,
		Plus,
		RecordingServer,
	},

	data() {
		return {
			servers: [],
			secret: '',
			loading: false,
			saved: false,
		}
	},

	computed: {
		showAddServerButton() {
			return this.servers.length === 0
		},
	},

	beforeMount() {
		const state = loadState('spreed', 'recording_servers')
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
				verify: false,
			})
		},

		debounceUpdateServers: debounce(function() {
			this.updateServers()
		}, 1000),

		async updateServers() {
			this.loading = true

			this.servers = this.servers.filter(server => server.server.trim() !== '')

			const self = this
			OCP.AppConfig.setValue('spreed', 'recording_servers', JSON.stringify({
				servers: this.servers,
				secret: this.secret,
			}), {
				success() {
					showSuccess(t('spreed', 'Recording backend settings saved'))
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

<style lang="scss" scoped>
@import '../../assets/variables';

.recording-server {
	h2 {
		height: 44px;
		display: flex;
		align-items: center;
	}
}

.recording-secret {
	margin-top: 20px;
}
</style>

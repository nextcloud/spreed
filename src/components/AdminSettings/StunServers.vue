<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
 -
 - @license AGPL-3.0-or-later
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
	<section id="stun_server" class="videocalls section">
		<h2>
			{{ t('spreed', 'STUN servers') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'A STUN server is used to determine the public IP address of participants behind a router.') }}
		</p>

		<TransitionWrapper name="fade"
			class="stun-servers"
			tag="ul"
			group>
			<StunServer v-for="(server, index) in servers"
				:key="`server${index}`"
				:server.sync="servers[index]"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:server="debounceUpdateServers" />
		</TransitionWrapper>

		<NcButton class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<span v-if="loading" class="icon icon-loading-small" />
				<Plus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add a new STUN server') }}
		</NcButton>
	</section>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import StunServer from '../../components/AdminSettings/StunServer.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

export default {
	name: 'StunServers',

	components: {
		NcButton,
		StunServer,
		Plus,
		TransitionWrapper,
	},

	data() {
		return {
			servers: [],
			hasInternetConnection: true,
			loading: false,
			saved: false,
			debounceUpdateServers: () => {},
		}
	},

	beforeMount() {
		this.servers = loadState('spreed', 'stun_servers')
		this.hasInternetConnection = loadState('spreed', 'has_internet_connection')
		this.debounceUpdateServers = debounce(this.updateServers, 1000)
	},

	beforeDestroy() {
		this.debounceUpdateServers.clear?.()
	},

	methods: {
		removeServer(index) {
			this.servers.splice(index, 1)
			if (this.servers.length === 0) {
				this.addDefaultServer()
			}
			this.debounceUpdateServers()
		},

		newServer() {
			this.servers.push('')
		},

		addDefaultServer() {
			if (this.hasInternetConnection) {
				this.servers.push('stun.nextcloud.com:443')
			}
		},

		async updateServers() {
			this.loading = true
			const servers = []

			this.servers.forEach((server) => {

				if (server.startsWith('https://')) {
					server = server.slice(8)
				} else if (server.startsWith('http://')) {
					server = server.slice(7)
				}

				servers.push(server)
			})

			this.servers = servers

			OCP.AppConfig.setValue('spreed', 'stun_servers', JSON.stringify(servers), {
				success: () => {
					showSuccess(t('spreed', 'STUN settings saved'))
					this.loading = false
					this.toggleSave()
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

<style lang="scss">
.additional-top-margin {
	margin-top: 10px;
}

</style>

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
	<div id="stun_server" class="videocalls section stun-server">
		<h2>
			{{ t('spreed', 'STUN servers') }}

			<Button v-if="!loading"
				class="stun-server__add-icon"
				type="tertiary-no-background"
				:aria-label="t('spreed', 'Add a new STUN server')"
				@click="newServer">
				<template #icon>
					<Plus />
				</template>
			</Button>
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'A STUN server is used to determine the public IP address of participants behind a router.') }}
		</p>

		<ul class="stun-servers">
			<transition-group name="fade" tag="li">
				<StunServer v-for="(server, index) in servers"
					:key="`server${index}`"
					:server.sync="servers[index]"
					:index="index"
					:loading="loading"
					@remove-server="removeServer"
					@update:server="debounceUpdateServers" />
			</transition-group>
		</ul>
	</div>
</template>

<script>
import StunServer from '../../components/AdminSettings/StunServer.vue'
import Button from '@nextcloud/vue/dist/Components/Button'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import Plus from 'vue-material-design-icons/Plus'
import debounce from 'debounce'
import { loadState } from '@nextcloud/initial-state'
import { showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'StunServers',

	directives: {
		tooltip: Tooltip,
	},

	components: {
		Button,
		StunServer,
		Plus,
	},

	data() {
		return {
			servers: [],
			hasInternetConnection: true,
			loading: false,
			saved: false,
		}
	},

	beforeMount() {
		this.servers = loadState('spreed', 'stun_servers')
		this.hasInternetConnection = loadState('spreed', 'has_internet_connection')
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

		debounceUpdateServers: debounce(function() {
			this.updateServers()
		}, 1000),

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
			const self = this

			OCP.AppConfig.setValue('spreed', 'stun_servers', JSON.stringify(servers), {
				success() {
					showSuccess(t('spreed', 'STUN settings saved'))
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

<style lang="scss">
.stun-server {
	h2 {
		height: 44px;
		display: flex;
		align-items: center;
	}

	&__add-icon {
		display: inline-block;
		width: 44px;
		height: 44px;
		vertical-align: middle;
	}
}

</style>

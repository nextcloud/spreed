<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="turn_server" class="videocalls section">
		<h2>
			{{ t('spreed', 'TURN servers') }}
		</h2>

		<!-- eslint-disable-next-line vue/no-v-html -->
		<p class="settings-hint" v-html="documentationHint" />

		<TransitionWrapper class="turn-servers"
			name="fade"
			tag="ul"
			group>
			<TurnServer v-for="(server, index) in servers"
				:key="`server${index}`"
				v-model:schemes="servers[index].schemes"
				v-model:server="servers[index].server"
				v-model:secret="servers[index].secret"
				v-model:protocols="servers[index].protocols"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:schemes="debounceUpdateServers"
				@update:server="debounceUpdateServers"
				@update:secret="debounceUpdateServers"
				@update:protocols="debounceUpdateServers" />
		</TransitionWrapper>

		<NcButton class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<span v-if="loading" class="icon icon-loading-small" />
				<Plus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add a new TURN server') }}
		</NcButton>
	</section>
</template>

<script>
import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import NcButton from '@nextcloud/vue/components/NcButton'
import Plus from 'vue-material-design-icons/Plus.vue'
import TurnServer from '../../components/AdminSettings/TurnServer.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

export default {
	name: 'TurnServers',

	components: {
		NcButton,
		TurnServer,
		Plus,
		TransitionWrapper,
	},

	data() {
		return {
			servers: [],
			loading: false,
			saved: false,
			debounceUpdateServers: () => {},
		}
	},

	computed: {
		documentationHint() {
			return t('spreed', 'A TURN server is used to proxy the traffic from participants behind a firewall. If individual participants cannot connect to others a TURN server is most likely required. See {linkstart}this documentation{linkend} for setup instructions.')
				.replace('{linkstart}', '<a  target="_blank" rel="noreferrer nofollow" class="external" href="https://nextcloud-talk.readthedocs.io/en/latest/TURN/">')
				.replace('{linkend}', ' ↗</a>')
		},
	},

	beforeMount() {
		this.debounceUpdateServers = debounce(this.updateServers, 1000)
		this.servers = loadState('spreed', 'turn_servers')
	},

	beforeUnmount() {
		this.debounceUpdateServers.clear?.()
	},

	methods: {
		t,
		removeServer(index) {
			this.servers.splice(index, 1)
			this.debounceUpdateServers()
		},

		newServer() {
			this.servers.push({
				schemes: 'turn', // default to turn only
				server: '',
				secret: '',
				protocols: 'udp,tcp', // default to udp AND tcp
			})
		},

		async updateServers() {
			const servers = []

			this.servers.forEach((server) => {
				const data = {
					schemes: server.schemes,
					server: server.server,
					secret: server.secret,
					protocols: server.protocols,
				}

				if (data.server.startsWith('https://')) {
					data.server = data.server.slice(8)
				} else if (data.server.startsWith('http://')) {
					data.server = data.server.slice(7)
				}

				if (data.secret === '') {
					return
				}

				servers.push(data)
			})

			this.loading = true
			OCP.AppConfig.setValue('spreed', 'turn_servers', JSON.stringify(servers), {
				success: () => {
					showSuccess(t('spreed', 'TURN settings saved'))
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

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
	<section id="signaling_server" class="signaling-servers section">
		<h2>
			{{ t('spreed', 'High-performance backend') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'An external signaling server should optionally be used for larger installations. Leave empty to use the internal signaling server.') }}
		</p>

		<NcNoteCard v-if="!isCacheConfigured" type="warning">
			{{ t('spreed', 'It is highly recommended to set up a distributed cache when using Nextcloud Talk together with a High Performance Back-end.') }}
		</NcNoteCard>

		<TransitionWrapper v-if="servers.length"
			name="fade"
			tag="ul"
			group>
			<SignalingServer v-for="(server, index) in servers"
				:key="`server${index}`"
				:server.sync="servers[index].server"
				:verify.sync="servers[index].verify"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:server="debounceUpdateServers"
				@update:verify="debounceUpdateServers" />
		</TransitionWrapper>

		<NcButton v-if="!servers.length || isClusteredMode"
			class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<span v-if="loading" class="icon icon-loading-small" />
				<Plus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add a new high-performance backend server') }}
		</NcButton>

		<template v-if="!servers.length">
			<p class="settings-hint additional-top-margin">
				{{ t('spreed', 'Please note that in calls with more than 4 participants without external signaling server, participants can experience connectivity issues and cause high load on participating devices.') }}
			</p>
			<NcCheckboxRadioSwitch :checked.sync="hideWarning"
				:disabled="loading"
				@update:checked="updateHideWarning">
				{{ t('spreed', 'Don\'t warn about connectivity issues in calls with more than 4 participants') }}
			</NcCheckboxRadioSwitch>
		</template>

		<NcTextField class="form__textfield additional-top-margin"
			:value="secret"
			name="signaling_secret"
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:label="t('spreed', 'Shared secret')"
			label-visible
			@update:value="updateSecret" />
	</section>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

import SignalingServer from '../../components/AdminSettings/SignalingServer.vue'
import TransitionWrapper from '../TransitionWrapper.vue'

import { SIGNALING } from '../../constants.js'

export default {
	name: 'SignalingServers',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcTextField,
		Plus,
		SignalingServer,
		TransitionWrapper,
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
					showSuccess(t('spreed', 'Missing high-performance backend warning hidden'))
					self.loading = false
					self.toggleSave()
				},
			})
		},

		updateSecret(value) {
			this.secret = value
			this.debounceUpdateServers()
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
					showSuccess(t('spreed', 'High-performance backend settings saved'))
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
.signaling-servers {
	.form__textfield {
		width: 300px;
	}
}

.additional-top-margin {
	margin-top: 10px;
}
</style>

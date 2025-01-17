<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="signaling_server" class="signaling-servers section">
		<h2>
			{{ t('spreed', 'High-performance backend') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'The High-performance backend is required for calls and conversations with multiple participants. Without the backend, all participants have to upload their own video individually for each other participant, which will most likely cause connectivity issues and a high load on participating devices.') }}
		</p>

		<NcNoteCard v-if="servers.length && !isCacheConfigured"
			type="warning"
			:text="t('spreed', 'It is highly recommended to set up a distributed cache when using Nextcloud Talk with a High-performance backend.')" />

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
			{{ t('spreed', 'Add High-performance backend server') }}
		</NcButton>

		<NcPasswordField v-if="servers.length"
			v-model="secret"
			class="form__textfield additional-top-margin"
			name="signaling_secret"
			as-text
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:label="t('spreed', 'Shared secret')"
			label-visible
			@update:model-value="debounceUpdateServers" />

		<template v-if="!servers.length">
			<NcNoteCard type="warning"
				class="additional-top-margin"
				:text="t('spreed', 'Please note that in calls with more than 2 participants without the High-performance backend, participants will most likely experience connectivity issues and cause high load on participating devices.')" />
			<NcCheckboxRadioSwitch v-model="hideWarning"
				:disabled="loading"
				@update:model-value="updateHideWarning">
				{{ t('spreed', 'Don\'t warn about connectivity issues in calls with more than 2 participants') }}
			</NcCheckboxRadioSwitch>
		</template>
	</section>
</template>

<script>
import debounce from 'debounce'

import Plus from 'vue-material-design-icons/Plus.vue'

import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import NcPasswordField from '@nextcloud/vue/dist/Components/NcPasswordField.js'

import SignalingServer from '../../components/AdminSettings/SignalingServer.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'

import { SIGNALING } from '../../constants.js'
import { EventBus } from '../../services/EventBus.ts'

export default {
	name: 'SignalingServers',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcNoteCard,
		NcPasswordField,
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
			debounceUpdateServers: () => {},
		}
	},

	beforeMount() {
		this.debounceUpdateServers = debounce(this.updateServers, 1000)
		const state = loadState('spreed', 'signaling_servers')
		this.servers = state.servers
		this.secret = state.secret
		this.hideWarning = state.hideWarning
	},

	beforeDestroy() {
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
				server: '',
				verify: true,
			})
		},

		updateHideWarning() {
			this.loading = true

			OCP.AppConfig.setValue('spreed', 'hide_signaling_warning', this.hideWarning ? 'yes' : 'no', {
				success: () => {
					showSuccess(t('spreed', 'Missing High-performance backend warning hidden'))
					this.loading = false
					this.toggleSave()
				},
			})
		},

		async updateServers() {
			this.loading = true

			this.servers = this.servers.filter(server => server.server.trim() !== '')

			OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
				servers: this.servers,
				secret: this.secret,
			}), {
				success: () => {
					showSuccess(t('spreed', 'High-performance backend settings saved'))
					EventBus.emit('signaling-servers-updated', this.servers)
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

<style lang="scss" scoped>
.signaling-servers {
	.form__textfield {
		width: 300px;
	}
}

.additional-top-margin {
	margin-top: 35px !important;
}
</style>

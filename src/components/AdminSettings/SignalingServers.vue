<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="signaling_server" class="signaling-servers section">
		<NcNoteCard v-if="!servers.length"
			type="error"
			:heading="t('spreed', 'Nextcloud Talk setup not complete')"
			:text="t('spreed', 'Install the High-performance backend to ensure calls with multiple participants work seamlessly.')" />

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

<script setup lang="ts">
import debounce from 'debounce'
import { ref, onBeforeUnmount } from 'vue'

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
import type { InitialState } from '../../types/index.ts'

const isCacheConfigured = loadState('spreed', 'has_cache_configured')
const isClusteredMode = loadState('spreed', 'signaling_mode') === SIGNALING.MODE.CLUSTER_CONVERSATION

const state = loadState<InitialState['spreed']['signaling_servers']>('spreed', 'signaling_servers')
const servers = ref(state.servers ?? [])
const secret = ref(state.secret ?? '')
const hideWarning = ref(state.hideWarning ?? false)

const loading = ref(false)

const debounceUpdateServers = debounce(updateServers, 1000)

onBeforeUnmount(() => {
	debounceUpdateServers.clear?.()
})

/**
 * Removes HPB server from the list
 * @param index index of server (remnant from clustered setup, should be always 0)
 */
function removeServer(index: number) {
	servers.value.splice(index, 1)
	debounceUpdateServers()
}

/**
 * Adds HPB server to the list
 */
function newServer() {
	servers.value.push({ server: '', verify: true })
}

/**
 * Update hideWarning value on server
 */
function updateHideWarning() {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'hide_signaling_warning', hideWarning.value ? 'yes' : 'no', {
		success: () => {
			showSuccess(t('spreed', 'Missing High-performance backend warning hidden'))
			loading.value = false
		},
	})
}

/**
 * Update servers list / secret value on server
 */
function updateServers() {
	loading.value = true

	servers.value = servers.value.filter(server => server.server.trim() !== '')

	OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
		servers: servers.value,
		secret: secret.value,
	}), {
		success: () => {
			showSuccess(t('spreed', 'High-performance backend settings saved'))
			EventBus.emit('signaling-servers-updated', servers.value)
			loading.value = false
		},
	})
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

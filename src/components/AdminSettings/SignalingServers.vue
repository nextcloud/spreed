<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="signaling_server" class="signaling-servers section">
		<NcNoteCard v-if="!serversProxy.length"
			type="warning"
			:heading="t('spreed', 'Nextcloud Talk setup not complete')">
			{{ t('spreed', 'Please note that in calls with more than 2 participants without the High-performance backend, participants will most likely experience connectivity issues and cause high load on participating devices.') }}
			{{ t('spreed', 'Install the High-performance backend to ensure calls with multiple participants work seamlessly.') }}

			<NcButton v-if="props.hasValidSubscription"
				variant="primary"
				class="additional-top-margin"
				href="https://portal.nextcloud.com/article/Nextcloud-Talk/High-Performance-Backend/Installation-of-Nextcloud-Talk-High-Performance-Backend">
				{{ t('spreed', 'Nextcloud portal') }} ↗
			</NcButton>
			<NcButton v-else
				variant="primary"
				class="additional-top-margin"
				href="https://nextcloud-talk.readthedocs.io/en/latest/quick-install/">
				{{ t('spreed', 'Quick installation guide') }} ↗
			</NcButton>
		</NcNoteCard>

		<h2>
			{{ t('spreed', 'High-performance backend') }}
		</h2>

		<p class="settings-hint">
			{{ t('spreed', 'The High-performance backend is required for calls and conversations with multiple participants. Without the backend, all participants have to upload their own video individually for each other participant, which will most likely cause connectivity issues and a high load on participating devices.') }}
		</p>

		<NcNoteCard v-if="serversProxy.length && !isCacheConfigured"
			type="warning"
			:text="t('spreed', 'It is highly recommended to set up a distributed cache when using Nextcloud Talk with a High-performance backend.')" />

		<ul v-if="serversProxy.length">
			<SignalingServer v-for="(server, index) in serversProxy"
				:key="index"
				v-model:server="server.server"
				v-model:verify="server.verify"
				:index="index"
				:loading="loading"
				@remove-server="removeServer"
				@update:server="debounceUpdateServers"
				@update:verify="debounceUpdateServers" />
		</ul>

		<NcButton v-if="!serversProxy.length || isClusteredMode"
			class="additional-top-margin"
			:disabled="loading"
			@click="newServer">
			<template #icon>
				<NcLoadingIcon v-if="loading" :size="20" />
				<IconPlus v-else :size="20" />
			</template>
			{{ t('spreed', 'Add High-performance backend server') }}
		</NcButton>

		<NcPasswordField v-if="serversProxy.length"
			v-model="secretProxy"
			class="form__textfield additional-top-margin"
			name="signaling_secret"
			as-text
			:disabled="loading"
			:placeholder="t('spreed', 'Shared secret')"
			:label="t('spreed', 'Shared secret')"
			label-visible
			@update:model-value="debounceUpdateServers" />

		<template v-if="!serversProxy.length">
			<NcCheckboxRadioSwitch v-model="showWarningProxy"
				type="switch"
				class="additional-top-margin"
				:disabled="loading"
				@update:model-value="updateHideWarning">
				{{ t('spreed', 'Warn about connectivity issues in calls with more than 2 participants') }}
			</NcCheckboxRadioSwitch>
		</template>
	</section>
</template>

<script setup lang="ts">
import type { InitialState } from '../../types/index.ts'

import { showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import debounce from 'debounce'
import { computed, onBeforeUnmount, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import SignalingServer from '../../components/AdminSettings/SignalingServer.vue'
import { SIGNALING } from '../../constants.ts'

const props = defineProps<{
	hideWarning: InitialState['spreed']['signaling_servers']['hideWarning']
	secret: InitialState['spreed']['signaling_servers']['secret']
	servers: InitialState['spreed']['signaling_servers']['servers']
	hasValidSubscription: InitialState['spreed']['has_valid_subscription']
}>()

const emit = defineEmits<{
	(e: 'update:servers', value: InitialState['spreed']['signaling_servers']['servers']): void
	(e: 'update:secret', value: InitialState['spreed']['signaling_servers']['secret']): void
	(e: 'update:hideWarning', value: InitialState['spreed']['signaling_servers']['hideWarning']): void
}>()

const isCacheConfigured = loadState('spreed', 'has_cache_configured')
const isClusteredMode = loadState('spreed', 'signaling_mode') === SIGNALING.MODE.CLUSTER_CONVERSATION

const loading = ref(false)

const serversProxy = computed({
	get() {
		return props.servers
	},
	set(value) {
		emit('update:servers', value)
	},
})
const secretProxy = computed({
	get() {
		return props.secret
	},
	set(value) {
		emit('update:secret', value)
	},
})
/** Opposite value of hideWarning */
const showWarningProxy = computed({
	get() {
		return !props.hideWarning
	},
	set(value) {
		emit('update:hideWarning', !value)
	},
})

const debounceUpdateServers = debounce(updateServers, 1000)

onBeforeUnmount(() => {
	debounceUpdateServers.clear()
})

/**
 * Removes HPB server from the list
 * @param index index of server (remnant from clustered setup, should be always 0)
 */
function removeServer(index: number) {
	serversProxy.value.splice(index, 1)
	debounceUpdateServers()
}

/**
 * Adds HPB server to the list
 */
function newServer() {
	serversProxy.value.push({ server: '', verify: true })
}

/**
 * Update hideWarning value on server
 * @param showWarning new value
 */
function updateHideWarning(showWarning: boolean) {
	loading.value = true
	/** showWarningProxy is opposite value of hideWarning, so should flip here */
	OCP.AppConfig.setValue('spreed', 'hide_signaling_warning', !showWarning ? 'yes' : 'no', {
		success: () => {
			if (!showWarning) {
				showSuccess(t('spreed', 'Missing High-performance backend warning hidden'))
			}
			loading.value = false
		},
	})
}

/**
 * Update servers list / secret value on server
 */
function updateServers() {
	loading.value = true

	OCP.AppConfig.setValue('spreed', 'signaling_servers', JSON.stringify({
		servers: serversProxy.value.filter((server) => server.server.trim() !== ''),
		secret: secretProxy.value,
	}), {
		success: () => {
			showSuccess(t('spreed', 'High-performance backend settings saved'))
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
	margin-top: 1em !important;
}
</style>

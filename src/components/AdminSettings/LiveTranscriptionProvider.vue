<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { InitialState, LiveTranscriptionProvider, LiveTranscriptionProviderStatus } from '../../types/index.ts'

import { showError, showSuccess } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { t } from '@nextcloud/l10n'
import { computed, onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconReload from 'vue-material-design-icons/Reload.vue'
import { setAppConfig } from '../../services/appConfigService.ts'
import { getLiveTranscriptionProviders, getLiveTranscriptionProviderStatus } from '../../services/liveTranscriptionService.ts'

type ProviderOption = {
	id: string
	label: string
}

defineProps<{
	hasSignalingServers: boolean
}>()

const providers = ref<LiveTranscriptionProvider[]>([])
const providersLoading = ref(true)
const savedAppId = ref(loadState<InitialState['spreed']['live_transcription_app_id']>('spreed', 'live_transcription_app_id', 'live_transcription'))
const loading = ref(false)

const checked = ref(false)
const requestFailed = ref(false)
const providerStatus = ref<LiveTranscriptionProviderStatus | null>(null)

const options = computed<ProviderOption[]>(() => {
	const list = providers.value.map((provider) => ({
		id: provider.id,
		label: provider.name === provider.id
			? provider.id
			: `${provider.name} (${provider.id})`,
	}))

	// Keep the configured app visible while loading or when the list is not available
	if (!list.some((option) => option.id === savedAppId.value)) {
		list.unshift({ id: savedAppId.value, label: savedAppId.value })
	}

	return list
})

const selectedOption = computed(() => options.value.find((option) => option.id === savedAppId.value) ?? null)

const isError = computed(() => requestFailed.value
	|| !providerStatus.value
	|| !['ok', 'no-capabilities'].includes(providerStatus.value.status))

const connectionState = computed(() => {
	if (!checked.value) {
		return t('spreed', 'Status: Checking connection')
	}
	if (requestFailed.value || !providerStatus.value) {
		return t('spreed', 'Error: Unknown error occurred')
	}

	const provider = providerStatus.value.appId
	switch (providerStatus.value.status) {
		case 'app-api':
			return t('spreed', 'Error: The AppAPI app is not enabled')
		case 'not-found':
			return t('spreed', 'Error: No external app with the ID "{appId}" is installed', { appId: provider })
		case 'disabled':
			return t('spreed', 'Error: The external app "{appId}" is disabled', { appId: provider })
		case 'unreachable':
			return t('spreed', 'Error: The external app "{appId}" did not respond', { appId: provider })
		case 'invalid-capabilities':
			return t('spreed', 'Error: The external app "{appId}" did not respond with live transcription capabilities', { appId: provider })
		case 'invalid-languages':
			return t('spreed', 'Error: The external app "{appId}" did not provide any languages for live transcription', { appId: provider })
		case 'no-capabilities':
			return t('spreed', 'OK: The external app "{appId}" provides live transcription, but does not report its capabilities', { appId: provider })
		case 'ok':
			return providerStatus.value.version
				? t('spreed', 'OK: Running version: {version}', { version: providerStatus.value.version })
				: t('spreed', 'OK: The external app "{appId}" provides live transcription', { appId: provider })
		default:
			return t('spreed', 'Error: Unknown error occurred')
	}
})

onMounted(() => {
	loadProviders()
	checkProviderStatus()
})

/**
 * Request the external apps that provide live transcription
 */
async function loadProviders() {
	providersLoading.value = true
	try {
		const response = await getLiveTranscriptionProviders()
		providers.value = response.data.ocs.data
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not load the live transcription providers'))
	} finally {
		providersLoading.value = false
	}
}

/**
 * Request the status of the saved live transcription provider
 */
async function checkProviderStatus() {
	checked.value = false
	requestFailed.value = false
	try {
		const response = await getLiveTranscriptionProviderStatus()
		providerStatus.value = response.data.ocs.data
	} catch (error) {
		console.error(error)
		requestFailed.value = true
	} finally {
		checked.value = true
	}
}

/**
 * Save the selected external app as live transcription provider, and update
 * the providers and the status
 *
 * @param option - selected option
 */
async function selectProvider(option: ProviderOption | null) {
	if (!option || option.id === savedAppId.value) {
		return
	}

	loading.value = true
	try {
		await setAppConfig('spreed', 'live_transcription_app_id', option.id)
		savedAppId.value = option.id
		showSuccess(t('spreed', 'Live transcription provider saved'))
	} catch (error) {
		console.error(error)
		showError(t('spreed', 'Could not save the setting'))
		return
	} finally {
		loading.value = false
	}

	// The previous app is no longer listed if it was no provider
	await Promise.all([loadProviders(), checkProviderStatus()])
}
</script>

<template>
	<section id="live_transcription_provider" class="live-transcription-provider section">
		<h2>
			{{ t('spreed', 'Live transcription provider') }}
		</h2>

		<NcNoteCard
			v-if="!hasSignalingServers"
			type="warning"
			:text="t('spreed', 'Live transcription is only possible with a High-performance backend.')" />

		<template v-else>
			<p class="settings-hint">
				{{ t('spreed', 'Live transcription in calls is provided by an external app installed through AppAPI. Only enabled external apps that announce live transcription in their capabilities can be selected.') }}
			</p>

			<NcSelect
				:modelValue="selectedOption"
				class="live-transcription-provider__select"
				inputId="live_transcription_app_id_input"
				name="live_transcription_app_id"
				:inputLabel="t('spreed', 'External app')"
				:placeholder="t('spreed', 'External app')"
				:options
				:clearable="false"
				:disabled="loading"
				:loading="loading || providersLoading"
				label="label"
				trackBy="id"
				@update:modelValue="selectProvider" />

			<div class="live-transcription-provider__status">
				<span class="test-connection">
					<NcLoadingIcon v-if="!checked" :size="20" />
					<IconAlertCircleOutline v-else-if="isError" :size="20" fillColor="var(--color-border-error)" />
					<IconCheck v-else :size="20" fillColor="var(--color-border-success)" />
					{{ connectionState }}
				</span>

				<NcButton
					v-if="checked"
					class="live-transcription-provider__reload"
					variant="tertiary"
					:title="t('spreed', 'Test this provider')"
					:aria-label="t('spreed', 'Test this provider')"
					@click="checkProviderStatus">
					<template #icon>
						<IconReload :size="20" />
					</template>
				</NcButton>
			</div>
		</template>
	</section>
</template>

<style lang="scss" scoped>
// Nested selectors, so the rules win over the component library defaults
.live-transcription-provider {
	.live-transcription-provider__select {
		width: 100%;
		max-width: 600px;
		margin-bottom: 0;
	}

	.live-transcription-provider__status {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		max-width: 600px;
		margin-top: calc(2 * var(--default-grid-baseline));
	}

	.live-transcription-provider__reload {
		flex-shrink: 0;
	}

	.test-connection {
		display: inline-flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		min-height: var(--default-clickable-area);
	}
}
</style>

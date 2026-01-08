<!--
- SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
- SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import {
	showError,
} from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconSubtitles from 'vue-material-design-icons/Subtitles.vue'
import IconSubtitlesOutline from 'vue-material-design-icons/SubtitlesOutline.vue'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { getTalkConfig } from '../../../services/CapabilitiesManager.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import { useLiveTranscriptionStore } from '../../../stores/liveTranscription.ts'

const token = useGetToken()
const callViewStore = useCallViewStore()
const liveTranscriptionStore = useLiveTranscriptionStore()

const isLiveTranscriptionLoading = ref(false)

const liveTranscriptionButtonLabel = computed(() => {
	if (!callViewStore.isLiveTranscriptionEnabled) {
		return t('spreed', 'Enable live transcription')
	}

	return t('spreed', 'Disable live transcription')
})

/**
 * Toggle live transcriptions.
 */
async function toggleLiveTranscription() {
	if (isLiveTranscriptionLoading.value) {
		return
	}

	isLiveTranscriptionLoading.value = true

	if (!callViewStore.isLiveTranscriptionEnabled) {
		await enableLiveTranscription()
	} else {
		await disableLiveTranscription()
	}

	isLiveTranscriptionLoading.value = false
}

/**
 * Enable live transcriptions.
 */
async function enableLiveTranscription() {
	// Strictly speaking it would be the responsibility of the components using
	// the language metadata to ensure that it is loaded before using it, but
	// for simplicity it is done here and enabling the live transcription is
	// tied to having said metadata.
	try {
		await liveTranscriptionStore.loadLiveTranscriptionLanguages()
	} catch (exception) {
		showError(t('spreed', 'Error when trying to load the available live transcription languages'))

		return
	}

	try {
		await callViewStore.enableLiveTranscription(token.value)
	} catch (error) {
		showError(t('spreed', 'Failed to enable live transcription'))
	}
}

/**
 * Disable live transcriptions.
 */
async function disableLiveTranscription() {
	try {
		await callViewStore.disableLiveTranscription(token.value)
	} catch (error) {
		// Not being able to disable the live transcription is not really
		// relevant for the user, as the transcript will be no longer visible in
		// the UI anyway, so no error is shown in that case.
	}
}
</script>

<template>
	<NcButton
		:title="liveTranscriptionButtonLabel"
		:aria-label="liveTranscriptionButtonLabel"
		:variant="callViewStore.isLiveTranscriptionEnabled ? 'secondary' : 'tertiary'"
		:disabled="isLiveTranscriptionLoading"
		@click="toggleLiveTranscription">
		<template #icon>
			<NcLoadingIcon
				v-if="isLiveTranscriptionLoading"
				:size="20" />
			<IconSubtitles
				v-else-if="callViewStore.isLiveTranscriptionEnabled"
				:size="20" />
			<IconSubtitlesOutline
				v-else
				:size="20" />
		</template>
	</NcButton>
</template>

<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import { useDevices } from '../../composables/useDevices.js'
import { useSettingsStore } from '../../stores/settings.ts'

const props = defineProps<{
	container?: string
}>()

const emit = defineEmits<{
	close: [value?: unknown]
}>()

// TRANSLATORS Microphone setting to reduce background noises for better voice quality
const noiseSuppressionLabel = t('spreed', 'Enable noise suppression')
const noiseSuppressionDescription = t('spreed', 'Reduce background noises for better voice quality')

// TRANSLATORS Microphone setting to minimize echo effect from own surrounding
const echoCancellationLabel = t('spreed', 'Enable echo cancellation')
const echoCancellationDescription = t('spreed', 'Minimize echo effect from own surrounding')

// TRANSLATORS Microphone setting to dynamically adjust microphone volume for consistent level
const autoGainControlLabel = t('spreed', 'Enable auto gain control')
const autoGainControlDescription = t('spreed', 'Dynamically adjust microphone volume for consistent level')

const settingsStore = useSettingsStore()

const { audioPreviewAvailable, updateAudioStream } = useDevices()

const originalState = {
	noiseSuppression: settingsStore.noiseSuppression,
	echoCancellation: settingsStore.echoCancellation,
	autoGainControl: settingsStore.autoGainControl,
} as const

/**
 * Emit result, if any (for spawnDialog callback)
 *
 * @param result callback result
 */
function onClosing(result?: unknown) {
	if (!result) {
		// Revert changes
		settingsStore.setNoiseSuppression(originalState.noiseSuppression)
		settingsStore.setEchoCancellation(originalState.echoCancellation)
		settingsStore.setAutoGainControl(originalState.autoGainControl)
	} else if (audioPreviewAvailable.value && (
		originalState.noiseSuppression !== settingsStore.noiseSuppression
		|| originalState.echoCancellation !== settingsStore.echoCancellation
		|| originalState.autoGainControl !== settingsStore.autoGainControl
	)) {
		// Apply changes to audio stream
		updateAudioStream(true)
	}

	emit('close', result)
}
</script>

<template>
	<NcDialog
		:name="t('spreed', 'Microphone settings')"
		:container="container"
		size="normal"
		:buttons="[
			{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => undefined },
			{ label: t('spreed', 'Save'), variant: 'primary', callback: () => true },
		]"
		closeOnClickOutside
		@closing="onClosing">
		<NcFormBox>
			<NcFormBoxSwitch
				:modelValue="settingsStore.noiseSuppression"
				:label="noiseSuppressionLabel"
				:description="noiseSuppressionDescription"
				@update:modelValue="settingsStore.setNoiseSuppression" />
			<NcFormBoxSwitch
				:modelValue="settingsStore.echoCancellation"
				:label="echoCancellationLabel"
				:description="echoCancellationDescription"
				@update:modelValue="settingsStore.setEchoCancellation" />
			<NcFormBoxSwitch
				:modelValue="settingsStore.autoGainControl"
				:label="autoGainControlLabel"
				:description="autoGainControlDescription"
				@update:modelValue="settingsStore.setAutoGainControl" />
		</NcFormBox>
	</NcDialog>
</template>

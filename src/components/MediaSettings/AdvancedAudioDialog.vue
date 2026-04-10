<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'
import NcRadioGroup from '@nextcloud/vue/components/NcRadioGroup'
import NcRadioGroupButton from '@nextcloud/vue/components/NcRadioGroupButton'
import { useDevices } from '../../composables/useDevices.js'
import { useSettingsStore } from '../../stores/settings.ts'
import { localMediaModel } from '../../utils/webrtc/index.js'

const props = defineProps<{
	container?: string
}>()

const emit = defineEmits<{
	close: [value?: unknown]
}>()

// TRANSLATORS Microphone setting to reduce background noises for better voice quality
const noiseSuppressionLabel = t('spreed', 'Enable noise suppression')
const noiseSuppressionDescription = t('spreed', 'Reduce background noises for better voice quality')
// TRANSLATORS Noise suppression disabled
const noiseSuppressionLevelLabelNo = t('spreed', 'Off')
// TRANSLATORS Low/Medium/High noise suppression level
const noiseSuppressionLevelLabelLow = t('spreed', 'Low')
// TRANSLATORS Low/Medium/High noise suppression level
const noiseSuppressionLevelLabelMedium = t('spreed', 'Medium')
// TRANSLATORS Low/Medium/High noise suppression level
const noiseSuppressionLevelLabelHigh = t('spreed', 'High')

// TRANSLATORS Microphone setting to minimize echo effect from own surrounding
const echoCancellationLabel = t('spreed', 'Enable echo cancellation')
const echoCancellationDescription = t('spreed', 'Minimize echo effect from own surrounding')

// TRANSLATORS Microphone setting to dynamically adjust microphone volume for consistent level
const autoGainControlLabel = t('spreed', 'Enable auto gain control')
const autoGainControlDescription = t('spreed', 'Dynamically adjust microphone volume for consistent level')

const settingsStore = useSettingsStore()

const { audioPreviewAvailable, updateAudioStream } = useDevices()

const noiseSuppressionLevel = computed(() => {
	if (!settingsStore.noiseSuppression && settingsStore.noiseSuppressionWithModel === 'none') {
		return 'disabled'
	}
	if (settingsStore.noiseSuppression && settingsStore.noiseSuppressionWithModel === 'none') {
		return 'builtin'
	}
	return settingsStore.noiseSuppressionWithModel
})

/**
 *
 * @param value
 */
function setNoiseSuppressionLevel(value: string) {
	switch (value) {
		case 'disabled': {
			settingsStore.setNoiseSuppression(false)
			settingsStore.setNoiseSuppressionWithModel('none')
			break
		}
		case 'builtin': {
			settingsStore.setNoiseSuppression(true)
			settingsStore.setNoiseSuppressionWithModel('none')
			break
		}
		case 'rnnoise': {
			settingsStore.setNoiseSuppression(false)
			settingsStore.setNoiseSuppressionWithModel('rnnoise')
			break
		}
		default: {
			// TODO for another model implementation
			settingsStore.setNoiseSuppression(false)
			settingsStore.setNoiseSuppressionWithModel('none')
			break
		}
	}
}

const originalState = {
	noiseSuppression: settingsStore.noiseSuppression,
	noiseSuppressionWithModel: settingsStore.noiseSuppressionWithModel,
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
		settingsStore.setNoiseSuppressionWithModel(originalState.noiseSuppressionWithModel)
		settingsStore.setEchoCancellation(originalState.echoCancellation)
		settingsStore.setAutoGainControl(originalState.autoGainControl)
	} else if (audioPreviewAvailable.value && (
		originalState.noiseSuppression !== settingsStore.noiseSuppression
		|| originalState.noiseSuppressionWithModel !== settingsStore.noiseSuppressionWithModel
		|| originalState.echoCancellation !== settingsStore.echoCancellation
		|| originalState.autoGainControl !== settingsStore.autoGainControl
	)) {
		// Apply changes to audio stream
		updateAudioStream(true)

		if (localMediaModel.getWebRtc()) {
			if (settingsStore.noiseSuppressionWithModel) {
				localMediaModel.enableNoiseSuppression()
			} else {
				localMediaModel.disableNoiseSuppression()
			}
		}
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
		<NcRadioGroup
			class="audio-dialog__radiogroup"
			:label="noiseSuppressionLabel"
			:description="noiseSuppressionDescription"
			:modelValue="noiseSuppressionLevel"
			@update:modelValue="setNoiseSuppressionLevel">
			<NcRadioGroupButton :label="noiseSuppressionLevelLabelNo" value="disabled" />
			<NcRadioGroupButton :label="noiseSuppressionLevelLabelLow" value="builtin" />
			<NcRadioGroupButton :label="noiseSuppressionLevelLabelMedium" value="rnnoise" />
			<!-- <NcRadioGroupButton :label="noiseSuppressionLevelLabelHigh" value="todo" /> -->
		</NcRadioGroup>

		<NcFormBox>
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

<style scoped lang="scss">
.audio-dialog {
	&__radiogroup {
		margin-block-end: var(--default-grid-baseline);
	}
}
</style>

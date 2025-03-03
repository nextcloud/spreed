<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="mediaDevicesPreview">
		<MediaDevicesSelector kind="audioinput"
			:devices="devices"
			:device-id="audioInputId"
			@refresh="updateDevices"
			@update:deviceId="handleAudioInputIdChange" />
		<div class="preview preview-audio">
			<div v-if="!audioPreviewAvailable"
				class="preview-not-available">
				<AlertCircle v-if="audioStreamError"
					:size="64"
					title=""
					fill-color="#999" />
				<MicrophoneOff v-else-if="!audioInputId"
					:size="64"
					title=""
					fill-color="#999" />
				<div v-else-if="!audioStream"
					class="icon icon-loading" />
				<p v-if="audioStreamErrorMessage">
					{{ audioStreamErrorMessage }}
				</p>
			</div>
			<VolumeIndicator v-else
				:audio-preview-available="audioPreviewAvailable"
				:audio-enabled="true"
				:current-volume="currentVolume"
				:volume-threshold="currentThreshold"
				:size="64" />
		</div>
		<MediaDevicesSelector kind="videoinput"
			:devices="devices"
			:device-id="videoInputId"
			@refresh="updateDevices"
			@update:deviceId="handleVideoInputIdChange" />
		<div class="preview preview-video">
			<div v-if="!videoPreviewAvailable"
				class="preview-not-available">
				<AlertCircle v-if="videoStreamError"
					:size="64"
					title=""
					fill-color="#999" />
				<VideoOff v-else-if="!videoInputId"
					:size="64"
					title=""
					fill-color="#999" />
				<div v-else-if="!videoStream"
					class="icon icon-loading" />
				<p v-if="videoStreamErrorMessage">
					{{ videoStreamErrorMessage }}
				</p>
			</div>
			<!-- v-show has to be used instead of v-if/else to ensure that the
				 reference is always valid once mounted. -->
			<video v-show="videoPreviewAvailable"
				ref="video"
				disablePictureInPicture="true"
				tabindex="-1" />
		</div>
		<NcCheckboxRadioSwitch v-if="supportDefaultBlurVirtualBackground"
			type="switch"
			:model-value="blurVirtualBackgroundEnabled"
			@update:model-value="setBlurVirtualBackgroundEnabled">
			{{ t('spreed', 'Enable blur background by default for all conversation') }}
		</NcCheckboxRadioSwitch>
		<MediaDevicesSelector v-if="audioOutputSupported"
			kind="audiooutput"
			:devices="devices"
			:device-id="audioOutputId"
			@refresh="updateDevices"
			@update:deviceId="handleAudioOutputIdChange" />
		<MediaDevicesSpeakerTest />
	</div>
</template>

<script>
import { ref } from 'vue'

import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { t } from '@nextcloud/l10n'

import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'

import MediaDevicesSelector from '../MediaSettings/MediaDevicesSelector.vue'
import MediaDevicesSpeakerTest from '../MediaSettings/MediaDevicesSpeakerTest.vue'
import VolumeIndicator from '../UIShared/VolumeIndicator.vue'

import { useDevices } from '../../composables/useDevices.js'
import { VIRTUAL_BACKGROUND } from '../../constants.ts'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useSettingsStore } from '../../stores/settings.js'

const supportDefaultBlurVirtualBackground = getTalkConfig('local', 'call', 'blur-virtual-background') !== undefined

export default {

	name: 'MediaDevicesPreview',

	components: {
		MediaDevicesSpeakerTest,
		AlertCircle,
		MediaDevicesSelector,
		MicrophoneOff,
		NcCheckboxRadioSwitch,
		VideoOff,
		VolumeIndicator,
	},

	setup() {
		const video = ref(null)
		const {
			devices,
			updateDevices,
			updatePreferences,
			currentVolume,
			currentThreshold,
			audioPreviewAvailable,
			videoPreviewAvailable,
			audioInputId,
			audioOutputId,
			videoInputId,
			audioOutputSupported,
			audioStream,
			audioStreamError,
			videoStream,
			videoStreamError,
			virtualBackground,
		} = useDevices(video, true)

		return {
			video,
			devices,
			updateDevices,
			updatePreferences,
			currentVolume,
			currentThreshold,
			audioPreviewAvailable,
			videoPreviewAvailable,
			audioInputId,
			audioOutputId,
			videoInputId,
			audioOutputSupported,
			audioStream,
			audioStreamError,
			videoStream,
			videoStreamError,
			settingsStore: useSettingsStore(),
			virtualBackground,
			supportDefaultBlurVirtualBackground,
		}
	},

	computed: {
		audioStreamErrorMessage() {
			if (!this.audioStreamError) {
				return null
			}

			if (this.audioStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.audioStreamError.message && this.audioStreamError.message.includes('Only secure origins')
			if ((this.audioStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.audioStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to microphone is only possible with HTTPS')
			}

			if (this.audioStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to microphone was denied')
			}

			return t('spreed', 'Error while accessing microphone')
		},

		videoStreamErrorMessage() {
			if (!this.videoStreamError) {
				return null
			}

			if (this.videoStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.videoStreamError.message && this.videoStreamError.message.includes('Only secure origins')
			if ((this.videoStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.videoStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to camera is only possible with HTTPS')
			}

			if (this.videoStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to camera was denied')
			}

			return t('spreed', 'Error while accessing camera')
		},

		blurVirtualBackgroundEnabled() {
			return this.settingsStore.blurVirtualBackgroundEnabled
		},
	},

	mounted() {
		if (this.blurVirtualBackgroundEnabled) {
			// wait for the virtual background to be ready
			this.$nextTick(() => {
				this.virtualBackground.setEnabled(true)
				this.virtualBackground.setVirtualBackground({
					backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
					blurValue: VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT,
				})
			})
		}
	},

	methods: {
		t,

		handleAudioInputIdChange(audioInputId) {
			this.audioInputId = audioInputId
			this.updatePreferences('audioinput')
		},

		handleVideoInputIdChange(videoInputId) {
			this.videoInputId = videoInputId
			this.updatePreferences('videoinput')
		},

		handleAudioOutputIdChange(audioOutputId) {
			this.audioOutputId = audioOutputId
			this.updatePreferences('audiooutput')
		},

		async setBlurVirtualBackgroundEnabled(value) {
			try {
				await this.settingsStore.setBlurVirtualBackgroundEnabled(value)
				if (value) {
					this.virtualBackground.setEnabled(true)
					this.virtualBackground.setVirtualBackground({
						backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
						blurValue: VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT,
					})
				} else {
					this.virtualBackground.setEnabled(false)
				}
			} catch (error) {
				console.error('Failed to set blur background enabled:', error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.preview {
	display: flex;

	.icon {
		background-size: 64px;
		width: 64px;
		height: 64px;
		opacity: 0.4;
		margin-inline: auto;
	}
}

.preview-not-available p {
	margin-bottom: 16px;
}

.preview-audio {
	.preview-not-available .icon {
		margin-top: 16px;
		margin-bottom: 16px;
	}

	.volume-indicator-wrapper {
		margin: 16px auto;
	}
}

.preview-video {
	margin-top: 24px;
	.preview-not-available .icon {
		margin-top: 16px;
		margin-bottom: 16px;
	}

	video {
		display: block;
		width: 100%;
	}
}
</style>

<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
	<div class="mediaDevicesPreview">
		<MediaDevicesSelector kind="audioinput"
			:devices="devices"
			:device-id="audioInputId"
			:enabled="enabled"
			@update:deviceId="audioInputId = $event" />
		<div class="preview preview-audio">
			<div v-if="!audioPreviewAvailable"
				class="preview-not-available">
				<div v-if="audioStreamError"
					class="icon icon-error" />
				<div v-else-if="!audioInputId"
					class="icon icon-audio-off" />
				<div v-else-if="!enabled"
					class="icon icon-audio" />
				<div v-else-if="!audioStream"
					class="icon icon-loading" />
				<p v-if="audioStreamErrorMessage">
					{{ audioStreamErrorMessage }}
				</p>
			</div>
			<!-- v-show has to be used instead of v-if/else to ensure that the
				 reference is always valid once mounted. -->
			<div v-show="audioPreviewAvailable"
				class="volume-indicator-wrapper">
				<div class="icon icon-audio" />
				<span ref="volumeIndicator"
					class="volume-indicator"
					:style="{ 'height': currentVolumeIndicatorHeight + 'px' }" />
			</div>
		</div>
		<MediaDevicesSelector kind="videoinput"
			:devices="devices"
			:device-id="videoInputId"
			:enabled="enabled"
			@update:deviceId="videoInputId = $event" />
		<div class="preview preview-video">
			<div v-if="!videoPreviewAvailable"
				class="preview-not-available">
				<div v-if="videoStreamError"
					class="icon icon-error" />
				<div v-else-if="!videoInputId"
					class="icon icon-video-off" />
				<div v-else-if="!enabled"
					class="icon icon-video" />
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
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import hark from 'hark'
import { mediaDevicesManager } from '../utils/webrtc/index'
import MediaDevicesSelector from './MediaDevicesSelector'

export default {

	name: 'MediaDevicesPreview',

	components: {
		MediaDevicesSelector,
	},

	props: {
		enabled: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			mounted: false,
			mediaDevicesManager: mediaDevicesManager,
			pendingGetUserMediaAudioCount: 0,
			pendingGetUserMediaVideoCount: 0,
			audioStream: null,
			audioStreamError: null,
			videoStream: null,
			videoStreamError: null,
			hark: null,
			currentVolume: -100,
			volumeThreshold: -100,
		}
	},

	computed: {
		devices() {
			return mediaDevicesManager.attributes.devices
		},

		audioInputId: {
			get() {
				return mediaDevicesManager.attributes.audioInputId
			},
			set(value) {
				mediaDevicesManager.set('audioInputId', value)
			},
		},

		videoInputId: {
			get() {
				return mediaDevicesManager.attributes.videoInputId
			},
			set(value) {
				mediaDevicesManager.set('videoInputId', value)
			},
		},

		audioStreamInputId() {
			if (!this.audioStream) {
				return null
			}

			const audioTracks = this.audioStream.getAudioTracks()
			if (audioTracks.length < 1) {
				return null
			}

			return audioTracks[0].getSettings().deviceId
		},

		videoStreamInputId() {
			if (!this.videoStream) {
				return null
			}

			const videoTracks = this.videoStream.getVideoTracks()
			if (videoTracks.length < 1) {
				return null
			}

			return videoTracks[0].getSettings().deviceId
		},

		audioPreviewAvailable() {
			return this.audioInputId && this.audioStream
		},

		videoPreviewAvailable() {
			return this.videoInputId && this.videoStream
		},

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
			const isInsecureContextAccordingToErrorMessage = this.audioStreamError.message && this.audioStreamError.message.indexOf('Only secure origins') !== -1
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
			const isInsecureContextAccordingToErrorMessage = this.videoStreamError.message && this.videoStreamError.message.indexOf('Only secure origins') !== -1
			if ((this.videoStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.videoStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to camera is only possible with HTTPS')
			}

			if (this.videoStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to camera was denied')
			}

			return t('spreed', 'Error while accessing camera')
		},

		currentVolumeIndicatorHeight() {
			// refs can not be accessed on the initial render, only after the
			// component has been mounted.
			if (!this.mounted) {
				return 0
			}

			// WebRTC volume goes from -100 (silence) to 0 (loudest sound in the
			// system); for the volume indicator only sounds above the threshold
			// are taken into account.
			let currentVolumeProportion = 0
			if (this.currentVolume > this.volumeThreshold) {
				currentVolumeProportion = (this.volumeThreshold - this.currentVolume) / this.volumeThreshold
			}

			const volumeIndicatorStyle = window.getComputedStyle ? getComputedStyle(this.$refs.volumeIndicator, null) : this.$refs.volumeIndicator.currentStyle

			const maximumVolumeIndicatorHeight = this.$refs.volumeIndicator.parentElement.clientHeight - (parseInt(volumeIndicatorStyle.bottom, 10) * 2)

			return maximumVolumeIndicatorHeight * currentVolumeProportion
		},

	},

	watch: {
		enabled: {
			handler: function(enabled) {
				if (this.enabled) {
					this.mediaDevicesManager.enableDeviceEvents()
					this.updateAudioStream()
					this.updateVideoStream()
				} else {
					this.mediaDevicesManager.disableDeviceEvents()
					this.stopAudioStream()
					this.stopVideoStream()
				}
			},
			immediate: true,
		},

		audioInputId(audioInputId) {
			if (!this.enabled) {
				return
			}

			this.updateAudioStream()
		},

		videoInputId(videoInputId) {
			if (!this.enabled) {
				return
			}

			this.updateVideoStream()
		},
	},

	mounted() {
		this.mounted = true

		if (!this.mediaDevicesManager.isSupported()) {
			// DOMException constructor is not supported in Internet Explorer,
			// so a plain object is used instead.
			this.audioStreamError = {
				message: 'MediaDevicesManager is not supported',
				name: 'NotSupportedError',
			}
			this.videoStreamError = {
				message: 'MediaDevicesManager is not supported',
				name: 'NotSupportedError',
			}
		}
	},

	destroyed() {
		this.stopAudioStream()
		this.stopVideoStream()

		if (this.enabled) {
			this.mediaDevicesManager.disableDeviceEvents()
		}
	},

	methods: {

		updateAudioStream() {
			if (!this.mediaDevicesManager.isSupported()) {
				return
			}

			if (this.audioStreamInputId && this.audioStreamInputId === this.audioInputId) {
				return
			}

			if (this.pendingGetUserMediaAudioCount) {
				this.pendingGetUserMediaAudioCount++

				return
			}

			// When the audio input device changes the previous stream must be
			// stopped before a new one is requested, as for example currently
			// Firefox does not support having two different audio input devices
			// active at the same time:
			// https://bugzilla.mozilla.org/show_bug.cgi?id=1468700
			this.stopAudioStream()

			this.audioStreamError = null

			if (this.audioInputId === null || this.audioInputId === undefined) {
				return
			}

			this.pendingGetUserMediaAudioCount = 1

			const resetPendingGetUserMediaAudioCount = () => {
				const updateAudioStreamAgain = this.pendingGetUserMediaAudioCount > 1

				this.pendingGetUserMediaAudioCount = 0

				if (updateAudioStreamAgain) {
					this.updateAudioStream()
				}
			}

			this.mediaDevicesManager.getUserMedia({ audio: true })
				.then(stream => {
					this.setAudioStream(stream)

					resetPendingGetUserMediaAudioCount()
				})
				.catch(error => {
					console.error('Error getting audio stream: ' + error.name + ': ' + error.message)
					this.audioStreamError = error
					this.setAudioStream(null)

					resetPendingGetUserMediaAudioCount()
				})
		},

		updateVideoStream() {
			if (!this.mediaDevicesManager.isSupported()) {
				return
			}

			if (this.videoStreamInputId && this.videoStreamInputId === this.videoInputId) {
				return
			}

			if (this.pendingGetUserMediaVideoCount) {
				this.pendingGetUserMediaVideoCount++

				return
			}

			// Video stream is stopped too to avoid potential issues similar to
			// the audio ones (see "updateAudioStream").
			this.stopVideoStream()

			this.videoStreamError = null

			if (this.videoInputId === null || this.videoInputId === undefined) {
				return
			}

			this.pendingGetUserMediaVideoCount = 1

			const resetPendingGetUserMediaVideoCount = () => {
				const updateVideoStreamAgain = this.pendingGetUserMediaVideoCount > 1

				this.pendingGetUserMediaVideoCount = 0

				if (updateVideoStreamAgain) {
					this.updateVideoStream()
				}
			}

			this.mediaDevicesManager.getUserMedia({ video: true })
				.then(stream => {
					this.setVideoStream(stream)

					resetPendingGetUserMediaVideoCount()
				})
				.catch(error => {
					console.error('Error getting video stream: ' + error.name + ': ' + error.message)
					this.videoStreamError = error
					this.setVideoStream(null)

					resetPendingGetUserMediaVideoCount()
				})
		},

		setAudioStream(audioStream) {
			this.audioStream = audioStream

			if (!audioStream) {
				return
			}

			this.hark = hark(this.audioStream)
			this.hark.on('volume_change', (volume, volumeThreshold) => {
				this.currentVolume = volume
				this.volumeThreshold = volumeThreshold
			})
		},

		setVideoStream(videoStream) {
			this.videoStream = videoStream

			if (!this.$refs.video) {
				return
			}

			if (!videoStream) {
				return
			}

			const options = {
				autoplay: true,
				mirror: true,
				muted: true,
			}
			attachMediaStream(videoStream, this.$refs.video, options)
		},

		stopAudioStream() {
			if (!this.audioStream) {
				return
			}

			this.audioStream.getTracks().forEach(function(track) {
				track.stop()
			})

			this.audioStream = null

			if (this.hark) {
				this.hark.stop()
				this.hark.off('volume_change')
				this.hark = null
			}
		},

		stopVideoStream() {
			if (!this.videoStream) {
				return
			}

			this.videoStream.getTracks().forEach(function(track) {
				track.stop()
			})

			this.videoStream = null

			if (this.$refs.video) {
				this.$refs.video.srcObject = null
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

		margin-left: auto;
		margin-right: auto;
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
		/* Make the wrapper the positioning context of the volume indicator. */
		position: relative;

		margin-top: 16px;
		margin-bottom: 16px;
	}

	.volume-indicator {
		position: absolute;

		width: 6px;
		right: 0;

		/* The button height is 64px; the volume indicator button is 56px at
		 * maximum, but its value will be changed based on the current volume;
		 * the height change will reveal more or less of the gradient, which has
		 * absolute dimensions and thus does not change when the height
		 * changes. */
		height: 56px;
		bottom: 4px;

		background: linear-gradient(0deg, green, yellow, red 54px);

		opacity: 0.7;
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

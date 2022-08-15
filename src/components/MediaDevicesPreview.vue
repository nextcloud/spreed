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
			@update:deviceId="audioInputId = $event" />
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
			<!-- v-show has to be used instead of v-if/else to ensure that the
				 reference is always valid once mounted. -->
			<div v-show="audioPreviewAvailable"
				class="volume-indicator-wrapper">
				<Microphone :size="64"
					title=""
					fill-color="#999" />
				<span ref="volumeIndicator"
					class="volume-indicator"
					:style="{ 'height': currentVolumeIndicatorHeight + 'px' }" />
			</div>
		</div>
		<MediaDevicesSelector kind="videoinput"
			:devices="devices"
			:device-id="videoInputId"
			@update:deviceId="videoInputId = $event" />
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
	</div>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'
import MediaDevicesSelector from './MediaDevicesSelector.vue'
import { devices } from '../mixins/devices.js'

export default {

	name: 'MediaDevicesPreview',

	components: {
		AlertCircle,
		MediaDevicesSelector,
		Microphone,
		MicrophoneOff,
		VideoOff,
	},

	mixins: [devices],

	data() {
		return {
			mounted: false,
			currentVolume: -100,
			volumeThreshold: -100,
		}
	},

	computed: {

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

	mounted() {
		this.mounted = true
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

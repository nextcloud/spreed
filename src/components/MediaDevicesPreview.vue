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
			<VolumeIndicator v-else
				:audio-preview-available="audioPreviewAvailable"
				:audio-enabled="true"
				:current-volume="currentVolume"
				:volume-threshold="volumeThreshold"
				:size="64" />
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
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import MediaDevicesSelector from './MediaDevicesSelector.vue'
import VolumeIndicator from './VolumeIndicator/VolumeIndicator.vue'

import { devices } from '../mixins/devices.js'

export default {

	name: 'MediaDevicesPreview',

	components: {
		AlertCircle,
		MediaDevicesSelector,
		MicrophoneOff,
		VideoOff,
		VolumeIndicator,
	},

	mixins: [devices],

	data() {
		return {
			mounted: false,
			currentVolume: -100,
			volumeThreshold: -100,
		}
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

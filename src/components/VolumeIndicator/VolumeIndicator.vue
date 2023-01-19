<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<span class="volume-indicator"
		:style="{ height: size + 'px', width: size + 'px' }">
		<span class="volume-indicator-primary"
			:style="{
				height: size - 1 - currentVolumeIndicatorHeight + 'px',
			}">
			<Microphone v-if="audioEnabled" :size="size" :fill-color="primaryColor" />
			<MicrophoneOff v-else :size="size" :fill-color="primaryColor" />
		</span>

		<span v-show="audioPreviewAvailable"
			class="volume-indicator-overlay"
			:class="{ 'volume-indicator-overlay-mute': !audioEnabled }"
			:style="{
				height: currentVolumeIndicatorHeight + 'px',
			}">
			<Microphone v-if="audioEnabled"
				:size="size"
				:fill-color="overlayMutedColor" />
			<MicrophoneOff v-else :size="size" :fill-color="overlayMutedColor" />
		</span>
	</span>
</template>

<script>
import Microphone from 'vue-material-design-icons/Microphone.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'

export default {
	name: 'VolumeIndicator',

	components: {
		Microphone,
		MicrophoneOff,
	},

	props: {
		audioPreviewAvailable: {
			type: Boolean,
			required: true,
		},

		audioEnabled: {
			type: Boolean,
			required: true,
		},

		currentVolume: {
			type: Number,
			required: true,
		},

		volumeThreshold: {
			type: Number,
			required: true,
		},

		size: {
			type: Number,
			default: 20,
		},

		primaryColor: {
			type: String,
			default: undefined,
		},

		overlayColor: {
			type: String,
			default: undefined,
		},
	},

	computed: {
		currentVolumeIndicatorHeight() {
			// WebRTC volume goes from -100 (silence) to 0 (loudest sound in the
			// system); for the volume indicator only sounds above the threshold
			// are taken into account.
			if (this.currentVolume < this.volumeThreshold) {
				return 0
			}

			return this.size * (1 - this.currentVolume / this.volumeThreshold)
		},

		overlayMutedColor() {
			return !this.audioEnabled ? this.overlayColor : undefined
		},
	},
}
</script>

<style lang="scss" scoped>
.volume-indicator {
	position: relative;
}

.volume-indicator-primary {
	position: absolute;
	top: 0;
	left: 50%;
	transform: translateX(-50%);

	overflow: hidden;
}

.volume-indicator-overlay {
	/* The button height is 44px; the volume indicator covers primary icon and has
	* the same size, but its height value will be changed based on the current volume */
	width: 100%;
	height: 100%;

	/* Position of container with overlay icon is centered to cover primary icon */
	position: absolute;
	bottom: 0;
	left: 50%;
	transform: translateX(-50%);
	pointer-events: none;

	overflow: hidden;

	& > span {
		position: absolute;
		bottom: 0;
	}

	/* Overlay icon inherits container color */
	color: var(--color-success);

	&-mute {
		color: var(--color-loading-dark);
	}
}
</style>

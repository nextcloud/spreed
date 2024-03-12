<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<span class="volume-indicator-wrapper"
		:style="{ height: size + 'px', width: size + 'px' }"
		:class="{ overload: hasOverload }">
		<span class="volume-indicator volume-indicator-primary" :style="{ height: iconPrimaryHeight + 'px' }">
			<Microphone v-if="audioEnabled" :size="size" :fill-color="primaryColor" />
			<MicrophoneOff v-else :size="size" :fill-color="primaryColor" />
		</span>

		<span v-if="audioPreviewAvailable"
			class="volume-indicator volume-indicator-overlay"
			:class="{ 'volume-indicator-overlay-mute': !audioEnabled }"
			:style="{ height: iconOverlayHeight + 'px' }">
			<Microphone v-if="audioEnabled" :size="size" :fill-color="overlayColor" />
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

		overloadLimit: {
			type: Number,
			default: -25,
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

		overlayMutedColor: {
			type: String,
			default: undefined,
		},
	},

	computed: {
		iconOffsetBottom() {
			return this.size / 8
		},

		iconPrimaryHeight() {
			return this.audioPreviewAvailable
				? this.size - this.iconOffsetBottom - this.currentVolumeIndicatorHeight
				: this.size
		},

		iconOverlayHeight() {
			return this.iconOffsetBottom / 2 + this.currentVolumeIndicatorHeight
		},

		hasOverload() {
			return this.audioPreviewAvailable && this.currentVolumeIndicatorHeight === this.size - this.iconOffsetBottom
		},

		currentVolumeIndicatorHeight() {
			if (!this.audioPreviewAvailable) {
				return 0
			}

			// WebRTC volume goes from -100 (silence) to 0 (loudest sound in the
			// system); for the volume indicator only sounds above the threshold
			// are taken into account.
			if (this.currentVolume < this.volumeThreshold) {
				return 0
			}

			return (this.size - this.iconOffsetBottom) * this.computeVolumeLevel()
		},
	},

	methods: {
		computeVolumeLevel() {
			const computedLevel = (this.volumeThreshold - this.currentVolume) / (this.volumeThreshold - this.overloadLimit)

			return Math.min(1, Math.max(0, computedLevel))
		},
	},
}
</script>

<style lang="scss" scoped>
.volume-indicator-wrapper {
	position: relative;
}

.volume-indicator {
	position: absolute;
	left: 0;

	width: 100%;
	height: 100%;

	overflow: hidden;

	transition: height 0.2s linear;

	&, & * {
		cursor: inherit;
	}
}

.volume-indicator-primary {
	top: 0;
}

.volume-indicator-overlay {
	bottom: 0;
	pointer-events: none;

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

.overload .volume-indicator {
	transition: height 0s linear;

	&-overlay {
		color: var(--color-error);
	}
}
</style>

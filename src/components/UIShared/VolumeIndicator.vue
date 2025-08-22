<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span class="volume-indicator-wrapper"
		:style="{ height: size + 'px', width: size + 'px' }"
		:class="{ overload: hasOverload }">
		<span class="volume-indicator volume-indicator-primary"
			:style="{ height: iconPrimaryHeight + 'px' }">
			<IconMicrophoneOutline v-if="audioEnabled" :size="size" :fill-color="primaryColor" />
			<NcIconSvgWrapper v-else
				inline
				:svg="IconMicrophoneOffOutline"
				:size="size"
				:style="{ color: primaryColor }" />
		</span>

		<span v-if="audioPreviewAvailable"
			class="volume-indicator volume-indicator-overlay"
			:class="{ 'volume-indicator-overlay-mute': !audioEnabled }"
			:style="{ height: iconOverlayHeight + 'px' }">
			<IconMicrophoneOutline v-if="audioEnabled" :size="size" :fill-color="overlayColor" />
			<NcIconSvgWrapper v-else
				inline
				:svg="IconMicrophoneOffOutline"
				:size="size"
				:style="{ color: overlayMutedColor }" />
		</span>
	</span>
</template>

<script>
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconMicrophoneOffOutline from '../../../img/material-icons/microphone-off-outline.svg?raw'

export default {
	name: 'VolumeIndicator',

	components: {
		IconMicrophoneOutline,
		NcIconSvgWrapper,
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

	setup() {
		return {
			IconMicrophoneOffOutline,
		}
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
	inset-inline-start: 0;

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
	display: inline-flex;
	bottom: 0;
	pointer-events: none;

	& > span {
		position: absolute;
		bottom: 0;
	}

	/* Overlay icon inherits container color */
	color: var(--color-border-success);

	&-mute {
		color: var(--color-loading-dark);
	}
}

.overload .volume-indicator {
	transition: height 0s linear;

	&-overlay {
		color: var(--color-border-error);
	}
}
</style>

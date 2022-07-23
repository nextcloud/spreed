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
	<div v-show="audioPreviewAvailable"
		class="volume-indicator-wrapper"
		:style="{ 'height': wrapperHeight + 'px' }">
		<span ref="volumeIndicator"
			class="volume-indicator"
			:class="{'volume-indicator--disabled': disabled}"
			:style="{ 'height': currentVolumeIndicatorHeight + 'px' }" />
	</div>
</template>

<script>

export default {
	name: 'VolumeIndicator',

	props: {
		audioPreviewAvailable: {
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

		wrapperHeight: {
			type: Number,
			default: 44,
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			mounted: false,
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
@import '../../assets/variables';

.volume-indicator-wrapper {
	/* Make the wrapper the positioning context of the volume indicator. */
	position: relative;
	margin-top: 16px;
	margin-bottom: 16px;
}

.volume-indicator {
	position: absolute;
	width: 4px;
	right: 0;

	/* The button height is 44px; the volume indicator button is 44px at
		* maximum, but its value will be changed based on the current volume;
		* the height change will reveal more or less of the gradient, which has
		* absolute dimensions and thus does not change when the height
		* changes. */
	height: $clickable-area;
	bottom: 4px;

	background: linear-gradient(0deg, green, yellow, red 44px);

	opacity: 0.7;

	&--disabled {
		background: var(--color-loading-light);
	}
}

</style>

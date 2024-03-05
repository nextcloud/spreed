<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="media-devices-checker">
		<div class="media-devices-checker__icon">
			<VolumeHighIcon :size="16" />
		</div>
		<NcButton type="secondary" @click="playTestSound">
			{{ buttonLabel }}
		</NcButton>
		<div v-if="isPlayingTestSound" class="equalizer">
			<div v-for="bar in equalizerBars"
				:key="bar.key"
				class="equalizer__bar"
				:style="bar.style" />
		</div>
	</div>
</template>

<script>
import VolumeHighIcon from 'vue-material-design-icons/VolumeHigh.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {

	name: 'MediaDevicesSpeakerTest',

	components: {
		NcButton,
		VolumeHighIcon,
	},

	data() {
		return {
			isPlayingTestSound: false,
		}
	},

	computed: {
		buttonLabel() {
			return this.isPlayingTestSound
				// TRANSLATORS Playing the test sound to check speakers
				? t('spreed', 'Playing …')
				: t('spreed', 'Test speakers')
		},

		equalizerBars() {
			return Array.from(Array(4).keys()).map(item => ({
				key: item,
				style: {
					height: Math.random() * 100 + '%',
					animationDelay: Math.random() * -2 + 's',
				},
			}))
		}
	},

	methods: {
		playTestSound() {
			if (this.isPlayingTestSound) {
				this.$store.dispatch('pauseWaitAudio')
				return
			}
			this.isPlayingTestSound = true
			this.$store.dispatch('playWaitAudio').then((response) => {
				response.addEventListener('ended', () => {
					this.isPlayingTestSound = false
				})
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.media-devices-checker {
	display: flex;
	margin: 16px 0;

	&__icon {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		width: 36px;
	}

	.equalizer {
		margin-left: 8px;
		height: var(--default-clickable-area);
		display: flex;
		align-items: center;

		&__bar {
			width: 8px;
			height: 100%;
			background: var(--color-primary-element);
			border-radius: 4px;
			margin: 0 2px;
			will-change: height;
			animation: equalizer 2s steps(15, end) infinite;
		}
	}
}

@keyframes equalizer {
	@for $i from 0 through 15 {
		#{4*$i}% { height: random(70) + 20%; }
	}
}
</style>

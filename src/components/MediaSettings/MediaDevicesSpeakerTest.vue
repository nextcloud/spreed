<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
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

<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton :disabled="disabled"
		class="media-devices-speaker-test-button"
		:title="buttonLabel"
		:aria-label="buttonLabel"
		variant="secondary"
		@click="playTestSound">
		<template #icon>
			<div class="equalizer">
				<div v-for="bar in equalizerBars"
					:key="bar.key"
					class="equalizer__bar"
					:class="{ 'equalizer__bar--active': isPlayingTestSound }"
					:style="bar.style" />
			</div>
		</template>
		<!-- TRANSLATORS: Button to test speakers by playing a sound -->
		{{ t('spreed', 'Test') }}
	</NcButton>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import { useSoundsStore } from '../../stores/sounds.js'

export default {

	name: 'MediaDevicesSpeakerTest',

	components: {
		NcButton,
	},

	props: {
		disabled: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			soundsStore: useSoundsStore(),
		}
	},

	computed: {
		isPlayingTestSound() {
			return this.soundsStore.audioObjectsPromises.wait !== null
		},

		buttonLabel() {
			return this.isPlayingTestSound
				// TRANSLATORS Playing the test sound to check speakers
				? t('spreed', 'Playing …')
				: t('spreed', 'Test speakers')
		},

		equalizerBars() {
			return Array.from(Array(3).keys()).map((item) => ({
				key: item,
				style: {
					height: this.isPlayingTestSound ? (Math.random() * 100 + '%') : (item % 2 === 0 ? '40%' : '60%'),
					animationDelay: this.isPlayingTestSound ? (Math.random() * -2 + 's') : undefined,
				},
			}))
		},
	},

	beforeUnmount() {
		this.soundsStore.pauseAudio('wait')
	},

	methods: {
		t,

		playTestSound() {
			if (this.isPlayingTestSound) {
				this.soundsStore.pauseAudio('wait')
			} else {
				this.soundsStore.playAudio('wait')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@use 'sass:math';

.media-devices-speaker-test-button {
	flex-shrink: 0;
	margin-inline-end: calc(var(--default-grid-baseline) / 2);
}

.equalizer {
		height: calc(var(--default-clickable-area) - var(--default-grid-baseline)); // - total margin block
		display: flex;
		align-items: center;

		&__bar {
			width: 5px;
			height: 100%;
			background: var(--color-main-text);
			border-radius: 4px;
			margin: 0 1px;

			&--active {
				will-change: height;
				animation: equalizer 2s steps(15, end) infinite;
			}
		}
	}

@keyframes equalizer {
	@for $i from 0 through 15 {
		#{3 * $i}% { height: math.random(40) + 20%; }
	}
}
</style>

<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcButton v-shortkey.once="disableKeyboardShortcuts ? null : ['m']"
		v-tooltip="audioButtonTooltip"
		:type="type"
		:aria-label="audioButtonAriaLabel"
		:class="{ 'no-audio-available': !isAudioAllowed || !model.attributes.audioAvailable }"
		@shortkey="toggleAudio"
		@click.stop="toggleAudio">
		<template #icon>
			<VolumeIndicator :audio-preview-available="model.attributes.audioAvailable"
				:audio-enabled="showMicrophoneOn"
				:current-volume="model.attributes.currentVolume"
				:volume-threshold="model.attributes.volumeThreshold"
				overlay-muted-color="#888888" />
		</template>
	</NcButton>
</template>

<script>
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import VolumeIndicator from '../../UIShared/VolumeIndicator.vue'

import { PARTICIPANT } from '../../../constants.js'
import BrowserStorage from '../../../services/BrowserStorage.js'

export default {
	name: 'LocalAudioControlButton',

	components: {
		NcButton,
		VolumeIndicator,
	},

	directives: {
		Tooltip,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},

		model: {
			type: Object,
			required: true,
		},

		disableKeyboardShortcuts: {
			type: Boolean,
			default: OCP.Accessibility.disableKeyboardShortcuts(),
		},

		type: {
			type: String,
			default: 'tertiary-no-background',
		},

		token: {
			type: String,
			required: true,
		},
	},

	computed: {
		isAudioAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
		},

		showMicrophoneOn() {
			return this.model.attributes.audioAvailable && this.model.attributes.audioEnabled
		},

		audioButtonTooltip() {
			if (!this.isAudioAllowed) {
				return t('spreed', 'You are not allowed to enable audio')
			}

			if (!this.model.attributes.audioAvailable) {
				return {
					content: t('spreed', 'No audio. Click to select device'),
					show: false,
				}
			}

			if (this.speakingWhileMutedNotification && !this.screenSharingMenuOpen) {
				return {
					content: this.speakingWhileMutedNotification,
					show: true,
				}
			}

			let content = ''
			if (this.model.attributes.audioEnabled) {
				content = this.disableKeyboardShortcuts
					? t('spreed', 'Mute audio')
					: t('spreed', 'Mute audio (M)')
			} else {
				content = this.disableKeyboardShortcuts
					? t('spreed', 'Unmute audio')
					: t('spreed', 'Unmute audio (M)')
			}

			return {
				content,
				show: false,
			}
		},

		audioButtonAriaLabel() {
			if (!this.model.attributes.audioAvailable) {
				return t('spreed', 'No audio. Click to select device')
			}

			return this.model.attributes.audioEnabled
				? t('spreed', 'Mute audio')
				: t('spreed', 'Unmute audio')
		},
	},

	mounted() {
		subscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	beforeUnmount() {
		unsubscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	expose: ['toggleAudio'],

	methods: {
		toggleAudio() {
			if (!this.model.attributes.audioAvailable) {
				emit('talk:media-settings:show')
				return
			}

			if (this.model.attributes.audioEnabled) {
				this.model.disableAudio()
			} else {
				this.model.enableAudio()
			}
		},

		updateDeviceState() {
			if (BrowserStorage.getItem('audioDisabled_' + this.token) === 'true') {
				this.model.disableAudio()
			} else {
				this.model.enableAudio()
			}
		},
	},
}
</script>

<style scoped>
.no-audio-available {
	opacity: .7;
}
</style>

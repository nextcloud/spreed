<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcPopover ref="popover"
		:boundary="boundaryElement"
		:show-triggers="[]"
		:hide-triggers="['click']"
		:auto-hide="false"
		:focus-trap="false"
		:shown="popupShown">
		<template #trigger>
			<NcButton :title="audioButtonTitle"
				:type="type"
				:aria-label="audioButtonAriaLabel"
				:class="{ 'no-audio-available': !model.attributes.audioAvailable }"
				:disabled="!isAudioAllowed"
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
		<div class="popover-hint">
			<span>{{ speakingWhileMutedWarner?.message }}</span>
		</div>
	</NcPopover>
</template>

<script>
import { onBeforeUnmount, ref, watch } from 'vue'

import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'

import VolumeIndicator from '../../UIShared/VolumeIndicator.vue'

import { PARTICIPANT } from '../../../constants.ts'
import BrowserStorage from '../../../services/BrowserStorage.js'
import SpeakingWhileMutedWarner from '../../../utils/webrtc/SpeakingWhileMutedWarner.js'

export default {
	name: 'LocalAudioControlButton',

	components: {
		NcButton,
		NcPopover,
		VolumeIndicator,
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

		disableMutedWarning: {
			type: Boolean,
			default: false,
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

	setup(props) {
		const boundaryElement = document.querySelector('.main-view')

		const popover = ref(null)
		const popupShown = ref(false)
		const speakingWhileMutedWarner = !props.disableMutedWarning
			? ref(new SpeakingWhileMutedWarner(props.model))
			: ref(null)

		if (!props.disableMutedWarning) {
			watch(() => speakingWhileMutedWarner.value.showPopup, (newValue) => {
				popupShown.value = newValue && isVisible(popover.value?.$el)
			})

			onBeforeUnmount(() => {
				speakingWhileMutedWarner.value.destroy()
			})
		}

		/**
		 * Check if component is visible and not obstructed by others
		 * @param element HTML element
		 */
		function isVisible(element) {
			if (!element) {
				return false // Element doesn't exist, therefore - not visible
			}
			const rect = element.getBoundingClientRect()
			return document.elementsFromPoint(rect.left, rect.top)?.[0] === element
		}

		return {
			boundaryElement,
			popover,
			popupShown,
			speakingWhileMutedWarner,
		}
	},

	computed: {
		isAudioAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
		},

		showMicrophoneOn() {
			return this.model.attributes.audioAvailable && this.model.attributes.audioEnabled
		},

		audioButtonTitle() {
			if (!this.isAudioAllowed) {
				return t('spreed', 'You are not allowed to enable audio')
			}

			if (!this.model.attributes.audioAvailable) {
				return t('spreed', 'No audio. Click to select device')
			}

			if (this.model.attributes.audioEnabled) {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Mute audio')
					: t('spreed', 'Mute audio (M)')
			} else {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Unmute audio')
					: t('spreed', 'Unmute audio (M)')
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

	created() {
		useHotKey('m', this.toggleAudio)
		useHotKey(' ', this.toggleAudio, { push: true })
	},

	mounted() {
		subscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	beforeDestroy() {
		unsubscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	expose: ['toggleAudio'],

	methods: {
		t,
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

.popover-hint {
	padding: calc(3 * var(--default-grid-baseline));
	max-width: 300px;
	text-align: start;
}
</style>

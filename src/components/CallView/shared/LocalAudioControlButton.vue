<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="local-audio-control-wrapper">
		<NcPopover ref="popover"
			:boundary="boundaryElement"
			:show-triggers="[]"
			:hide-triggers="['click']"
			:auto-hide="false"
			no-focus-trap
			:shown="popupShown">
			<template #trigger>
				<NcButton :title="audioButtonTitle"
					:variant="variant"
					:aria-label="audioButtonAriaLabel"
					:class="{
						'no-audio-available': !isAudioAvailable,
						'audio-control-button': showDevices,
					}"
					:disabled="!isAudioAllowed"
					@click.stop="toggleAudio">
					<template #icon>
						<VolumeIndicator
							:audio-preview-available="isAudioAvailable"
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

		<NcActions v-if="showDevices"
			:disabled="!isAudioAvailable || !isAudioAllowed"
			class="audio-selector-button"
			@open="updateDevices">
			<template #icon>
				<IconChevronUp :size="16" />
			</template>
			<NcActionCaption :name="t('spreed', 'Select a microphone')" />
			<NcActionButton v-for="device in audioInputDevices"
				:key="device.deviceId ?? 'none'"
				type="radio"
				:model-value="audioInputId"
				:value="device.deviceId"
				:title="device.label"
				@click="handleAudioInputIdChange(device.deviceId)">
				{{ device.label }}
			</NcActionButton>
			<template v-if="audioOutputSupported">
				<NcActionSeparator />
				<NcActionCaption :name="t('spreed', 'Select a speaker')" />
				<NcActionButton v-for="device in audioOutputDevices"
					:key="device.deviceId ?? 'none'"
					type="radio"
					:model-value="audioOutputId"
					:value="device.deviceId"
					:title="device.label"
					@click="handleAudioOutputIdChange(device.deviceId)">
					{{ device.label }}
				</NcActionButton>
			</template>
		</NcActions>
	</div>
</template>

<script>
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useHotKey } from '@nextcloud/vue/composables/useHotKey'
import { onBeforeUnmount, ref, watch } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionCaption from '@nextcloud/vue/components/NcActionCaption'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import VolumeIndicator from '../../UIShared/VolumeIndicator.vue'
import { useDevices } from '../../../composables/useDevices.js'
import { PARTICIPANT } from '../../../constants.ts'
import BrowserStorage from '../../../services/BrowserStorage.js'
import SpeakingWhileMutedWarner from '../../../utils/webrtc/SpeakingWhileMutedWarner.js'

export default {
	name: 'LocalAudioControlButton',

	components: {
		NcActions,
		NcActionButton,
		NcActionCaption,
		NcActionSeparator,
		NcButton,
		NcPopover,
		VolumeIndicator,
		IconChevronUp,
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

		variant: {
			type: String,
			default: 'tertiary-no-background',
		},

		token: {
			type: String,
			required: true,
		},

		showDevices: {
			type: Boolean,
			default: false,
		},
	},

	expose: ['toggleAudio'],

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

		const {
			devices,
			audioInputId,
			audioOutputId,
			updateDevices,
			audioOutputSupported,
			updatePreferences,
		} = useDevices(props.token, false)

		/**
		 * Check if component is visible and not obstructed by others
		 *
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
			devices,
			audioInputId,
			audioOutputId,
			updateDevices,
			audioOutputSupported,
			updatePreferences,
		}
	},

	computed: {
		isAudioAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
		},

		isAudioAvailable() {
			return this.model.attributes.audioAvailable
		},

		showMicrophoneOn() {
			return this.isAudioAvailable && this.model.attributes.audioEnabled
		},

		audioButtonTitle() {
			if (!this.isAudioAllowed) {
				return t('spreed', 'You are not allowed to enable audio')
			}

			if (!this.isAudioAvailable) {
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
			if (!this.isAudioAvailable) {
				return t('spreed', 'No audio. Click to select device')
			}

			return this.model.attributes.audioEnabled
				? t('spreed', 'Mute audio')
				: t('spreed', 'Unmute audio')
		},

		audioInputDevices() {
			return [...this.devices.filter((device) => device.kind === 'audioinput'), { deviceId: null, label: t('spreed', 'None') }]
		},

		audioOutputDevices() {
			return this.devices.filter((device) => device.kind === 'audiooutput')
		},
	},

	created() {
		useHotKey('m', this.toggleAudio)
		useHotKey(' ', this.toggleAudio, { push: true })
	},

	mounted() {
		subscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	beforeUnmount() {
		unsubscribe('local-audio-control-button:toggle-audio', this.updateDeviceState)
	},

	methods: {
		t,
		toggleAudio() {
			if (!this.isAudioAvailable) {
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

		handleAudioInputIdChange(audioInputId) {
			this.audioInputId = audioInputId
			this.updatePreferences('audioinput')
		},

		handleAudioOutputIdChange(audioOutputId) {
			this.audioOutputId = audioOutputId
			this.updatePreferences('audiooutput')
		},
	},
}
</script>

<style scoped lang="scss">
.no-audio-available {
	opacity: .7;
}

.popover-hint {
	padding: calc(3 * var(--default-grid-baseline));
	max-width: 300px;
	text-align: start;
}

.audio-selector-button :deep(.action-item__menutoggle) {
	--button-size: var(--clickable-area-small);
	height: var(--default-clickable-area);
	border-start-start-radius: 2px;
	border-end-start-radius: 2px;
}

.audio-control-button {
	border-start-end-radius: 2px;
	border-end-end-radius: 2px;
}

.local-audio-control-wrapper {
	display: flex;
	align-items: center;
	gap: 1px;
}

// Overwriting NcActionButton styles
:deep(.action-button__longtext) {
	display: -webkit-box;
	-webkit-line-clamp: 1;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
	padding: 0;
	max-width: 350px;
}

:deep(.action-button__longtext-wrapper) {
	max-width: 350px;
}

:deep(.action-button__icon) {
	width: 0;
	margin-inline-start: calc(var(--default-grid-baseline) * 3);
}

:deep(.action-button > span) {
	height: var(--default-clickable-area);
}
</style>

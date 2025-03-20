<!--
  - @copyright Copyright (c) 2021 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcModal v-if="modal"
		:container="container"
		@close="closeModal">
		<div class="media-settings">
			<h2 class="media-settings__title">
				{{ t('spreed', 'Media settings') }}
			</h2>
			<!-- Preview -->
			<div class="media-settings__preview">
				<video v-show="showVideo"
					ref="video"
					:class="['preview__video', {'preview__video--mirrored': isMirrored}]"
					disable-picture-in-picture="true"
					tabindex="-1" />
				<NcButton v-if="showVideo"
					type="secondary"
					class="media-settings__preview-mirror"
					:title="mirrorToggleLabel"
					:aria-label="mirrorToggleLabel"
					@click="isMirrored = !isMirrored">
					<template #icon>
						<ReflectHorizontal :size="20" />
					</template>
				</NcButton>
				<div v-show="!showVideo"
					class="preview__novideo">
					<VideoBackground :display-name="displayName"
						:user="userId" />
					<AvatarWrapper :id="userId"
						:token="token"
						:name="displayName"
						:source="actorType"
						:size="AVATAR.SIZE.EXTRA_LARGE"
						disable-menu
						disable-tooltip />
				</div>
			</div>

			<!-- Audio and video toggles -->
			<div class="media-settings__toggles-wrapper">
				<div class="media-settings__toggles">
					<!-- Audio toggle -->
					<NcButton v-tooltip="audioButtonTooltip"
						type="tertiary"
						:aria-label="audioButtonTooltip"
						:disabled="!audioPreviewAvailable"
						@click="toggleAudio">
						<template #icon>
							<VolumeIndicator :audio-preview-available="audioPreviewAvailable"
								:audio-enabled="audioOn"
								:current-volume="currentVolume"
								:volume-threshold="currentThreshold"
								overlay-muted-color="#888888" />
						</template>
					</NcButton>

					<!-- Video toggle -->
					<NcButton v-tooltip="videoButtonTooltip"
						type="tertiary"
						:aria-label="videoButtonTooltip"
						:disabled="!videoPreviewAvailable"
						@click="toggleVideo">
						<template #icon>
							<VideoIcon v-if="videoOn"
								:size="20" />
							<VideoOff v-else
								:size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Tabs -->
			<div class="media-settings__call-preferences">
				<NcButton :type="showDeviceSelection ? 'secondary' : 'tertiary'"
					@click="toggleTab('devices')">
					<template #icon>
						<Cog :size="20" />
					</template>
					{{ t('spreed', 'Devices') }}
				</NcButton>
				<NcButton v-if="isVirtualBackgroundAvailable"
					:type="showBackgroundEditor ? 'secondary' : 'tertiary'"
					@click="toggleTab('backgrounds')">
					<template #icon>
						<Creation :size="20" />
					</template>
					{{ t('spreed', 'Backgrounds') }}
				</NcButton>
			</div>

			<!-- Device selection -->
			<div v-if="showDeviceSelection" class="media-settings__device-selection">
				<MediaDevicesSelector kind="audioinput"
					:devices="devices"
					:device-id="audioInputId"
					@refresh="updateDevices"
					@update:deviceId="handleAudioInputIdChange" />
				<MediaDevicesSelector kind="videoinput"
					:devices="devices"
					:device-id="videoInputId"
					@refresh="updateDevices"
					@update:deviceId="handleVideoInputIdChange" />
				<MediaDevicesSpeakerTest />
			</div>

			<!-- Background selection -->
			<VideoBackgroundEditor v-if="showBackgroundEditor"
				:token="token"
				@update-background="handleUpdateVirtualBackground" />

			<!-- "Always show" setting -->
			<NcCheckboxRadioSwitch v-if="!isPublicShareAuthSidebar"
				class="checkbox"
				:checked="showMediaSettings || showRecordingWarning"
				:disabled="showRecordingWarning"
				@update:checked="setShowMediaSettings">
				{{ t('spreed', 'Always show preview for this conversation') }}
			</NcCheckboxRadioSwitch>

			<!-- Moderator options before starting a call-->
			<NcCheckboxRadioSwitch v-if="!hasCall && canModerateRecording"
				class="checkbox"
				:checked.sync="isRecordingFromStart">
				{{ t('spreed', 'Start recording immediately with the call') }}
			</NcCheckboxRadioSwitch>

			<!-- Recording warning -->
			<NcNoteCard v-if="showRecordingWarning" type="warning">
				<p v-if="isCurrentlyRecording">
					<strong>{{ t('spreed', 'The call is being recorded.') }}</strong>
				</p>
				<p v-else>
					<strong>{{ t('spreed', 'The call might be recorded.') }}</strong>
				</p>
				<template v-if="isRecordingConsentRequired">
					<p>
						{{ t('spreed', 'The recording might include your voice, video from camera, and screen share. Your consent is required before joining the call.') }}
					</p>
					<NcCheckboxRadioSwitch class="checkbox--warning"
						:checked="recordingConsentGiven"
						@update:checked="setRecordingConsentGiven">
						{{ t('spreed', 'Give consent to the recording of this call') }}
					</NcCheckboxRadioSwitch>
				</template>
			</NcNoteCard>

			<!-- buttons bar at the bottom -->
			<div class="media-settings__call-buttons">
				<!-- Silent call -->
				<NcActions v-if="showSilentCallOption" :container="container" force-menu>
					<NcActionButton v-if="!silentCall"
						:name="t('spreed', 'Call without notification')"
						close-after-click
						@click="setSilentCall(true)">
						{{ t('spreed', 'The conversation participants will not be notified about this call') }}
						<template #icon>
							<BellOff :size="16" />
						</template>
					</NcActionButton>
					<NcActionButton v-else
						:name="t('spreed', 'Normal call')"
						close-after-click
						@click="setSilentCall(false)">
						<template #icon>
							<Bell :size="16" />
						</template>
						{{ t('spreed', 'The conversation participants will be notified about this call') }}
					</NcActionButton>
				</NcActions>

				<!-- Join call -->
				<CallButton v-if="!isInCall"
					class="call-button"
					is-media-settings
					:is-recording-from-start="isRecordingFromStart"
					:disabled="isRecordingConsentRequired && !recordingConsentGiven"
					:recording-consent-given="recordingConsentGiven"
					:silent-call="silentCall" />
				<NcButton v-else-if="showUpdateChangesButton" @click="closeModalAndApplySettings">
					{{ t('spreed', 'Apply settings') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { ref } from 'vue'

import Bell from 'vue-material-design-icons/Bell.vue'
import BellOff from 'vue-material-design-icons/BellOff.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import ReflectHorizontal from 'vue-material-design-icons/ReflectHorizontal.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'

import MediaDevicesSelector from './MediaDevicesSelector.vue'
import MediaDevicesSpeakerTest from './MediaDevicesSpeakerTest.vue'
import VideoBackgroundEditor from './VideoBackgroundEditor.vue'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import VideoBackground from '../CallView/shared/VideoBackground.vue'
import CallButton from '../TopBar/CallButton.vue'
import VolumeIndicator from '../UIShared/VolumeIndicator.vue'

import { useDevices } from '../../composables/useDevices.js'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { AVATAR, CALL, PARTICIPANT, VIRTUAL_BACKGROUND } from '../../constants.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { useGuestNameStore } from '../../stores/guestName.js'
import { useSettingsStore } from '../../stores/settings.js'
import { localMediaModel } from '../../utils/webrtc/index.js'

const recordingEnabled = getCapabilities()?.spreed?.config?.call?.recording || false
const recordingConsent = getCapabilities()?.spreed?.config?.call?.['recording-consent']

export default {
	name: 'MediaSettings',

	components: {
		AvatarWrapper,
		Bell,
		BellOff,
		CallButton,
		Cog,
		Creation,
		NcActionButton,
		NcActions,
		NcButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcNoteCard,
		MediaDevicesSelector,
		MediaDevicesSpeakerTest,
		ReflectHorizontal,
		VideoBackground,
		VideoIcon,
		VideoOff,
		VolumeIndicator,
		VideoBackgroundEditor,
	},

	props: {
		recordingConsentGiven: {
			type: Boolean,
			default: false
		}
	},

	emits: ['update:recording-consent-given'],

	setup() {
		const video = ref(null)
		const isInCall = useIsInCall()
		const guestNameStore = useGuestNameStore()
		const settingsStore = useSettingsStore()

		const {
			devices,
			updateDevices,
			updatePreferences,
			currentVolume,
			currentThreshold,
			audioPreviewAvailable,
			videoPreviewAvailable,
			audioInputId,
			videoInputId,
			initializeDevices,
			stopDevices,
			virtualBackground,
		} = useDevices(video, false)

		return {
			AVATAR,
			isInCall,
			guestNameStore,
			settingsStore,
			video,
			// useDevices
			devices,
			updateDevices,
			updatePreferences,
			currentVolume,
			currentThreshold,
			audioPreviewAvailable,
			videoPreviewAvailable,
			audioInputId,
			videoInputId,
			initializeDevices,
			stopDevices,
			virtualBackground,
			model: localMediaModel,
		}
	},

	data() {
		return {
			modal: false,
			tabContent: 'none',
			audioOn: undefined,
			videoOn: undefined,
			silentCall: false,
			updatedBackground: undefined,
			audioDeviceStateChanged: false,
			videoDeviceStateChanged: false,
			isRecordingFromStart: false,
			isPublicShareAuthSidebar: false,
			isMirrored: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		displayName() {
			return this.$store.getters.getDisplayName()
		},

		guestName() {
			return this.guestNameStore.getGuestName(
				this.$store.getters.getToken(),
				this.$store.getters.getActorId(),
			)
		},

		userId() {
			return this.$store.getters.getUserId()
		},

		actorType() {
			return this.$store.getters.getActorType()
		},

		token() {
			return this.$store.getters.getToken()
		},

		showMediaSettings() {
			return this.settingsStore.getShowMediaSettings(this.token)
		},

		showVideo() {
			return this.videoPreviewAvailable && this.videoOn
		},

		audioButtonTooltip() {
			if (!this.audioPreviewAvailable) {
				return t('spreed', 'No audio')
			}
			return this.audioOn ? t('spreed', 'Mute audio') : t('spreed', 'Unmute audio')
		},

		videoButtonTooltip() {
			if (!this.videoPreviewAvailable) {
				return t('spreed', 'No camera')
			}
			return this.videoOn ? t('spreed', 'Disable video') : t('spreed', 'Enable video')
		},

		mirrorToggleLabel() {
			return this.isMirrored
				? t('spreed', 'Display video as you will see it (mirrored)')
				: t('spreed', 'Display video as others will see it')
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasCall() {
			return this.conversation.hasCall
		},

		isCurrentlyRecording() {
			return [CALL.RECORDING.VIDEO_STARTING, CALL.RECORDING.AUDIO_STARTING,
				CALL.RECORDING.VIDEO, CALL.RECORDING.AUDIO].includes(this.conversation.callRecording)
		},

		canFullModerate() {
			return this.conversation.participantType === PARTICIPANT.TYPE.OWNER
				|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		canModerateRecording() {
			return this.canFullModerate && recordingEnabled
		},

		isRecordingConsentRequired() {
			return recordingConsent === CALL.RECORDING_CONSENT.REQUIRED
				|| (recordingConsent === CALL.RECORDING_CONSENT.OPTIONAL && this.conversation.recordingConsent === CALL.RECORDING_CONSENT.REQUIRED)
		},

		showRecordingWarning() {
			return !this.isInCall && (this.isCurrentlyRecording || this.isRecordingConsentRequired)
		},

		showSilentCallOption() {
			return !(this.hasCall && !this.isInLobby) && !this.isPublicShareAuthSidebar
		},

		showDeviceSelection() {
			return this.tabContent === 'devices'
		},

		showBackgroundEditor() {
			return this.tabContent === 'backgrounds'
		},

		isVirtualBackgroundAvailable() {
			return this.virtualBackground.isAvailable()
		},

		showUpdateChangesButton() {
			return this.updatedBackground || this.audioDeviceStateChanged
				|| this.videoDeviceStateChanged
		},
	},

	watch: {
		modal(newValue) {
			if (newValue) {
				this.audioOn = !BrowserStorage.getItem('audioDisabled_' + this.token)
				this.videoOn = !BrowserStorage.getItem('videoDisabled_' + this.token)
				this.silentCall = !!BrowserStorage.getItem('silentCall_' + this.token)

				// Set virtual background depending on BrowserStorage's settings
				if (BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token) === 'true') {
					if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
						this.blurVirtualBackground()
					} else if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
						this.setVirtualBackgroundImage(BrowserStorage.getItem('virtualBackgroundUrl_' + this.token))
					}
				} else {
					this.clearVirtualBackground()
				}

				this.initializeDevices()
			} else {
				this.stopDevices()
			}
		},

		audioInputId(audioInputId) {
			if (this.showDeviceSelection && audioInputId && !this.audioOn) {
				this.toggleAudio()
			}
		},

		videoInputId(videoInputId) {
			if (this.showDeviceSelection && videoInputId && !this.videoOn) {
				this.toggleVideo()
			}
		},

		isRecordingFromStart(value) {
			this.setRecordingConsentGiven(value)
		},
	},

	mounted() {
		subscribe('talk:media-settings:show', this.showModal)
		subscribe('talk:media-settings:hide', this.closeModalAndApplySettings)

		// FIXME: this is a workaround to remove the old key from the browser storage
		// To be removed in the future
		if (BrowserStorage.getItem('devicesPreferred')) {
			BrowserStorage.removeItem('devicesPreferred')
		}
	},

	beforeDestroy() {
		unsubscribe('talk:media-settings:show', this.showModal)
		unsubscribe('talk:media-settings:hide', this.closeModalAndApplySettings)
	},

	methods: {
		showModal(page) {
			this.modal = true
			if (page === 'video-verification') {
				this.isPublicShareAuthSidebar = true
			}

			if (!BrowserStorage.getItem('audioInputDevicePreferred') || !BrowserStorage.getItem('videoInputDevicePreferred')) {
				this.tabContent = 'devices'
			}
		},

		closeModal() {
			this.modal = false
			this.updatedBackground = undefined
			this.audioDeviceStateChanged = false
			this.videoDeviceStateChanged = false
			this.isPublicShareAuthSidebar = false
			this.isRecordingFromStart = false
			this.isMirrored = false
			// Update devices preferences
			this.updatePreferences('audioinput')
			this.updatePreferences('videoinput')
		},

		toggleAudio() {
			if (!this.audioOn) {
				BrowserStorage.removeItem('audioDisabled_' + this.token)
				this.audioOn = true
			} else {
				BrowserStorage.setItem('audioDisabled_' + this.token, 'true')
				this.audioOn = false
			}
			this.audioDeviceStateChanged = !this.audioDeviceStateChanged
		},

		toggleVideo() {
			if (!this.videoOn) {
				BrowserStorage.removeItem('videoDisabled_' + this.token)
				this.videoOn = true
			} else {
				BrowserStorage.setItem('videoDisabled_' + this.token, 'true')
				this.videoOn = false
			}
			this.videoDeviceStateChanged = !this.videoDeviceStateChanged
		},

		setSilentCall(value) {
			this.silentCall = value
			if (value) {
				BrowserStorage.setItem('silentCall_' + this.token, 'true')
			} else {
				BrowserStorage.removeItem('silentCall_' + this.token)
			}
		},

		closeModalAndApplySettings() {
			if (this.updatedBackground) {
				this.handleUpdateBackground(this.updatedBackground)
			}
			if (this.audioDeviceStateChanged) {
				emit('local-audio-control-button:toggle-audio')
			}
			if (this.videoDeviceStateChanged) {
				emit('local-video-control-button:toggle-video')
			}

			this.closeModal()
		},

		handleUpdateBackground(background) {
			if (background === 'none') {
				this.clearBackground()
			} else if (background === 'blur') {
				this.blurBackground()
			} else {
				this.setBackgroundImage(background)
			}
		},

		handleUpdateVirtualBackground(background) {
			this.updatedBackground = background
			if (background === 'none') {
				this.clearVirtualBackground()
			} else if (background === 'blur') {
				this.blurVirtualBackground()
			} else {
				this.setVirtualBackgroundImage(background)
			}
		},

		/**
		 * Clears the virtualBackground: the background used in the MediaSettings preview
		 */
		clearVirtualBackground() {
			this.virtualBackground.setEnabled(false)
		},

		/**
		 * Clears the background of the participants in current or future call
		 */
		clearBackground() {
			if (this.isInCall) {
				localMediaModel.disableVirtualBackground()
			} else {
				BrowserStorage.removeItem('virtualBackgroundEnabled_' + this.token)
			}
		},

		/**
		 * Blurs the virtualBackground: the background used in the MediaSettings preview
		 */
		blurVirtualBackground() {
			this.virtualBackground.setEnabled(true)
			this.virtualBackground.setVirtualBackground({
				backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR,
				blurValue: VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT,
			})
		},

		/**
		 * Blurs the background of the participants in current or future call
		 */
		blurBackground() {
			if (this.isInCall) {
				localMediaModel.enableVirtualBackground()
				localMediaModel.setVirtualBackgroundBlur(VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT)
			} else {
				BrowserStorage.setItem('virtualBackgroundEnabled_' + this.token, 'true')
				BrowserStorage.setItem('virtualBackgroundType_' + this.token, VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR)
				BrowserStorage.setItem('virtualBackgroundBlurStrength_' + this.token, VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT)
			}
		},

		/**
		 * Sets an image as background in virtualBackground: the background used in the MediaSettings preview
		 *
		 * @param {string} background the image's url
		 */
		setVirtualBackgroundImage(background) {
			this.virtualBackground.setEnabled(true)
			this.virtualBackground.setVirtualBackground({
				backgroundType: VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE,
				virtualSource: background,
			})
		},

		/**
		 * Sets an image as background of the participants in current or future call
		 *
		 * @param {string} background the image's url
		 */
		setBackgroundImage(background) {
			if (this.isInCall) {
				localMediaModel.enableVirtualBackground()
				localMediaModel.setVirtualBackgroundImage(background)
			} else {
				BrowserStorage.setItem('virtualBackgroundEnabled_' + this.token, 'true')
				BrowserStorage.setItem('virtualBackgroundType_' + this.token, VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE)
				BrowserStorage.setItem('virtualBackgroundUrl_' + this.token, background)
			}
		},

		toggleTab(tab) {
			if (this.tabContent !== tab) {
				this.tabContent = tab
			} else {
				this.tabContent = 'none'
			}
		},

		setShowMediaSettings(newValue) {
			this.settingsStore.setShowMediaSettings(this.token, newValue)
		},

		setRecordingConsentGiven(value) {
			this.$emit('update:recording-consent-given', value)
		},

		handleAudioInputIdChange(audioInputId) {
			this.audioInputId = audioInputId
			this.updatePreferences('audioinput')
		},

		handleVideoInputIdChange(videoInputId) {
			this.videoInputId = videoInputId
			this.updatePreferences('videoinput')
		},
	},
}
</script>

<style lang="scss" scoped>
.media-settings {
	padding: calc(var(--default-grid-baseline) * 5);
	padding-bottom: 0;

	&__title {
		text-align: center;
	}

	&__preview {
		position: relative;
		margin: 0 auto calc(var(--default-grid-baseline) * 3);
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		border-radius: calc(var(--default-grid-baseline) * 3);
		background-color: var(--color-loading-dark);
		width: 100%;
		aspect-ratio: 4/3;
	}

	&__preview > &__preview-mirror {
		position: absolute;
		top: var(--default-grid-baseline);
		right: var(--default-grid-baseline);
	}

	&__toggles-wrapper {
		width: 100%;
		display: flex;
		justify-content: center;
		position: relative;
		height: calc(var(--default-grid-baseline) * 4);
	}

	&__toggles {
		display: flex;
		position: absolute;
		top: calc(var(--default-grid-baseline) * -9);
		background: var(--color-main-background);
		border-radius: var(--border-radius-pill);
		box-shadow: 0 0 var(--default-grid-baseline) var(--color-box-shadow);
	}

	&__device-selection {
		width: 100%;
	}

	&__call-preferences {
		height: var(--default-clickable-area);
		display: flex;
		justify-content: center;
		align-items: center;
		gap: calc(var(--default-grid-baseline) * 2);
	}

	&__call-buttons {
		display: flex;
		z-index: 1;
		align-items: center;
		justify-content: center;
		gap: var(--default-grid-baseline);
		position: sticky;
		bottom: 0;
		background-color: var(--color-main-background);
		padding: 10px 0 20px;
	}
}

.preview {
	&__video {
		max-width: 100%;
		object-fit: contain;
		max-height: 100%;

		&--mirrored {
			transform: none !important;
		}
	}

	&__novideo {
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		width: 100%;
	}
}

.call-button {
	display: flex;
	justify-content: center;
	align-items: center;
}

.checkbox {
	display: flex;
	justify-content: center;
	margin: calc(var(--default-grid-baseline) * 2);

	&--warning {
		&:focus-within :deep(.checkbox-radio-switch__label),
		& :deep(.checkbox-radio-switch__label:hover) {
			background-color: var(--note-background) !important;
		}
	}
}

:deep(.modal-wrapper--normal > .modal-container) {
	max-width: 500px !important;
}
</style>

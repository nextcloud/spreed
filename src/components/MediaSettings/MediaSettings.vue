<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal v-if="modal"
		:label-id="dialogHeaderId"
		@close="closeModal">
		<div class="media-settings">
			<h2 :id="dialogHeaderId" class="media-settings__title nc-dialog-alike-header">
				{{ t('spreed', 'Media settings') }}
			</h2>
			<!-- Preview -->
			<div class="media-settings__preview">
				<video v-show="showVideo"
					ref="video"
					class="preview__video"
					:class="{ 'preview__video--mirrored': isMirrored }"
					disablePictureInPicture
					tabindex="-1" />
				<NcButton v-if="showVideo"
					variant="secondary"
					class="media-settings__preview-mirror"
					:title="mirrorToggleLabel"
					:aria-label="mirrorToggleLabel"
					@click="isMirrored = !isMirrored">
					<template #icon>
						<IconReflectHorizontal :size="20" />
					</template>
				</NcButton>
				<div v-show="!showVideo"
					class="preview__novideo">
					<VideoBackground :display-name="displayName"
						:user="userId" />
					<AvatarWrapper :id="userId"
						:token="token"
						:name="displayName"
						:source="actorStore.actorType"
						:size="AVATAR.SIZE.EXTRA_LARGE"
						disable-menu
						disable-tooltip />
				</div>

				<!-- Audio and video toggles -->
				<div class="media-settings__toggles">
					<!-- Audio toggle -->
					<NcButton variant="tertiary"
						:title="audioButtonTitle"
						:aria-label="audioButtonTitle"
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
					<NcButton variant="tertiary"
						:title="videoButtonTitle"
						:aria-label="videoButtonTitle"
						:disabled="!videoPreviewAvailable"
						@click="toggleVideo">
						<template #icon>
							<IconVideo v-if="videoOn" :size="20" />
							<IconVideoOff v-else :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Tab panels -->
			<MediaSettingsTabs :active.sync="tabContent" :tabs="tabs">
				<template #tab-panel:devices>
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
					<MediaDevicesSelector v-if="audioOutputSupported"
						kind="audiooutput"
						:devices="devices"
						:device-id="audioOutputId"
						@refresh="updateDevices"
						@update:deviceId="handleAudioOutputIdChange" />
					<MediaDevicesSpeakerTest />
				</template>

				<template #tab-panel:backgrounds>
					<VideoBackgroundEditor class="media-settings__tab"
						:token="token"
						:skip-blur-virtual-background="skipBlurVirtualBackground"
						@update-background="handleUpdateVirtualBackground" />
				</template>
			</MediaSettingsTabs>

			<!-- Dashboard Device checker-->
			<template v-if="isInTalkDashboard">
				<NcCheckboxRadioSwitch v-if="supportStartWithoutMedia"
					id="call-media"
					:model-value="startWithoutMediaEnabled"
					:disabled="mediaLoading"
					type="switch"
					@update:model-value="toggleStartWithoutMedia">
					{{ t('spreed', 'Turn off camera and microphone by default when joining a call') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-if="supportDefaultBlurVirtualBackground"
					type="switch"
					:model-value="blurVirtualBackgroundEnabled"
					@update:model-value="setBlurVirtualBackgroundEnabled">
					{{ t('spreed', 'Enable blur background by default for all conversation') }}
				</NcCheckboxRadioSwitch>
			</template>
			<template v-else>
				<!-- "Always show" setting -->
				<NcCheckboxRadioSwitch v-if="!isPublicShareAuthSidebar"
					class="checkbox"
					:model-value="showMediaSettings || showRecordingWarning"
					:disabled="showRecordingWarning"
					@update:model-value="setShowMediaSettings">
					{{ t('spreed', 'Always show preview for this conversation') }}
				</NcCheckboxRadioSwitch>

				<!-- Moderator options before starting a call-->
				<NcCheckboxRadioSwitch v-if="!hasCall && canModerateRecording"
					v-model="isRecordingFromStart"
					class="checkbox">
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
							:model-value="recordingConsentGiven"
							@update:model-value="setRecordingConsentGiven">
							{{ t('spreed', 'Give consent to the recording of this call') }}
						</NcCheckboxRadioSwitch>
					</template>
				</NcNoteCard>
			</template>
			<!-- buttons bar at the bottom -->
			<div class="media-settings__call-buttons">
				<!-- Silent call -->
				<template v-if="!isInTalkDashboard">
					<NcActions v-if="showSilentCallOption" force-menu>
						<NcActionButton v-if="!silentCall"
							:name="t('spreed', 'Call without notification')"
							close-after-click
							@click="setSilentCall(true)">
							{{ t('spreed', 'The conversation participants will not be notified about this call') }}
							<template #icon>
								<IconBellOff :size="16" />
							</template>
						</NcActionButton>
						<NcActionButton v-else
							:name="t('spreed', 'Normal call')"
							close-after-click
							@click="setSilentCall(false)">
							<template #icon>
								<IconBell :size="16" />
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
				</template>
				<NcButton v-if="showUpdateChangesButton" @click="closeModalAndApplySettings">
					{{ isInTalkDashboard ? t('spreed', 'Save') : t('spreed', 'Apply settings') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { computed, markRaw, ref } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import IconBell from 'vue-material-design-icons/Bell.vue'
import IconBellOff from 'vue-material-design-icons/BellOff.vue'
import IconCog from 'vue-material-design-icons/Cog.vue'
import IconCreation from 'vue-material-design-icons/Creation.vue'
import IconReflectHorizontal from 'vue-material-design-icons/ReflectHorizontal.vue'
import IconVideo from 'vue-material-design-icons/Video.vue'
import IconVideoOff from 'vue-material-design-icons/VideoOff.vue'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import VideoBackground from '../CallView/shared/VideoBackground.vue'
import CallButton from '../TopBar/CallButton.vue'
import VolumeIndicator from '../UIShared/VolumeIndicator.vue'
import MediaDevicesSelector from './MediaDevicesSelector.vue'
import MediaDevicesSpeakerTest from './MediaDevicesSpeakerTest.vue'
import MediaSettingsTabs from './MediaSettingsTabs.vue'
import VideoBackgroundEditor from './VideoBackgroundEditor.vue'
import { useDevices } from '../../composables/useDevices.js'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useId } from '../../composables/useId.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { AVATAR, CALL, CONFIG, PARTICIPANT, VIRTUAL_BACKGROUND } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useGuestNameStore } from '../../stores/guestName.js'
import { useSettingsStore } from '../../stores/settings.js'
import { localMediaModel } from '../../utils/webrtc/index.js'

const supportStartWithoutMedia = getTalkConfig('local', 'call', 'start-without-media') !== undefined
const supportDefaultBlurVirtualBackground = getTalkConfig('local', 'call', 'blur-virtual-background') !== undefined

export default {
	name: 'MediaSettings',

	components: {
		AvatarWrapper,
		CallButton,
		MediaDevicesSelector,
		MediaDevicesSpeakerTest,
		MediaSettingsTabs,
		NcActionButton,
		NcActions,
		NcButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcNoteCard,
		VideoBackground,
		VideoBackgroundEditor,
		VolumeIndicator,
		// Icons
		IconBell,
		IconBellOff,
		IconReflectHorizontal,
		IconVideo,
		IconVideoOff,
	},

	props: {
		recordingConsentGiven: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['update:recording-consent-given'],

	setup() {
		const video = ref(null)
		const isInCall = useIsInCall()
		const guestNameStore = useGuestNameStore()
		const settingsStore = useSettingsStore()
		const dialogHeaderId = `media-settings-header-${useId()}`

		const {
			devices,
			updateDevices,
			updatePreferences,
			currentVolume,
			currentThreshold,
			audioPreviewAvailable,
			videoPreviewAvailable,
			audioInputId,
			audioOutputId,
			videoInputId,
			audioOutputSupported,
			initializeDevices,
			stopDevices,
			virtualBackground,
		} = useDevices(video, false)

		const isVirtualBackgroundAvailable = computed(() => virtualBackground.value.isAvailable())

		const devicesTab = {
			id: 'devices',
			label: t('spreed', 'Devices'),
			icon: markRaw(IconCog),
		}
		const backgroundsTab = {
			id: 'backgrounds',
			label: t('spreed', 'Backgrounds'),
			icon: markRaw(IconCreation),
		}
		const tabs = computed(() => isVirtualBackgroundAvailable.value ? [devicesTab, backgroundsTab] : [devicesTab])

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
			audioOutputId,
			videoInputId,
			audioOutputSupported,
			initializeDevices,
			stopDevices,
			virtualBackground,
			model: localMediaModel,
			tabs,
			dialogHeaderId,
			supportStartWithoutMedia,
			supportDefaultBlurVirtualBackground,
			actorStore: useActorStore(),
			token: useGetToken(),
		}
	},

	data() {
		return {
			modal: false,
			tabContent: undefined,
			audioOn: undefined,
			videoOn: undefined,
			silentCall: false,
			updatedBackground: undefined,
			audioDeviceStateChanged: false,
			videoDeviceStateChanged: false,
			isRecordingFromStart: false,
			isPublicShareAuthSidebar: false,
			isMirrored: false,
			skipBlurVirtualBackground: false,
			mediaLoading: false,
			isInTalkDashboard: false,
		}
	},

	computed: {
		displayName() {
			return this.actorStore.displayName
		},

		guestName() {
			return this.guestNameStore.getGuestName(
				this.token,
				this.actorStore.actorId,
			)
		},

		userId() {
			return this.actorStore.userId
		},

		showMediaSettings() {
			return this.settingsStore.getShowMediaSettings(this.token)
		},

		blurVirtualBackgroundEnabled() {
			return this.settingsStore.blurVirtualBackgroundEnabled
		},

		showVideo() {
			return this.videoPreviewAvailable && this.videoOn
		},

		audioButtonTitle() {
			if (!this.audioPreviewAvailable) {
				return t('spreed', 'No audio')
			}
			return this.audioOn ? t('spreed', 'Mute audio') : t('spreed', 'Unmute audio')
		},

		videoButtonTitle() {
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
			return [CALL.RECORDING.VIDEO_STARTING,
				CALL.RECORDING.AUDIO_STARTING,
				CALL.RECORDING.VIDEO,
				CALL.RECORDING.AUDIO].includes(this.conversation.callRecording)
		},

		canFullModerate() {
			return this.conversation.participantType === PARTICIPANT.TYPE.OWNER
				|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR
		},

		isInLobby() {
			return this.$store.getters.isInLobby
		},

		canModerateRecording() {
			return this.canFullModerate && (getTalkConfig(this.token, 'call', 'recording') || false)
		},

		recordingConsent() {
			return getTalkConfig(this.token, 'call', 'recording-consent')
		},

		isRecordingConsentRequired() {
			return this.recordingConsent === CONFIG.RECORDING_CONSENT.REQUIRED
				|| (this.recordingConsent === CONFIG.RECORDING_CONSENT.OPTIONAL && this.conversation.recordingConsent === CALL.RECORDING_CONSENT.ENABLED)
		},

		showRecordingWarning() {
			return !this.isInCall && (this.isCurrentlyRecording || this.isRecordingConsentRequired)
		},

		showSilentCallOption() {
			return !(this.hasCall && !this.isInLobby) && !this.isPublicShareAuthSidebar
		},

		showUpdateChangesButton() {
			return (this.isInTalkDashboard || this.isInCall)
				&& (this.updatedBackground
					|| this.audioDeviceStateChanged
					|| this.videoDeviceStateChanged)
		},

		connectionFailed() {
			return this.$store.getters.connectionFailed(this.token)
		},

		startWithoutMediaEnabled() {
			return this.settingsStore.startWithoutMedia
		},
	},

	watch: {
		modal(newValue) {
			if (newValue) {
				if (this.settingsStore.startWithoutMedia) {
					// Disable audio
					this.audioOn = false
					BrowserStorage.setItem('audioDisabled_' + this.token, 'true')
					// Disable video
					this.videoOn = false
					BrowserStorage.setItem('videoDisabled_' + this.token, 'true')
				} else {
					this.audioOn = !BrowserStorage.getItem('audioDisabled_' + this.token)
					this.videoOn = !BrowserStorage.getItem('videoDisabled_' + this.token)
				}
				this.silentCall = !!BrowserStorage.getItem('silentCall_' + this.token)

				// Set virtual background depending on BrowserStorage's settings
				if (BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token) === 'true') {
					if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
						this.blurVirtualBackground()
					} else if (BrowserStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
						this.setVirtualBackgroundImage(BrowserStorage.getItem('virtualBackgroundUrl_' + this.token))
					}
				} else if (this.blurVirtualBackgroundEnabled && !this.skipBlurVirtualBackground) {
					// Fall back to global blur background setting
					this.blurVirtualBackground()
				} else {
					this.clearVirtualBackground()
				}

				this.initializeDevices()
			} else {
				this.stopDevices()
			}
		},

		audioInputId(audioInputId) {
			if (this.tabContent === 'devices' && audioInputId && !this.audioOn) {
				this.toggleAudio()
			}
		},

		videoInputId(videoInputId) {
			if (this.tabContent === 'devices' && videoInputId && !this.videoOn) {
				this.toggleVideo()
			}
		},

		isRecordingFromStart(value) {
			this.setRecordingConsentGiven(value)
		},

		isInCall(value) {
			if (value) {
				const virtualBackgroundEnabled = BrowserStorage.getItem('virtualBackgroundEnabled_' + this.token) === 'true'
				// Apply global blur background setting
				if (this.blurVirtualBackgroundEnabled && !this.skipBlurVirtualBackground && !virtualBackgroundEnabled) {
					this.blurBackground(true)
				}
			} else {
				// Reset the flag for the next call
				this.skipBlurVirtualBackground = false
			}
		},

		connectionFailed(value) {
			if (value) {
				this.skipBlurVirtualBackground = false
			}
		},
	},

	beforeMount() {
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
		t,
		showModal(page) {
			this.modal = true
			if (page === 'video-verification') {
				this.isPublicShareAuthSidebar = true
			}

			if (page === 'device-check') {
				this.isInTalkDashboard = true
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
			this.isInTalkDashboard = false
			this.isRecordingFromStart = false
			this.isMirrored = false
			// Update devices preferences
			this.updatePreferences('audioinput')
			this.updatePreferences('audiooutput')
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
			// Default global blur background setting was changed by user
			if (this.blurVirtualBackgroundEnabled && background !== 'blur') {
				this.skipBlurVirtualBackground = true
			}
			// Apply the new background
			if (background === 'none') {
				this.clearBackground()
			} else if (background === 'blur') {
				this.blurBackground(this.blurVirtualBackgroundEnabled)
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
		 *
		 * @param {boolean} globalBlurVirtualBackground - Whether the global blur background setting is enabled (in Talk settings)
		 */
		blurBackground(globalBlurVirtualBackground = false) {
			if (this.isInCall) {
				localMediaModel.enableVirtualBackground()
				localMediaModel.setVirtualBackgroundBlur(VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT, globalBlurVirtualBackground)
			} else if (!globalBlurVirtualBackground) {
				this.skipBlurVirtualBackground = true
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

		handleAudioOutputIdChange(audioOutputId) {
			this.audioOutputId = audioOutputId
			this.updatePreferences('audiooutput')
		},

		handleVideoInputIdChange(videoInputId) {
			this.videoInputId = videoInputId
			this.updatePreferences('videoinput')
		},

		async toggleStartWithoutMedia(value) {
			this.mediaLoading = true
			try {
				await this.settingsStore.setStartWithoutMedia(value)
				showSuccess(t('spreed', 'Your default media state has been saved'))
			} catch (exception) {
				showError(t('spreed', 'Error while setting default media state'))
			} finally {
				this.mediaLoading = false
			}
		},

		async setBlurVirtualBackgroundEnabled(value) {
			try {
				await this.settingsStore.setBlurVirtualBackgroundEnabled(value)
				if (value) {
					this.blurVirtualBackground()
				} else {
					this.virtualBackground.setEnabled(false)
				}
			} catch (error) {
				console.error('Failed to set blur background enabled:', error)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.media-settings {
	padding: calc(var(--default-grid-baseline) * 5);
	padding-bottom: 0;

	&__preview {
		position: relative;
		margin: 0 auto calc(var(--default-grid-baseline) * 4);
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: var(--border-radius-element, var(--border-radius-large));
		background-color: var(--color-loading-dark);
		width: 100%;
		aspect-ratio: 4/3;
	}

	&__preview > &__preview-mirror {
		position: absolute;
		top: calc(var(--default-grid-baseline) * 2);
		inset-inline-end: calc(var(--default-grid-baseline) * 2);
	}

	&__toggles {
		display: flex;
		position: absolute;
		bottom: calc(var(--default-grid-baseline) * -2);
		background: var(--color-main-background);
		border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
		box-shadow: 0 0 var(--default-grid-baseline) var(--color-box-shadow);
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
		border-radius: var(--border-radius-element);

		&--mirrored {
			transform: none !important;
		}
	}

	&__novideo {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		width: 100%;
		height: 100%;
		border-radius: var(--border-radius-element);
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

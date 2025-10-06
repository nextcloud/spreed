<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<component
		:is="isDialog ? 'NcModal' : 'div'"
		v-if="show"
		:size="isDialog ? 'large' : undefined"
		:label-id="isDialog ? dialogHeaderId : undefined"
		@close="close">
		<div class="media-settings">
			<h2
				v-if="isDialog"
				:id="dialogHeaderId"
				class="media-settings__title nc-dialog-alike-header">
				{{ t('spreed', 'Check devices') }}
			</h2>
			<!-- Recording warning -->
			<NcNoteCard
				v-if="showRecordingWarning"
				:class="{ 'media-settings__recording-warning--mobile': isMobile }"
				type="warning">
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
					<NcCheckboxRadioSwitch
						class="checkbox--warning"
						:model-value="recordingConsentGiven"
						@update:model-value="setRecordingConsentGiven">
						{{ t('spreed', 'Give consent to the recording of this call') }}
					</NcCheckboxRadioSwitch>
				</template>
			</NcNoteCard>
			<div class="media-settings__content" :class="{ 'media-settings__content--mobile': isMobile }">
				<!-- Preview -->
				<div class="media-settings__preview">
					<video
						v-show="showVideo"
						ref="video"
						class="preview__video"
						:class="{ 'preview__video--mirrored': isMirrored }"
						disablePictureInPicture
						tabindex="-1" />
					<NcButton
						v-if="showVideo"
						variant="secondary"
						class="media-settings__preview-mirror"
						:title="mirrorToggleLabel"
						:aria-label="mirrorToggleLabel"
						@click="isMirrored = !isMirrored">
						<template #icon>
							<IconReflectHorizontal :size="20" />
						</template>
					</NcButton>
					<div
						v-show="!showVideo"
						class="preview__novideo">
						<VideoBackground
							:display-name="displayName"
							:user="userId" />
						<AvatarWrapper
							:id="userId"
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
						<NcButton
							v-if="!audioStreamError"
							variant="tertiary"
							:title="audioButtonTitle"
							:aria-label="audioButtonTitle"
							:disabled="!audioPreviewAvailable"
							@click="toggleAudio">
							<template #icon>
								<VolumeIndicator
									:audio-preview-available="audioPreviewAvailable"
									:audio-enabled="audioOn"
									:current-volume="currentVolume"
									:volume-threshold="currentThreshold"
									overlay-muted-color="#888888" />
							</template>
						</NcButton>
						<NcPopover
							v-else
							:title="t('spreed', 'Show more info')"
							no-focus-trap>
							<template #trigger>
								<NcButton
									variant="error"
									:aria-label="t('spreed', 'Audio is not available')">
									<template #icon>
										<NcIconSvgWrapper :svg="IconMicrophoneOffOutline" :size="20" />
									</template>
								</NcButton>
							</template>
							<template #default>
								<p class="media-settings__device-error">
									{{ audioStreamErrorMessage }}
								</p>
							</template>
						</NcPopover>

						<!-- Video toggle -->
						<NcButton
							v-if="!videoStreamError"
							variant="tertiary"
							:title="videoButtonTitle"
							:aria-label="videoButtonTitle"
							:disabled="!videoPreviewAvailable"
							@click="toggleVideo">
							<template #icon>
								<IconVideo v-if="videoOn" :size="20" />
								<IconVideoOffOutline v-else :size="20" />
							</template>
						</NcButton>
						<NcPopover
							v-else
							:title="t('spreed', 'Show more info')"
							no-focus-trap>
							<template #trigger>
								<NcButton
									variant="error"
									:aria-label="t('spreed', 'Video is not available')">
									<template #icon>
										<IconVideoOffOutline :size="20" />
									</template>
								</NcButton>
							</template>
							<template #default>
								<p class="media-settings__device-error">
									{{ videoStreamErrorMessage }}
								</p>
							</template>
						</NcPopover>
					</div>
				</div>
				<div class="media-settings__settings">
					<!-- Tab panels -->
					<MediaSettingsTabs v-model:active="tabContent" :tabs="tabs">
						<template #tab-panel:devices>
							<MediaDevicesSelector
								kind="audioinput"
								:devices="devices"
								:device-id="audioInputId"
								@refresh="updateDevices"
								@update:device-id="handleAudioInputIdChange" />
							<MediaDevicesSelector
								kind="videoinput"
								:devices="devices"
								:device-id="videoInputId"
								@refresh="updateDevices"
								@update:device-id="handleVideoInputIdChange" />
							<MediaDevicesSelector
								v-if="audioOutputSupported"
								kind="audiooutput"
								:devices="devices"
								:device-id="audioOutputId"
								@refresh="updateDevices"
								@update:device-id="handleAudioOutputIdChange">
								<template #extra-action>
									<MediaDevicesSpeakerTest :disabled="audioStreamError" />
								</template>
							</MediaDevicesSelector>
						</template>

						<template #tab-panel:backgrounds>
							<VideoBackgroundEditor
								class="media-settings__tab"
								:token="token"
								:skip-blur-virtual-background="skipBlurVirtualBackground"
								@update-background="handleUpdateVirtualBackground" />
						</template>
					</MediaSettingsTabs>

					<!-- Guest display name setting-->
					<SetGuestUsername
						v-if="isGuest"
						compact
						@update="guestUserName = $event" />

					<!-- Moderator options before starting a call-->
					<NcCheckboxRadioSwitch
						v-if="showStartRecordingOption"
						v-model="isRecordingFromStart"
						class="checkbox">
						{{ t('spreed', 'Start recording immediately with the call') }}
					</NcCheckboxRadioSwitch>
					<!-- Notify call option-->
					<NcCheckboxRadioSwitch
						v-if="showNotifyCallOption"
						v-model="notifyCall"
						class="checkbox"
						@update:model-value="setNotifyCall">
						{{ t('spreed', 'Notify all participants about this call') }}
					</NcCheckboxRadioSwitch>

					<NcButton
						v-if="showUpdateChangesButton"
						class="action-button"
						@click="closeModalAndApplySettings">
						{{ isDeviceCheck ? t('spreed', 'Save') : t('spreed', 'Apply settings') }}
					</NcButton>
					<!-- Join call -->
					<CallButton
						v-else-if="isBeforeJoinCall"
						class="action-button"
						is-media-settings
						:is-recording-from-start="isRecordingFromStart"
						:disabled="disabledCallButton"
						:recording-consent-given="recordingConsentGiven"
						:silent-call="!notifyCall" />
				</div>
			</div>
		</div>
	</component>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { computed, h, markRaw, ref, useId } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import IconCogOutline from 'vue-material-design-icons/CogOutline.vue'
import IconReflectHorizontal from 'vue-material-design-icons/ReflectHorizontal.vue'
import IconVideo from 'vue-material-design-icons/Video.vue' // Filled for better indication
import IconVideoOffOutline from 'vue-material-design-icons/VideoOffOutline.vue'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import VideoBackground from '../CallView/shared/VideoBackground.vue'
import SetGuestUsername from '../SetGuestUsername.vue'
import CallButton from '../TopBar/CallButton.vue'
import VolumeIndicator from '../UIShared/VolumeIndicator.vue'
import MediaDevicesSelector from './MediaDevicesSelector.vue'
import MediaDevicesSpeakerTest from './MediaDevicesSpeakerTest.vue'
import MediaSettingsTabs from './MediaSettingsTabs.vue'
import VideoBackgroundEditor from './VideoBackgroundEditor.vue'
import IconMicrophoneOffOutline from '../../../img/material-icons/microphone-off-outline.svg?raw'
import IconBackground from '../../../img/material-icons/replace-background.svg?raw'
import { useDevices } from '../../composables/useDevices.js'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useIsInCall } from '../../composables/useIsInCall.js'
import { ATTENDEE, AVATAR, CALL, CONFIG, PARTICIPANT, VIRTUAL_BACKGROUND } from '../../constants.ts'
import BrowserStorage from '../../services/BrowserStorage.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useGuestNameStore } from '../../stores/guestName.js'
import { useSettingsStore } from '../../stores/settings.ts'
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
		NcIconSvgWrapper,
		NcModal,
		NcPopover,
		NcNoteCard,
		VideoBackground,
		VideoBackgroundEditor,
		VolumeIndicator,
		SetGuestUsername,
		// Icons
		IconReflectHorizontal,
		IconVideo,
		IconVideoOffOutline,
	},

	props: {
		recordingConsentGiven: {
			type: Boolean,
			default: false,
		},

		isDialog: {
			type: Boolean,
			default: true,
		},
	},

	emits: ['update:recordingConsentGiven'],

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
			subscribeToDevices,
			unsubscribeFromDevices,
			audioStreamError,
			videoStreamError,
			virtualBackground,
			registerVideoElement,
		} = useDevices()

		const isVirtualBackgroundAvailable = computed(() => virtualBackground.value?.isAvailable())

		const devicesTab = {
			id: 'devices',
			label: t('spreed', 'Devices'),
			icon: markRaw(IconCogOutline),
		}
		const backgroundsTab = {
			id: 'backgrounds',
			label: t('spreed', 'Backgrounds'),
			icon: markRaw(() => h(NcIconSvgWrapper, { svg: IconBackground })),
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
			subscribeToDevices,
			unsubscribeFromDevices,
			registerVideoElement,
			audioStreamError,
			videoStreamError,
			virtualBackground,
			tabs,
			dialogHeaderId,
			supportStartWithoutMedia,
			supportDefaultBlurVirtualBackground,
			actorStore: useActorStore(),
			token: useGetToken(),
			isMobile: useIsMobile(),
			IconMicrophoneOffOutline,
		}
	},

	data() {
		return {
			show: false,
			tabContent: 'devices',
			audioOn: undefined,
			videoOn: undefined,
			notifyCall: true,
			updatedBackground: undefined,
			audioDeviceStateChanged: false,
			videoDeviceStateChanged: false,
			isRecordingFromStart: false,
			isPublicShareAuthSidebar: false,
			isMirrored: false,
			skipBlurVirtualBackground: false,
			mediaLoading: false,
			isDeviceCheck: false,
			guestUserName: '',
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

		isGuest() {
			return !this.userId && this.actorStore.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
		},

		userId() {
			return this.actorStore.userId
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

		isBeforeJoinCall() {
			return !this.isInCall && !this.isInLobby && !this.isDeviceCheck
		},

		showRecordingWarning() {
			return this.isBeforeJoinCall && (this.isCurrentlyRecording || this.isRecordingConsentRequired)
		},

		showNotifyCallOption() {
			return !this.hasCall && !this.isPublicShareAuthSidebar
				&& this.isBeforeJoinCall
		},

		showStartRecordingOption() {
			return !this.hasCall && this.canModerateRecording && this.isBeforeJoinCall
		},

		showUpdateChangesButton() {
			return (this.isDeviceCheck
				|| this.isInCall)
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

		audioStreamErrorMessage() {
			if (!this.audioStreamError) {
				return null
			}

			if (this.audioStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.audioStreamError.message && this.audioStreamError.message.includes('Only secure origins')
			if ((this.audioStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.audioStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to microphone is only possible with HTTPS')
			}

			if (this.audioStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to microphone was denied')
			}

			return t('spreed', 'Error while accessing microphone')
		},

		videoStreamErrorMessage() {
			if (!this.videoStreamError) {
				return null
			}

			if (this.videoStreamError.name === 'NotSupportedError' && !window.RTCPeerConnection) {
				return t('spreed', 'Calls are not supported in your browser')
			}

			// In newer browser versions MediaDevicesManager is not supported in
			// insecure contexts; in older browser versions it is, but getting
			// the user media fails with "NotAllowedError".
			const isInsecureContext = 'isSecureContext' in window && !window.isSecureContext
			const isInsecureContextAccordingToErrorMessage = this.videoStreamError.message && this.videoStreamError.message.includes('Only secure origins')
			if ((this.videoStreamError.name === 'NotSupportedError' && isInsecureContext)
				|| (this.videoStreamError.name === 'NotAllowedError' && isInsecureContextAccordingToErrorMessage)) {
				return t('spreed', 'Access to camera is only possible with HTTPS')
			}

			if (this.videoStreamError.name === 'NotAllowedError') {
				return t('spreed', 'Access to camera was denied')
			}

			return t('spreed', 'Error while accessing camera')
		},

		disabledCallButton() {
			return (this.isRecordingConsentRequired && !this.recordingConsentGiven)
				|| (this.isGuest && !this.guestUserName.length)
		},

		forceShowMediaSettings() {
			return !this.isInCall && this.isGuest && this.isDialog
		},
	},

	watch: {
		show(newValue) {
			if (newValue) {
				this.subscribeToDevices()
				this.$nextTick(() => {
					this.registerVideoElement(this.video)
				})

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
				this.notifyCall = BrowserStorage.getItem('silentCall_' + this.token) !== 'true'

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
			} else {
				this.unsubscribeFromDevices()
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

		hasCall(value) {
			if (value && this.forceShowMediaSettings) {
				this.showMediaSettings()
			}
		},
	},

	beforeMount() {
		subscribe('talk:media-settings:show', this.showMediaSettings)
		subscribe('talk:media-settings:hide', this.closeModalAndApplySettings)
	},

	mounted() {
		if (!this.isDialog) {
			this.showMediaSettings()
		}
	},

	beforeUnmount() {
		unsubscribe('talk:media-settings:show', this.showMediaSettings)
		unsubscribe('talk:media-settings:hide', this.closeModalAndApplySettings)

		if (!this.isDialog) {
			this.close()
		}
	},

	methods: {
		t,
		showMediaSettings(page) {
			this.show = true
			if (page === 'video-verification') {
				this.isPublicShareAuthSidebar = true
			}

			if (page === 'device-check') {
				this.isDeviceCheck = true
				this.tabContent = 'devices'
			} else if (page === 'backgrounds') {
				this.isDeviceCheck = true
				this.tabContent = 'backgrounds'
			}
		},

		close() {
			this.show = false
			this.updatedBackground = undefined
			this.audioDeviceStateChanged = false
			this.videoDeviceStateChanged = false
			this.isPublicShareAuthSidebar = false
			this.isDeviceCheck = false
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
			this.audioDeviceStateChanged = true
		},

		toggleVideo() {
			if (!this.videoOn) {
				BrowserStorage.removeItem('videoDisabled_' + this.token)
				this.videoOn = true
			} else {
				BrowserStorage.setItem('videoDisabled_' + this.token, 'true')
				this.videoOn = false
			}
			this.videoDeviceStateChanged = true
		},

		setNotifyCall(value) {
			if (!value) {
				BrowserStorage.setItem('silentCall_' + this.token, 'true')
			} else {
				BrowserStorage.removeItem('silentCall_' + this.token)
			}
		},

		closeModalAndApplySettings() {
			if (this.updatedBackground) {
				this.handleUpdateBackground(this.updatedBackground)
			}

			if (this.audioDeviceStateChanged && this.isInCall) {
				if (this.audioOn) {
					localMediaModel.enableAudio()
				} else {
					localMediaModel.disableAudio()
				}
			}

			if (this.videoDeviceStateChanged && this.isInCall) {
				if (this.videoOn) {
					localMediaModel.enableVideo()
				} else {
					localMediaModel.disableVideo()
				}
			}

			this.close()
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

		setRecordingConsentGiven(value) {
			this.$emit('update:recordingConsentGiven', value)
		},

		handleAudioInputIdChange(audioInputId) {
			this.audioInputId = audioInputId
			this.audioDeviceStateChanged = true
			this.updatePreferences('audioinput')
		},

		handleAudioOutputIdChange(audioOutputId) {
			this.audioOutputId = audioOutputId
			this.updatePreferences('audiooutput')
		},

		handleVideoInputIdChange(videoInputId) {
			this.videoInputId = videoInputId
			this.videoDeviceStateChanged = true
			this.updatePreferences('videoinput')
		},

		async toggleStartWithoutMedia(value) {
			this.mediaLoading = true
			try {
				await this.settingsStore.updateStartWithoutMedia(value)
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

	&__preview {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: var(--border-radius-element, var(--border-radius-large));
		background-color: var(--color-loading-dark);
		aspect-ratio: 4/3;
		margin-inline-end: var(--default-grid-baseline);
	}

	&__preview > &__preview-mirror {
		position: absolute;
		top: calc(var(--default-grid-baseline) * 2);
		inset-inline-end: calc(var(--default-grid-baseline) * 2);
	}

	&__toggles {
		display: flex;
		gap: calc(0.5 * var(--default-grid-baseline));
		padding: calc(0.5 * var(--default-grid-baseline));
		position: absolute;
		bottom: calc(var(--default-grid-baseline) * -2);
		inset-inline-start: 50%;
		transform: translateX(-50%);
		background: var(--color-main-background);
		border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
		box-shadow: 0 0 var(--default-grid-baseline) var(--color-box-shadow);
		z-index: 2;
	}

	&__content {
		display: grid;
		grid-template-columns: 4fr 3fr;
		gap: calc(var(--default-grid-baseline) * 2);

		&--mobile {
			grid-template-columns: 1fr;
			gap: calc(var(--default-grid-baseline) * 4);
		}
	}

	&__settings {
		max-width: 450px; // 1/2 large modal width
		display: flex;
		flex-direction: column;
	}

	&__guest {
		margin-top: var(--default-grid-baseline);
	}

	&__device-error {
		padding: calc(var(--default-grid-baseline) * 2);
	}
}

.preview {
	&__video {
		max-width: 100%;
		object-fit: contain;
		max-height: 100%;
		border-radius: var(--border-radius-element);
		position: absolute;
		top: 0;
		inset-inline-start: 0;

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

.action-button {
	margin-inline: auto;
	margin-top: var(--default-grid-baseline);
}

.checkbox {
	margin-block: var(--default-grid-baseline);
	&--warning {
		&:focus-within :deep(.checkbox-radio-switch__label),
		& :deep(.checkbox-radio-switch__label:hover) {
			background-color: var(--note-background) !important;
		}
	}
}

.media-settings__recording-warning--mobile {
	max-width: 450px;
}

// Override NcModal styles for large horizontal layout
:deep(.modal-wrapper--large > .modal-container) {
	width: unset !important;
}

</style>

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
	<NcModal v-if="modal"
		class="talk-modal"
		size="small"
		:container="container"
		@close="closeModal">
		<div class="media-settings">
			<h2 class="media-settings__title">
				{{ t('spreed', 'Media settings') }}
			</h2>
			<!-- Preview -->
			<div class="media-settings__preview">
				<!-- eslint-disable-next-line -->
				<video v-show="showVideo"
					ref="video"
					class="preview__video"
					disable-picture-in-picture="true"
					tabindex="-1" />
				<div v-show="!showVideo"
					class="preview__novideo">
					<VideoBackground :display-name="displayName"
						:user="userId" />
					<NcAvatar v-if="userId"
						:size="128"
						:disable-menu="true"
						:disable-tooltip="true"
						:show-user-status="false"
						:user="userId"
						:display-name="displayName" />
					<div v-if="!userId"
						class="avatar avatar-128px guest">
						{{ firstLetterOfGuestName }}
					</div>
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
								:volume-threshold="volumeThreshold"
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
				<NcButton :type="showBackgroundEditor ? 'secondary' : 'tertiary'"
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
					@update:deviceId="audioInputId = $event" />
				<MediaDevicesSelector kind="videoinput"
					:devices="devices"
					:device-id="videoInputId"
					@update:deviceId="videoInputId = $event" />
			</div>

			<!-- Background selection -->
			<VideoBackgroundEditor v-if="showBackgroundEditor"
				:virtual-background="virtualBackground"
				:token="token"
				@update-background="handleUpdateBackground" />

			<!-- "Always show" setting -->
			<NcCheckboxRadioSwitch :checked.sync="showMediaSettings"
				class="checkbox">
				{{ t('spreed', 'Always show preview for this conversation') }}
			</NcCheckboxRadioSwitch>

			<!-- Recording warning -->
			<NcNoteCard v-if="isStartingRecording || isRecording"
				type="warning">
				<p>{{ t('spreed', 'The call is being recorded.') }}</p>
			</NcNoteCard>

			<!-- buttons bar at the bottom -->
			<div class="media-settings__call-buttons">
				<!-- Silent call -->
				<NcActions v-if="showSilentCallOption"
					:container="container"
					:force-menu="true">
					<template v-if="!silentCall">
						<NcActionButton :close-after-click="true"
							icon="icon-upload"
							:title="t('spreed', 'Call without notification')"
							@click="silentCall= true">
							{{ t('spreed', 'The conversation participants will not be notified about this call') }}
							<BellOff slot="icon"
								:size="16" />
						</NcActionButton>
					</template>
					<template v-else>
						<NcActionButton :close-after-click="true"
							icon="icon-upload"
							:title="t('spreed', 'Normal call')"
							@click="silentCall= false">
							{{ t('spreed', 'The conversation participants will be notified about this call') }}
							<Bell slot="icon"
								:size="16" />
						</NcActionButton>
					</template>
				</NcActions>
				<!-- Join call -->
				<CallButton v-if="!isInCall"
					class="call-button"
					:force-join-call="true"
					:silent-call="silentCall" />
				<NcButton v-else @click="closeModal">
					{{ t('spreed', 'Done') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import Bell from 'vue-material-design-icons/Bell.vue'
import BellOff from 'vue-material-design-icons/BellOff.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Creation from 'vue-material-design-icons/Creation.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcNoteCard from '@nextcloud/vue/dist/Components/NcNoteCard.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import VideoBackground from '../CallView/shared/VideoBackground.vue'
import MediaDevicesSelector from '../MediaDevicesSelector.vue'
import CallButton from '../TopBar/CallButton.vue'
import VolumeIndicator from '../VolumeIndicator/VolumeIndicator.vue'
import VideoBackgroundEditor from './VideoBackgroundEditor.vue'

import { useIsInCall } from '../../composables/useIsInCall.js'
import { CALL, VIRTUAL_BACKGROUND } from '../../constants.js'
import { devices } from '../../mixins/devices.js'
import isInLobby from '../../mixins/isInLobby.js'
import BrowserStorage from '../../services/BrowserStorage.js'
import { localMediaModel } from '../../utils/webrtc/index.js'

export default {
	name: 'MediaSettings',

	directives: {
		Tooltip,
	},

	components: {
		Bell,
		BellOff,
		CallButton,
		Cog,
		Creation,
		NcActionButton,
		NcActions,
		NcAvatar,
		NcButton,
		NcCheckboxRadioSwitch,
		NcModal,
		NcNoteCard,
		MediaDevicesSelector,
		VideoBackground,
		VideoIcon,
		VideoOff,
		VolumeIndicator,
		VideoBackgroundEditor,
	},

	mixins: [devices, isInLobby],

	setup() {
		const isInCall = useIsInCall()
		return { isInCall }
	},

	data() {
		return {
			model: localMediaModel,
			modal: false,
			tabContent: 'none',
			audioOn: undefined,
			videoOn: undefined,
			showMediaSettings: true,
			silentCall: false,
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
			return this.$store.getters.getGuestName(
				this.$store.getters.getToken(),
				this.$store.getters.getActorId(),
			)
		},

		firstLetterOfGuestName() {
			const customName = this.guestName !== t('spreed', 'Guest') ? this.guestName : '?'
			return customName.charAt(0)
		},

		userId() {
			return this.$store.getters.getUserId()
		},

		token() {
			return this.$store.getters.getToken()
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

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		hasCall() {
			return this.conversation.hasCall || this.conversation.hasCallOverwrittenByChat
		},

		isStartingRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO_STARTING
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO_STARTING
		},

		isRecording() {
			return this.conversation.callRecording === CALL.RECORDING.VIDEO
				|| this.conversation.callRecording === CALL.RECORDING.AUDIO
		},

		showSilentCallOption() {
			return !(this.hasCall && !this.isInLobby)
		},

		showDeviceSelection() {
			return this.tabContent === 'devices'
		},

		showBackgroundEditor() {
			return this.tabContent === 'backgrounds'
		},
	},

	watch: {
		modal(newValue) {
			if (newValue) {
				this.audioOn = !localStorage.getItem('audioDisabled_' + this.token)
				this.videoOn = !localStorage.getItem('videoDisabled_' + this.token)

				// Set virtual background depending on localstorage's settings
				if (localStorage.getItem('virtualBackgroundEnabled_' + this.token) === 'true') {
					if (localStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR) {
						this.blurVirtualBackground()
					} else if (localStorage.getItem('virtualBackgroundType_' + this.token) === VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE) {
						this.setVirtualBackgroundImage(localStorage.getItem('virtualBackgroundUrl_' + this.token))
					}
				}

				this.initializeDevicesMixin()
			} else {
				this.stopDevicesMixin()
			}
		},

		showMediaSettings(newValue) {
			if (newValue) {
				BrowserStorage.setItem('showMediaSettings' + this.token, 'true')
			} else {
				BrowserStorage.setItem('showMediaSettings' + this.token, 'false')
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
	},

	mounted() {
		subscribe('talk:media-settings:show', this.showModal)
		subscribe('talk:media-settings:hide', this.closeModal)
	},

	beforeDestroy() {
		unsubscribe('talk:media-settings:show', this.showModal)
		unsubscribe('talk:media-settings:hide', this.closeModal)
	},

	methods: {
		showModal() {
			this.modal = true
		},

		closeModal() {
			this.modal = false
		},

		toggleAudio() {
			if (!this.audioOn) {
				localStorage.removeItem('audioDisabled_' + this.token)
				this.audioOn = true
			} else {
				localStorage.setItem('audioDisabled_' + this.token, 'true')
				this.audioOn = false
			}
		},

		toggleVideo() {
			if (!this.videoOn) {
				localStorage.removeItem('videoDisabled_' + this.token)
				this.videoOn = true
			} else {
				localStorage.setItem('videoDisabled_' + this.token, 'true')
				this.videoOn = false
			}
		},

		handleUpdateBackground(background) {
			if (background === 'none') {
				this.clearVirtualBackground()
				this.clearBackground()
			} else if (background === 'blur') {
				this.blurVirtualBackground()
				this.blurBackground()
			} else {
				this.setVirtualBackgroundImage(background)
				this.setBackgroundImage(background)
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
				localStorage.setItem('virtualBackgroundEnabled_' + this.token, 'false')
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
				localStorage.setItem('virtualBackgroundEnabled_' + this.token, 'true')
				localStorage.setItem('virtualBackgroundType_' + this.token, VIRTUAL_BACKGROUND.BACKGROUND_TYPE.BLUR)
				localStorage.setItem('virtualBackgroundBlurStrength_' + this.token, VIRTUAL_BACKGROUND.BLUR_STRENGTH.DEFAULT)
			}
		},

		/**
		 * Sets an image as background in virtualBackground: the background used in the MediaSettings preview
		 *
		 * @param background
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
		 * @param background the image's url
		 */
		setBackgroundImage(background) {
			if (this.isInCall) {
				localMediaModel.enableVirtualBackground()
				localMediaModel.setVirtualBackgroundImage(background)
			} else {
				localStorage.setItem('virtualBackgroundEnabled_' + this.token, 'true')
				localStorage.setItem('virtualBackgroundType_' + this.token, VIRTUAL_BACKGROUND.BACKGROUND_TYPE.IMAGE)
				localStorage.setItem('virtualBackgroundUrl_' + this.token, background)
			}
		},

		toggleTab(tab) {
			if (this.tabContent !== tab) {
				this.tabContent = tab
			} else {
				this.tabContent = 'none'
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/variables';
@import '../../assets/avatar';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.media-settings {
	padding: 20px;
	background-color: var(--color-main-background);
	overflow-y: auto;
	overflow-x: hidden;
	margin: auto;
	&__title {
		text-align: center;
	}
	&__preview {
		position: relative;
		margin: 0 auto 12px auto;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		border-radius: 12px;
		height: 256px;
		width: 342px;
		background-color: var(--color-loading-dark);
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
		height: $clickable-area;
		display: flex;
		justify-content: space-evenly;
		align-items: center;
	}

	&__call-buttons {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 4px;
	}
}

.preview {
	&__video {
		max-width: 100%;
		object-fit: contain;
		max-height: 100%;
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
	margin: 14px;
}

:deep(.modal-container) {
	display: flex !important;
}
</style>

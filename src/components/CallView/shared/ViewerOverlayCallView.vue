<!--
  - @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
  -
  - @author Grigorii Shartsev <me@shgk.me>
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
	<div class="viewer-overlay">
		<div class="viewer-overlay__collapse" :class="{ collapsed: isCollapsed }">
			<NcButton type="tertiary"
				class="viewer-overlay__button"
				:aria-label="isCollapsed ? t('spreed', 'Collapse') : t('spreed', 'Expand')"
				@click.stop="isCollapsed = !isCollapsed">
				<template #icon>
					<ChevronDown v-if="!isCollapsed" :size="20" />
					<ChevronUp v-else :size="20" />
				</template>
			</NcButton>
		</div>

		<Transition name="slide-down">
			<div v-show="!isCollapsed" class="viewer-overlay__video-container">
				<div class="video-overlay__top-bar">
					<NcButton type="tertiary"
						class="viewer-overlay__button"
						:aria-label="t('spreed', 'Expand')"
						@click.stop="maximize">
						<template #icon>
							<ArrowExpand :size="20" />
						</template>
					</NcButton>
				</div>

				<LocalVideo v-if="localModel.attributes.videoEnabled"
					class="viewer-overlay__local-video"
					:token="token"
					:show-controls="false"
					:local-media-model="localModel"
					:local-call-participant-model="localCallParticipantModel"
					un-selectable />

				<VideoVue class="viewer-overlay__video"
					:token="token"
					:model="model"
					:shared-data="sharedData"
					is-grid
					un-selectable
					@click-video="maximize">
					<template #bottom-bar>
						<div class="viewer-overlay__bottom-bar">
							<NcButton v-tooltip="audioButtonTooltip"
								type="tertiary"
								class="viewer-overlay__button"
								:aria-label="audioButtonAriaLabel"
								:class="{
									'audio-enabled': isAudioAllowed && localModel.attributes.audioAvailable && localModel.attributes.audioEnabled,
									'no-audio-available': !isAudioAllowed || !localModel.attributes.audioAvailable,
								}"
								@click.stop="toggleAudio">
								<template #icon>
									<VolumeIndicator :audio-preview-available="localModel.attributes.audioAvailable"
										:audio-enabled="showMicrophoneOn"
										:current-volume="localModel.attributes.currentVolume"
										:volume-threshold="localModel.attributes.volumeThreshold"
										primary-color="currentColor"
										overlay-muted-color="#888888" />
								</template>
							</NcButton>
							<NcButton v-tooltip="videoButtonTooltip"
								type="tertiary"
								class="viewer-overlay__button"
								:aria-label="videoButtonAriaLabel"
								:class="{
									'video-enabled': isVideoAllowed && localModel.attributes.videoAvailable && localModel.attributes.videoEnabled,
									'no-video-available': !isVideoAllowed || !localModel.attributes.videoAvailable,
								}"
								@click.stop="toggleVideo">
								<template #icon>
									<VideoIcon v-if="showVideoOn" :size="20" />
									<VideoOff v-else :size="20" />
								</template>
							</NcButton>
						</div>
					</template>
				</VideoVue>
			</div>
		</Transition>
	</div>
</template>

<script>
import ArrowExpand from 'vue-material-design-icons/ArrowExpand.vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit } from '@nextcloud/event-bus'

import { NcButton, Tooltip } from '@nextcloud/vue'

import VolumeIndicator from '../../VolumeIndicator/VolumeIndicator.vue'
import LocalVideo from './LocalVideo.vue'
import VideoVue from './VideoVue.vue'

import { PARTICIPANT } from '../../../constants.js'
import { localCallParticipantModel, localMediaModel } from '../../../utils/webrtc/index.js'

export default {
	name: 'ViewerOverlayCallView',

	components: {
		LocalVideo,
		ChevronUp,
		ChevronDown,
		VolumeIndicator,
		NcButton,
		VideoOff,
		VideoIcon,
		VideoVue,
		ArrowExpand,
	},

	directives: {
		tooltip: Tooltip,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		model: {
			type: Object,
			required: true,
		},

		sharedData: {
			type: Object,
			required: true,
		},

		localModel: {
			type: Object,
			required: false,
			default: () => localMediaModel,
		},

		localCallParticipantModel: {
			type: Object,
			required: false,
			default: () => localCallParticipantModel,
		},
	},

	setup() {
		return {
			// TODO: temp for refactoring to make the code similar to the original
			disableKeyboardShortcuts: true,
		}
	},

	data() {
		return {
			isCollapsed: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		// COPY FROM LOCAL VIDEO CONTROLS

		isAudioAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_AUDIO
		},

		isVideoAllowed() {
			return this.conversation.permissions & PARTICIPANT.PERMISSIONS.PUBLISH_VIDEO
		},

		videoButtonAriaLabel() {
			if (!this.localModel.attributes.videoAvailable) {
				return t('spreed', 'No video. Click to select device')
			}

			if (this.localModel.attributes.videoEnabled) {
				return t('spreed', 'Disable video')
			}

			if (!this.localModel.getWebRtc() || !this.localModel.getWebRtc().connection || this.localModel.getWebRtc().connection.getSendVideoIfAvailable()) {
				return t('spreed', 'Enable video')
			}

			return t('spreed', 'Enable video. Your connection will be briefly interrupted when enabling the video for the first time')
		},

		videoButtonTooltip() {
			if (!this.isVideoAllowed) {
				return t('spreed', 'You are not allowed to enable video')
			}

			if (!this.localModel.attributes.videoAvailable) {
				return t('spreed', 'No video. Click to select device')
			}

			if (this.localModel.attributes.videoEnabled) {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Disable video')
					: t('spreed', 'Disable video (V)')
			}

			if (!this.localModel.getWebRtc() || !this.localModel.getWebRtc().connection || this.localModel.getWebRtc().connection.getSendVideoIfAvailable()) {
				return this.disableKeyboardShortcuts
					? t('spreed', 'Enable video')
					: t('spreed', 'Enable video (V)')
			}

			return this.disableKeyboardShortcuts
				? t('spreed', 'Enable video - Your connection will be briefly interrupted when enabling the video for the first time')
				: t('spreed', 'Enable video (V) - Your connection will be briefly interrupted when enabling the video for the first time')
		},

		showVideoOn() {
			return this.localModel.attributes.videoAvailable && this.localModel.attributes.videoEnabled
		},

		showMicrophoneOn() {
			return this.localModel.attributes.audioAvailable && this.localModel.attributes.audioEnabled
		},

		audioButtonTooltip() {
			if (!this.isAudioAllowed) {
				return t('spreed', 'You are not allowed to enable audio')
			}

			if (!this.localModel.attributes.audioAvailable) {
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
			if (this.localModel.attributes.audioEnabled) {
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
			if (!this.localModel.attributes.audioAvailable) {
				return t('spreed', 'No audio. Click to select device')
			}

			return this.localModel.attributes.audioEnabled
				? t('spreed', 'Mute audio')
				: t('spreed', 'Unmute audio')
		},
		// END COPY FROM LOCAL VIDEO CONTROLS

	},

	methods: {
		toggleVideo() {
			if (!this.localModel.attributes.videoAvailable) {
				emit('show-settings', {})
				return
			}

			if (this.localModel.attributes.videoEnabled) {
				this.localModel.disableVideo()
			} else {
				this.localModel.enableVideo()
			}
		},

		toggleAudio() {
			if (!this.localModel.attributes.audioAvailable) {
				emit('show-settings', {})
				return
			}

			if (this.localModel.attributes.audioEnabled) {
				this.localModel.disableAudio()
			} else {
				this.localModel.enableAudio()
			}
		},

		maximize() {
			if (OCA.Viewer) {
				OCA.Viewer.close()
			}
			this.$store.dispatch('setCallViewMode', { isViewerOverlay: false })
		},

	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables';

.viewer-overlay {
	--aspect-ratio: calc(3 / 4);
	--width: 20vw;
	--min-width: 250px;
	--max-width: 400px;
	position: absolute;
	bottom: 8px;
	right: 8px;
	width: var(--width);
	min-width: var(--min-width);
	max-width: var(--max-width);
	min-height: calc(var(--default-clickable-area) + 8px);
	z-index: 11000;
}

.viewer-overlay__collapse {
	position: absolute;
	top: 8px;
	right: 8px;
	z-index: 100;
}

.viewer-overlay__button {
	background-color: rgb(var(--color-main-background-rgb), 0.7) !important;
	&:active,
	&:hover,
	&:focus {
		background-color: rgb(var(--color-main-background-rgb), 0.9) !important;
	}
}

.video-overlay__top-bar {
	position: absolute;
	top: 8px;
	left: 8px;
	z-index: 100;
}

.viewer-overlay__bottom-bar {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 12px 8px 12px;
	z-index: 1;
}

.viewer-overlay__video-container {
	width: 100%;
	height: calc(var(--width) * var(--aspect-ratio));
	min-height: calc(var(--min-width) * var(--aspect-ratio));
	max-height: calc(var(--max-width) * var(--aspect-ratio));
	/* Note: because of transition it always has position absolute on animation */
	bottom: 0;
	right: 0;
}

.viewer-overlay__local-video {
	position: absolute;
	bottom: 10%;
	right: 5%;
	width: 25%;
	height: 25%;
	overflow: hidden;
}

.viewer-overlay__video {
	position: relative;
	height: 100%;
}
</style>

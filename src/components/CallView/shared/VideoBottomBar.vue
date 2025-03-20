<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
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
	<div class="wrapper" :class="{'wrapper--big': isBig}">
		<TransitionWrapper name="fade">
			<div v-if="showRaiseHandIndicator" class="status-indicator raiseHandIndicator">
				<HandBackLeft :size="18" fill-color="#ffffff" />
			</div>
		</TransitionWrapper>

		<div v-if="!isSidebar" class="bottom-bar">
			<TransitionWrapper name="fade">
				<div v-show="showParticipantName"
					class="participant-name"
					:class="{
						'participant-name--active': isCurrentlyActive,
						'participant-name--has-shadow': hasShadow,
					}">
					{{ participantName }}
				</div>
			</TransitionWrapper>

			<TransitionWrapper v-if="!isScreen"
				v-show="showVideoOverlay"
				class="media-indicators"
				name="fade"
				group>
				<NcButton v-if="showAudioIndicator"
					key="audioIndicator"
					v-tooltip="audioButtonTooltip"
					:aria-label="audioButtonTooltip"
					class="audioIndicator"
					type="tertiary-no-background"
					:disabled="isAudioButtonDisabled"
					@click.stop="forceMute">
					<template #icon>
						<Microphone v-if="model.attributes.audioAvailable" :size="20" fill-color="#ffffff" />
						<MicrophoneOff v-else :size="20" fill-color="#ffffff" />
					</template>
				</NcButton>

				<NcButton v-if="showVideoIndicator"
					key="videoIndicator"
					v-tooltip="videoButtonTooltip"
					:aria-label="videoButtonTooltip"
					class="videoIndicator"
					type="tertiary-no-background"
					@click.stop="toggleVideo">
					<template #icon>
						<VideoIcon v-if="isRemoteVideoEnabled" :size="20" fill-color="#ffffff" />
						<VideoOff v-else :size="20" fill-color="#ffffff" />
					</template>
				</NcButton>

				<NcButton v-if="showScreenSharingIndicator"
					key="screenSharingIndicator"
					v-tooltip="t('spreed', 'Show screen')"
					:aria-label="t('spreed', 'Show screen')"
					class="screenSharingIndicator"
					:class="{'screen-visible': sharedData.screenVisible}"
					type="tertiary-no-background"
					@click.stop="switchToScreen">
					<template #icon>
						<Monitor :size="20" fill-color="#ffffff" />
					</template>
				</NcButton>

				<div v-if="connectionStateFailedNoRestart"
					key="iceFailedIndicator"
					class="status-indicator iceFailedIndicator">
					<AlertCircle :size="20" fill-color="#ffffff" />
				</div>
			</TransitionWrapper>

			<NcButton v-if="showStopFollowingButton"
				class="following-button"
				type="tertiary"
				@click="handleStopFollowing">
				{{ t('spreed', 'Stop following') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import Monitor from 'vue-material-design-icons/Monitor.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'
import VideoOff from 'vue-material-design-icons/VideoOff.vue'

import { emit } from '@nextcloud/event-bus'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'

import { PARTICIPANT } from '../../../constants.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

export default {
	name: 'VideoBottomBar',

	components: {
		AlertCircle,
		HandBackLeft,
		Microphone,
		MicrophoneOff,
		Monitor,
		NcButton,
		TransitionWrapper,
		VideoIcon,
		VideoOff,
	},

	inheritAttrs: false,

	props: {
		token: {
			type: String,
			required: true,
		},
		isSidebar: {
			type: Boolean,
			default: false,
		},
		hasShadow: {
			type: Boolean,
			default: false,
		},
		isBig: {
			type: Boolean,
			default: false,
		},
		participantName: {
			type: String,
			default: '',
		},
		showVideoOverlay: {
			type: Boolean,
			default: true,
		},
		model: {
			type: Object,
			required: true,
		},
		sharedData: {
			type: Object,
			required: true,
		},
		// True if the bottom bar is used in the screen component
		isScreen: {
			type: Boolean,
			default: false,
		},
		// The current promoted participant
		isPromoted: {
			type: Boolean,
			default: false,
		},
		// Is the current selected participant
		isSelected: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		connectionStateFailedNoRestart() {
			return this.model.attributes.connectionState === ConnectionState.FAILED_NO_RESTART
		},

		// Common indicators
		showRaiseHandIndicator() {
			return !this.connectionStateFailedNoRestart && this.model.attributes.raisedHand.state
		},
		showStopFollowingButton() {
			return this.isBig && this.$store.getters.selectedVideoPeerId !== null
		},

		// Audio indicator
		showAudioIndicator() {
			return !this.connectionStateFailedNoRestart && !this.isAudioButtonHidden
		},
		isAudioButtonHidden() {
			return this.model.attributes.audioAvailable && !this.canFullModerate
		},
		isAudioButtonDisabled() {
			return !this.model.attributes.audioAvailable || !this.canFullModerate
		},
		audioButtonTooltip() {
			return this.model.attributes.audioAvailable
				? t('spreed', 'Mute')
				: t('spreed', 'Muted')
		},

		// Video indicator
		showVideoIndicator() {
			return !this.connectionStateFailedNoRestart && this.model.attributes.videoAvailable
		},
		isRemoteVideoEnabled() {
			return this.sharedData.remoteVideoBlocker?.isVideoEnabled()
		},
		isRemoteVideoBlocked() {
			return this.sharedData.remoteVideoBlocker && !this.sharedData.remoteVideoBlocker.isVideoEnabled()
		},
		videoButtonTooltip() {
			return this.isRemoteVideoEnabled
				? t('spreed', 'Disable video')
				: t('spreed', 'Enable video')
		},

		// ScreenSharing indicator
		showScreenSharingIndicator() {
			return !this.connectionStateFailedNoRestart && this.model.attributes.screen
		},

		// Name indicator
		isCurrentlyActive() {
			return this.isSelected || this.model.attributes.speaking
		},
		showParticipantName() {
			return !this.model.attributes.videoAvailable || this.isRemoteVideoBlocked
				|| this.showVideoOverlay || this.isPromoted || this.isCurrentlyActive
		},

		// Moderator rights
		participantType() {
			return this.$store.getters.conversation(this.token)?.participantType
				|| (this.$store.getters.getUserId() !== null
					? PARTICIPANT.TYPE.USER
					: PARTICIPANT.TYPE.GUEST)
		},
		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},
	},

	methods: {
		forceMute() {
			this.model.forceMute()
		},

		toggleVideo() {
			this.sharedData.remoteVideoBlocker.setVideoEnabled(!this.isRemoteVideoEnabled)
		},

		switchToScreen() {
			if (!this.sharedData.screenVisible || !this.isBig) {
				emit('switch-screen-to-id', this.model.attributes.peerId)
			}
		},

		handleStopFollowing() {
			this.$store.dispatch('stopPresentation')
			this.$store.dispatch('selectedVideoPeerId', null)
		},
	},
}
</script>

<style lang="scss" scoped>
.wrapper {
	display: flex;
	align-items: center;
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 12px 8px 12px;
	z-index: 1;

	&--big {
		justify-content: center;
		& .bottom-bar {
			width: unset;
		}
		& .participant-name {
			margin-right: 0;
		}
	}
}

.bottom-bar {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	height: 44px;

	& .media-indicators {
		display: flex;
	}

	& .following-button {
		opacity: 0.8;
		background-color: var(--color-background-dark);

		&:hover,
		&:focus {
			opacity: 1;
		}
	}
}

.participant-name {
	color: white;
	margin: 0 auto 0 8px;
	position: relative;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
	&--active {
		font-weight: bold;
	}
	&--has-shadow {
		text-shadow: 0 0 4px rgba(0, 0, 0, .8);
	}
}

.status-indicator {
	width: 44px;
	height: 44px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.iceFailedIndicator {
	opacity: .8 !important;
}

.audioIndicator[disabled],
.videoIndicator {
	opacity: .7;
}

.videoIndicator {
	&:hover,
	&:focus {
		opacity: 1;
	}
}

</style>

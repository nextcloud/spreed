<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="wrapper"
		:class="{ 'wrapper--big': isBig }"
		@mouseover.stop="mouseover = true"
		@mouseleave.stop="mouseover = false">
		<TransitionWrapper name="fade">
			<div v-if="showRaiseHandIndicator" class="status-indicator raiseHandIndicator">
				<IconHandBackLeftOutline :size="18" fill-color="#ffffff" />
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
					:title="audioButtonTitle"
					:aria-label="audioButtonTitle"
					class="audioIndicator"
					variant="tertiary-no-background"
					:disabled="isAudioButtonDisabled"
					@click.stop="forceMute">
					<template #icon>
						<IconMicrophoneOutline v-if="model.attributes.audioAvailable" :size="20" />
						<NcIconSvgWrapper v-else :svg="IconMicrophoneOffOutline" :size="20" />
					</template>
				</NcButton>

				<NcButton v-if="showVideoIndicator"
					:title="videoButtonTitle"
					:aria-label="videoButtonTitle"
					class="videoIndicator"
					variant="tertiary-no-background"
					@click.stop="toggleVideo">
					<template #icon>
						<IconVideoOutline v-if="isRemoteVideoEnabled" :size="20" />
						<IconVideoOffOutline v-else :size="20" />
					</template>
				</NcButton>

				<NcButton v-if="showScreenSharingIndicator"
					:title="t('spreed', 'Show screen')"
					:aria-label="t('spreed', 'Show screen')"
					class="screenSharingIndicator"
					:class="{ 'screen-visible': sharedData.screenVisible }"
					variant="tertiary-no-background"
					@click.stop="switchToScreen">
					<template #icon>
						<IconMonitor :size="20" />
					</template>
				</NcButton>

				<div v-if="connectionStateFailedNoRestart"
					class="status-indicator iceFailedIndicator">
					<IconAlertCircleOutline :size="20" />
				</div>
			</TransitionWrapper>

			<NcButton v-if="showStopFollowingButton"
				class="following-button"
				variant="tertiary"
				@click="handleStopFollowing">
				{{ t('spreed', 'Stop following') }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import IconAlertCircleOutline from 'vue-material-design-icons/AlertCircleOutline.vue'
import IconHandBackLeftOutline from 'vue-material-design-icons/HandBackLeftOutline.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconMonitor from 'vue-material-design-icons/Monitor.vue'
import IconVideoOffOutline from 'vue-material-design-icons/VideoOffOutline.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import IconMicrophoneOffOutline from '../../../../img/material-icons/microphone-off-outline.svg?raw'
import { PARTICIPANT } from '../../../constants.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

export default {
	name: 'VideoBottomBar',

	components: {
		IconAlertCircleOutline,
		IconHandBackLeftOutline,
		IconMicrophoneOutline,
		IconMonitor,
		IconVideoOutline,
		IconVideoOffOutline,
		NcButton,
		NcIconSvgWrapper,
		TransitionWrapper,
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

	emits: ['bottomBarHover'],

	setup() {
		return {
			IconMicrophoneOffOutline,
			callViewStore: useCallViewStore(),
			actorStore: useActorStore(),
		}
	},

	data() {
		return {
			mouseover: false,
		}
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
			return this.isBig && this.callViewStore.selectedVideoPeerId !== null
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

		audioButtonTitle() {
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

		videoButtonTitle() {
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
				|| (this.actorStore.isLoggedIn
					? PARTICIPANT.TYPE.USER
					: PARTICIPANT.TYPE.GUEST)
		},

		canFullModerate() {
			return this.participantType === PARTICIPANT.TYPE.OWNER || this.participantType === PARTICIPANT.TYPE.MODERATOR
		},
	},

	watch: {
		mouseover(value) {
			if (!this.isBig) {
				return
			}
			this.$emit('bottomBarHover', value)
		},
	},

	methods: {
		t,
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
			this.callViewStore.stopPresentation(this.token)
			this.callViewStore.setSelectedVideoPeerId(null)
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
	padding: 0 calc(var(--default-grid-baseline) * 2) var(--default-grid-baseline);
	z-index: 1;

	&--big {
		justify-content: center;
		margin: 0 var(--default-clickable-area); // grid collapse button
		width: calc(100% - var(--default-clickable-area) * 2);
		& .bottom-bar {
			width: unset;
			padding: var(--default-grid-baseline);

			&:hover {
				background-color: rgba(0, 0, 0, 0.2);
				border-radius: var(--border-radius-large);
			}
		}

		& .participant-name {
			margin-inline-end: 0;
		}
	}
}

.bottom-bar {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);
	width: 100%;

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
	margin-block: 0px;
	margin-inline: 8px auto;
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
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	display: flex;
	align-items: center;
	justify-content: center;
}

.iceFailedIndicator {
	opacity: .8 !important;
}

.audioIndicator,
.videoIndicator,
.screenSharingIndicator,
.iceFailedIndicator {
	color: #ffffff !important;
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

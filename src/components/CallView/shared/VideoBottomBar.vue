<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<div class="wrapper"
		:class="{'wrapper--big': isBig}">
		<transition name="fade">
			<div v-if="!connectionStateFailedNoRestart && model.attributes.raisedHand.state"
				class="bottom-bar__statusIndicator">
				<HandBackLeft class="handIndicator"
					decorative
					title=""
					size="18px"
					fill-color="#ffffff" />
			</div>
		</transition>
		<div v-if="!isSidebar"
			class="bottom-bar"
			:class="{'bottom-bar--video-on' : hasShadow, 'bottom-bar--big': isBig }">
			<transition name="fade">
				<div v-show="showNameIndicator"
					class="bottom-bar__nameIndicator"
					:class="{'bottom-bar__nameIndicator--promoted': boldenNameIndicator}">
					{{ participantName }}
				</div>
			</transition>
			<transition name="fade">
				<div v-if="!isScreen"
					v-show="showVideoOverlay"
					class="bottom-bar__mediaIndicator">
					<button v-show="!connectionStateFailedNoRestart"
						v-if="showMicrophone || showMicrophoneOff"
						v-tooltip="audioButtonTooltip"
						class="muteIndicator"
						:disabled="!model.attributes.audioAvailable || !selfIsModerator"
						@click.stop="forceMute">
						<Microphone v-if="showMicrophone"
							:size="20"
							title=""
							fill-color="#ffffff"
							decorative />
						<MicrophoneOff v-if="showMicrophoneOff"
							:size="20"
							title=""
							fill-color="#ffffff"
							decorative />
					</button>
					<button v-show="!connectionStateFailedNoRestart && model.attributes.videoAvailable"
						v-tooltip="videoButtonTooltip"
						class="hideRemoteVideo"
						@click.stop="toggleVideo">
						<VideoIcon v-if="showVideoButton"
							:size="20"
							title=""
							fill-color="#ffffff"
							decorative />
						<VideoOff v-if="!showVideoButton"
							:size="20"
							title=""
							fill-color="#ffffff"
							decorative />
					</button>
					<button v-show="!connectionStateFailedNoRestart"
						v-tooltip="t('spreed', 'Show screen')"
						class="screensharingIndicator"
						:class="screenSharingButtonClass"
						@click.stop="switchToScreen">
						<Monitor :size="20"
							title=""
							fill-color="#ffffff"
							decorative />
					</button>
					<button v-show="connectionStateFailedNoRestart"
						class="iceFailedIndicator"
						:class="{ 'not-failed': !connectionStateFailedNoRestart }"
						disabled="true">
						<AlertCircle :size="20"
							title=""
							fill-color="#ffffff"
							decorative />
					</button>
				</div>
			</transition>
			<button v-if="hasSelectedVideo && isBig"
				class="bottom-bar__button"
				@click="handleStopFollowing">
				{{ stopFollowingLabel }}
			</button>
		</div>
	</div>
</template>

<script>
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import AlertCircle from 'vue-material-design-icons/AlertCircle'
import Microphone from 'vue-material-design-icons/Microphone'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff'
import Monitor from 'vue-material-design-icons/Monitor'
import Video from 'vue-material-design-icons/Video'
import VideoOff from 'vue-material-design-icons/VideoOff'
import { PARTICIPANT } from '../../../constants'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft'
import { emit } from '@nextcloud/event-bus'

export default {
	name: 'VideoBottomBar',

	components: {
		AlertCircle,
		HandBackLeft,
		Microphone,
		MicrophoneOff,
		Monitor,
		VideoIcon: Video,
		VideoOff,
	},

	directives: {
		tooltip: Tooltip,
	},

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
	},

	computed: {
		showMicrophone() {
			return this.model.attributes.audioAvailable && this.selfIsModerator
		},

		showMicrophoneOff() {
			return !this.model.attributes.audioAvailable && this.model.attributes.audioAvailable !== undefined
		},

		connectionStateFailedNoRestart() {
			return this.model.attributes.connectionState === ConnectionState.FAILED_NO_RESTART
		},

		audioButtonTooltip() {
			if (this.model.attributes.audioAvailable) {
				return t('spreed', 'Mute')
			}

			return null
		},

		showVideoButton() {
			return this.sharedData.videoEnabled
		},

		videoButtonTooltip() {
			if (this.sharedData.videoEnabled) {
				return t('spreed', 'Disable video')
			}

			return t('spreed', 'Enable video')
		},

		screenSharingButtonClass() {
			return {
				'screen-on': this.model.attributes.screen,
				'screen-off': !this.model.attributes.screen,
				'screen-visible': this.sharedData.screenVisible,
			}
		},

		showNameIndicator() {
			return !this.model.attributes.videoAvailable || !this.sharedData.videoEnabled || this.showVideoOverlay || this.isSelected || this.isPromoted || this.isSpeaking
		},

		boldenNameIndicator() {
			return this.isSpeaking || this.isSelected
		},

		hasSelectedVideo() {
			return this.$store.getters.selectedVideoPeerId !== null
		},

		stopFollowingLabel() {
			return t('spreed', 'Stop following')
		},

		currentParticipant() {
			return this.$store.getters.conversation(this.token) || {
				sessionId: '0',
				participantType: this.$store.getters.getUserId() !== null ? PARTICIPANT.TYPE.USER : PARTICIPANT.TYPE.GUEST,
			}
		},

		selfIsModerator() {
			return this.currentParticipant.participantType === PARTICIPANT.TYPE.OWNER
				|| this.currentParticipant.participantType === PARTICIPANT.TYPE.MODERATOR
		},
	},

	methods: {

		forceMute() {
			this.model.forceMute()
		},

		toggleVideo() {
			emit('talk:video:toggled', {
				peerId: this.model.attributes.peerId,
				value: !this.sharedData.videoEnabled,
			})
		},

		switchToScreen() {
			if (!this.sharedData.screenVisible) {
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

@import '../../../assets/variables';

.wrapper {
	display: flex;
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 12px 8px 12px;
	align-items: center;
	&--big {
		justify-content: center;
	}
}

.bottom-bar {
	display: flex;
	width: 100%;
	justify-content: space-between;
	align-items: center;
	height: 40px;
	z-index: 1;
	&--big {
		justify-content: center;
		height: 48px;
		width: unset;
	}
	&--video-on {
		text-shadow: 0 0 4px rgba(0, 0, 0,.8);
	}
	&__nameIndicator {
		color: white;
		margin: 0 4px 0 8px;
		position: relative;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
		&--promoted {
			font-weight: bold;
		}
	}
	&__statusIndicator {
		width: 44px;
		height: 44px;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	&__mediaIndicator {
		position: relative;
		background-size: 22px;
		text-align: center;
		margin: 0 4px;
		display: flex;
		flex-wrap: nowrap;
	}
	&__button {
		opacity: 0.8;
		margin-left: 4px;
		border: none;
		&:hover,
		&:focus {
			opacity: 1;
			border: none;
		}
	}
}

.handIndicator {
	margin-top: 8px;
}

.handIndicator,
.muteIndicator,
.hideRemoteVideo,
.screensharingIndicator,
.iceFailedIndicator {
	position: relative;
	display: inline-block;
	background-color: transparent !important;
	border: none;
	padding: 0 12px;
}

.iceFailedIndicator {
	opacity: .8 !important;
}

.screensharingIndicator.screen-off,
.iceFailedIndicator.not-failed {
	display: none;
}

.muteIndicator[disabled],
.hideRemoteVideo {
	opacity: .7;
}

.hideRemoteVideo {
	&:hover,
	&:focus {
		opacity: 1;
	}
}

</style>

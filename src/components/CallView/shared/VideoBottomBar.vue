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
	<div>
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
				<div
					v-if="!isScreen"
					v-show="showVideoOverlay"
					class="bottom-bar__mediaIndicator">
					<button v-show="!connectionStateFailedNoRestart"
						v-tooltip="audioButtonTooltip"
						class="muteIndicator forced-white"
						:class="audioButtonClass"
						:disabled="!model.attributes.audioAvailable || !selfIsModerator"
						@click="forceMute" />
					<button v-show="!connectionStateFailedNoRestart && model.attributes.videoAvailable"
						v-tooltip="videoButtonTooltip"
						class="hideRemoteVideo forced-white"
						:class="videoButtonClass"
						@click="toggleVideo" />
					<button v-show="!connectionStateFailedNoRestart"
						v-tooltip="t('spreed', 'Show screen')"
						class="screensharingIndicator forced-white icon-screen"
						:class="screenSharingButtonClass"
						@click="switchToScreen" />
					<button v-show="connectionStateFailedNoRestart"
						class="iceFailedIndicator forced-white icon-error"
						:class="{ 'not-failed': !connectionStateFailedNoRestart }"
						disabled="true" />
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
import { PARTICIPANT } from '../../../constants'

export default {
	name: 'VideoBottomBar',

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

		connectionStateFailedNoRestart() {
			return this.model.attributes.connectionState === ConnectionState.FAILED_NO_RESTART
		},

		audioButtonClass() {
			return {
				'icon-audio': this.model.attributes.audioAvailable && this.selfIsModerator,
				'icon-audio-off': !this.model.attributes.audioAvailable && this.model.attributes.audioAvailable !== undefined,
			}
		},

		audioButtonTooltip() {
			if (this.model.attributes.audioAvailable) {
				return t('spreed', 'Mute')
			}

			return null
		},

		videoButtonClass() {
			return {
				'icon-video': this.sharedData.videoEnabled,
				'icon-video-off': !this.sharedData.videoEnabled,
			}
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
			this.sharedData.videoEnabled = !this.sharedData.videoEnabled
		},

		switchToScreen() {
			if (!this.sharedData.screenVisible) {
				this.$emit('switchScreenToId', this.peerId)
			}
		},

		handleStopFollowing() {
			this.$store.dispatch('selectedVideoPeerId', null)
		},
	},
}
</script>

<style lang="scss" scoped>

@import '../../../assets/variables.scss';

.bottom-bar {
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 20px 12px 24px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	height: 40px;
	z-index: 1;
	&--big {
		justify-content: center;
		height: 48px;
	}
	&--video-on {
		text-shadow: 0 0 4px rgba(0, 0, 0,.8);
	}
	&__nameIndicator {
		color: white;
		margin-right: 4px;
		position: relative;
		font-size: 20px;
		filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
		&--promoted {
			font-weight: bold;
		}
	}
	&__mediaIndicator {
		position: relative;
		background-size: 22px;
		text-align: center;
		margin: 0 4px;
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

.muteIndicator,
.hideRemoteVideo,
.screensharingIndicator,
.iceFailedIndicator {
	position: relative;
	display: inline-block;
	background-color: transparent !important;
	border: none;
	width: 32px;
	height: 32px;
	background-size: 22px;

	&.hidden {
		display: none;
	}
}

.muteIndicator:not(.icon-audio):not(.icon-audio-off),
.screensharingIndicator.screen-off,
.iceFailedIndicator.not-failed {
	display: none;
}

.muteIndicator.icon-audio-off,
.hideRemoteVideo {
	opacity: .7;
}

.hideRemoteVideo {
	&:hover,
	&:focus {
		opacity: 1;
	}
}

.iceFailedIndicator {
	opacity: .8 !important;
}

</style>

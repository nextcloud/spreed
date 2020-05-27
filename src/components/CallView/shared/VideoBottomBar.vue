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
			:class="{'bottom-bar--video-on' : hasVideo, 'bottom-bar--big': isBig }">
			<transition name="fade">
				<div v-show="showNameIndicator"
					class="bottom-bar__nameIndicator"
					:class="{'bottom-bar__nameIndicator--promoted': boldenNameIndicator}">
					{{ participantName }}
				</div>
			</transition>
			<transition name="fade">
				<div
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
				@click="handlefollowSpeaker">
				{{ followSpeakerLabel }}
			</button>
		</div>
	</div>
</template>

<script>
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'VideoBottomBar',

	directives: {
		tooltip: Tooltip,
	},

	props: {
		isSidebar: {
			type: Boolean,
			required: true,
		},
		hasVideo: {
			type: Boolean,
<<<<<<< HEAD
			required: true,
=======
			default: false,
>>>>>>> 1ce4858d... fixup! Create VideoBottomBar component
		},
		isBig: {
			type: Boolean,
			required: true,
		},
		participantName: {
			type: String,
			required: true,
		},
		showVideoOverlay: {
			type: Boolean,
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
	&--big {
		justify-content: center;
		height: 48px;
	}
	&--video-on {
		text-shadow: 0 0 4px rgba(0, 0, 0,.8);
	}
	&__nameIndicator {
		color: white;
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
		margin: 0 8px;
	}
	&__button {
		opacity: 0.8;
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
.hideRemoteVideo.icon-video-off {
	opacity: .7;
}

.hideRemoteVideo.icon-video-off {
	&:hover,
	&:focus {
		opacity: 1;
	}
}

.iceFailedIndicator {
	opacity: .8 !important;
}

</style>

<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-show="!placeholderForPromoted || sharedData.promoted"
		:id="(placeholderForPromoted ? 'placeholder-' : '') + 'container_' + model.attributes.peerId + '_video_incoming'"
		class="videoContainer"
		:class="containerClass"
		@mouseover="showShadow"
		@mouseleave="hideShadow">
		<transition name="fade">
			<video
				v-show="hasVideoStream && !showPlaceholderForPromoted"
				ref="video"
				:class="videoClass"
				class="video" />
		</transition>
		<transition name="fade">
			<div class="avatar-container">
				<template v-if="showBackgroundandAvatar">
					<VideoBackground
						:display-name="model.attributes.name"
						:user="model.attributes.userId" />
					<Avatar v-if="model.attributes.userId"
						:size="avatarSize"
						:disable-menu="true"
						:disable-tooltip="true"
						:user="model.attributes.userId"
						:display-name="model.attributes.name"
						:class="avatarClass" />
					<div v-if="!model.attributes.userId"
						:class="guestAvatarClass"
						class="avatar guest">
						{{ firstLetterOfGuestName }}
					</div>
				</template>
				<div v-if="showPlaceholderForPromoted" class="avatar-container">
					<AccountCircle fill-color="#FFFFFF" :size="36" />
				</div>
			</div>
		</transition>

		<div v-if="!isSidebar"
			class="bottom-bar"
			:class="{'bottom-bar--video-on' : hasVideoStream, 'bottom-bar--big': isBig }">
			<transition name="fade">
				<div v-show="!model.attributes.videoAvailable || !sharedData.videoEnabled || showVideoOverlay || isSelected || isSpeaking"
					class="bottom-bar__nameIndicator"
					:class="{'bottom-bar__nameIndicator--promoted': isSpeaking || isSelected}">
					{{ participantName }}
				</div>
			</transition>
			<transition name="fade">
				<div
					v-if="isGrid"
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
		</div>
		<div v-if="isSpeaking && !isStripe && !isBig" class="speaking-shadow" />
		<div v-if="mouseover" class="hover-shadow" />
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel'
import { PARTICIPANT } from '../../../constants'
import SHA1 from 'crypto-js/sha1'
import Hex from 'crypto-js/enc-hex'
import video from './video.js'
import VideoBackground from './VideoBackground'
import AccountCircle from 'vue-material-design-icons/AccountCircle'

export default {

	name: 'Video',

	components: {
		Avatar,
		VideoBackground,
		AccountCircle,
	},

	directives: {
		tooltip: Tooltip,
	},

	mixins: [video],

	props: {
		token: {
			type: String,
			required: true,
		},
		placeholderForPromoted: {
			type: Boolean,
			default: false,
		},
		model: {
			type: Object,
			required: true,
		},
		sharedData: {
			type: Object,
			required: true,
		},
		showVideoOverlay: {
			type: Boolean,
			default: true,
		},
		// True if this video component is used in the promoted view's video stripe
		isStripe: {
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
		// True when this component is used in the big video slot in the
		// promoted view
		isBig: {
			type: Boolean,
			default: false,
		},
		// True when this component is used as main video in the sidebar
		isSidebar: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			mouseover: false,
		}
	},
	computed: {

		showBackgroundandAvatar() {
			if (this.isStripe) {
				return !this.hasVideoStream && !(this.isSelected || this.isPromoted)
			} else {
				return !this.hasVideoStream
			}
		},

		showPlaceholderForPromoted() {
			if (this.isStripe) {
				if (this.$store.getters.selectedVideoPeerId !== null) {
					return this.isSelected
				} else {
					return this.isPromoted
				}
			} else {
				return false
			}
		},

		isSelectable() {
			if (this.isStripe) {
				return !this.isSelected
			} else {
				return true
			}
		},

		containerClass() {
			return {
				'videoContainer-dummy': this.placeholderForPromoted,
				'not-connected': !this.placeholderForPromoted && this.model.attributes.connectionState !== ConnectionState.CONNECTED && this.model.attributes.connectionState !== ConnectionState.COMPLETED,
				'speaking': !this.placeholderForPromoted && this.model.attributes.speaking,
				'promoted': !this.placeholderForPromoted && this.sharedData.promoted && !this.isGrid,
				'video-container-grid': this.isGrid,
				'video-container-grid--speaking': this.isSpeaking,
				'video-container-big': this.isBig,
			}
		},

		avatarSize() {
			return !this.sharedData.promoted ? 64 : 128
		},

		avatarClass() {
			return {
				'icon-loading': this.model.attributes.connectionState !== ConnectionState.CONNECTED && this.model.attributes.connectionState !== ConnectionState.COMPLETED && this.model.attributes.connectionState !== ConnectionState.FAILED_NO_RESTART,
			}
		},

		guestAvatarClass() {
			return Object.assign(this.avatarClass, {
				['avatar-' + this.avatarSize + 'px']: true,
			})
		},

		firstLetterOfGuestName() {
			const customName = this.participantName && this.participantName !== t('spreed', 'Guest') ? this.participantName : '?'
			return customName.charAt(0)
		},

		sessionHash() {
			return Hex.stringify(SHA1(this.model.attributes.peerId))
		},

		participantName() {
			let participantName = this.model.attributes.name

			// The name is undefined and not shown until a connection is made
			// for registered users, so do not fall back to the guest name in
			// the store either until the connection was made.
			if (!this.model.attributes.userId && !participantName && participantName !== undefined) {
				participantName = this.$store.getters.getGuestName(
					this.$store.getters.getToken(),
					this.sessionHash,
				)
			}

			return participantName
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
		isSpeaking() {
			return this.model.attributes.speaking
		},
		hasVideoStream() {
			return this.model.attributes.videoAvailable && this.sharedData.videoEnabled && this.model.attributes.stream
		},
	},

	watch: {

		'model.attributes.stream': function(stream) {
			this._setStream(stream)
		},
		isSelected(bool) {
			if (bool) {
				this.mouseover = false
			}
		},

	},

	mounted() {
		// Set initial state
		this._setStream(this.model.attributes.stream)
	},

	methods: {

		_setStream(stream) {

			if (!stream) {
				// Do not clear the srcObject of the video element, just leave
				// the previous stream as a frozen image.

				return
			}

			// The audio is played using an audio element in the model to be
			// able to hear it even if there is no view for it. Moreover, if
			// there is a video track Chromium does not play audio in a video
			// element until the video track starts to play; an audio element is
			// thus needed to play audio when the remote peer starts with the
			// camera available but disabled.
			attachMediaStream(stream, this.$refs.video)

			this.$refs.video.muted = true

			// At least Firefox, Opera and Edge move the video to a wrong
			// position instead of keeping it unchanged when
			// "transform: scaleX(1)" is used ("transform: scaleX(-1)" is fine);
			// as it should have no effect the transform is removed.
			if (this.$refs.video.style.transform === 'scaleX(1)') {
				this.$refs.video.style.transform = ''
			}
		},

		forceMute() {
			this.model.forceMute()
		},

		toggleVideo() {
			this.sharedData.videoEnabled = !this.sharedData.videoEnabled
		},

		switchToScreen() {
			if (!this.sharedData.screenVisible) {
				this.$emit('switchScreenToId', this.model.attributes.peerId)
			}
		},
		showShadow() {
			if (this.isSelectable) {
				this.mouseover = true
			}
		},
		hideShadow() {
			if (this.isSelectable) {
				this.mouseover = false
			}
		},
	},

}
</script>

<style lang="scss" scoped>
.forced-white {
	filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
}

@import '../../../assets/avatar.scss';
@import '../../../assets/variables.scss';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.video-container-grid {
	position: relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.video-container-big {
	position: absolute;
	height: 100%;
	width: 100%;
}
.avatar-container {
	margin: auto;
}

.bottom-bar {
	position: absolute;
	bottom: 0;
	height: 40px;
	width: 100%;
	padding: 0 20px 12px 24px;
	display: flex;
	justify-content: space-between;
	align-items: flex-end;
	&--big {
		justify-content: flex-start;
	}
	&--video-on {
		text-shadow: 0 0 4px rgba(0, 0, 0,.8);
	}
	&__nameIndicator {
		color: white;
		position: relative;
		font-size: 20px;
		&--promoted {
			font-weight: bold;
		}
	}
	&__mediaIndicator {
		position: relative;
		background-size: 22px;
		text-align: center;
		margin: 0 0 -7px 8px;
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

.video {
	height: 100%;
	width: 100%;
}

.video--fit {
	/* Fit the frame */
	object-fit: contain;
}

.video--fill {
	/* Fill the frame */
	object-fit: cover;
}

.speaking-shadow {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	left: 0;
	box-shadow: inset 0 0 0 2px white;
}

.hover-shadow {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	left: 0;
	box-shadow: inset 0 0 0 3px white;
	cursor: pointer;
}

</style>

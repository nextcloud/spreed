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
		:class="containerClass">
		<video v-if="!placeholderForPromoted" v-show="model.attributes.videoAvailable && sharedData.videoEnabled" ref="video" />
		<div v-if="!placeholderForPromoted" v-show="!model.attributes.videoAvailable || !sharedData.videoEnabled" class="avatar-container">
			<Avatar v-if="model.attributes.userId"
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:user="model.attributes.userId"
				:display-name="model.attributes.name"
				:class="avatarClass" />
			<div v-else
				:class="guestAvatarClass"
				class="avatar guest">
				{{ firstLetterOfGuestName }}
			</div>
		</div>
		<div class="nameIndicator">
			{{ participantName }}
		</div>
		<div class="mediaIndicator">
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

export default {

	name: 'Video',

	components: {
		Avatar,
	},

	directives: {
		tooltip: Tooltip,
	},

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
		useConstrainedLayout: {
			type: Boolean,
			default: false,
		},
		isGrid: {
			type: Boolean,
			default: false,
		},
	},

	computed: {

		containerClass() {
			return {
				'videoContainer-dummy': this.placeholderForPromoted,
				'not-connected': !this.placeholderForPromoted && this.model.attributes.connectionState !== ConnectionState.CONNECTED && this.model.attributes.connectionState !== ConnectionState.COMPLETED,
				'speaking': !this.placeholderForPromoted && this.model.attributes.speaking,
				'promoted': !this.placeholderForPromoted && this.sharedData.promoted && !this.isGrid,
			}
		},

		avatarSize() {
			return (this.useConstrainedLayout && !this.sharedData.promoted) ? 64 : 128
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
			const customName = this.participantName !== t('spreed', 'Guest') ? this.participantName : '?'
			return customName.charAt(0)
		},

		sessionHash() {
			return Hex.stringify(SHA1(this.model.attributes.peerId))
		},

		participantName() {
			let participantName = this.model.attributes.name

			if (!this.model.attributes.userId) {
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

	},

	watch: {

		'model.attributes.stream': function(stream) {
			this._setStream(stream)
		},

	},

	mounted() {
		// Set initial state
		this._setStream(this.model.attributes.stream)
	},

	methods: {

		_setStream(stream) {
			if (this.placeholderForPromoted) {
				return
			}

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

	},

}
</script>

<style lang="scss" scoped>
.forced-white {
	filter: drop-shadow(1px 1px 4px var(--color-box-shadow));
}

@import '../../../assets/avatar.scss';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.mediaIndicator {
	position: absolute;
	width: 100%;
	bottom: 44px;
	left: 0;
	background-size: 22px;
	text-align: center;
}

.constrained-layout .mediaIndicator {
	/* Move the media indicator closer to the bottom */
	bottom: 16px;
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

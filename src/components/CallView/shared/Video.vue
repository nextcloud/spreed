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
		:id="(placeholderForPromoted ? 'placeholder-' : '') + 'container_' + peerId + '_video_incoming'"
		class="videoContainer"
		:class="containerClass"
		@mouseover="showShadow"
		@mouseleave="hideShadow"
		@click="handleClickVideo">
		<transition name="fade">
			<video
				v-show="showVideo"
				ref="video"
				:class="videoClass"
				class="video" />
		</transition>
		<transition name="fade">
			<Screen
				v-if="showSharedScreen"
				:is-big="isBig"
				:token="token"
				:call-participant-model="model"
				:shared-data="sharedData" />
		</transition>
		<transition-group name="fade">
			<div
				v-if="showBackgroundAndAvatar"
				:key="'backgroundAvatar'"
				class="avatar-container">
				<VideoBackground
					:display-name="model.attributes.name"
					:user="model.attributes.userId"
					:grid-blur="videoBackgroundBlur" />
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
			</div>
			<div v-if="showPlaceholderForPromoted"
				:key="'placeholderForPromoted'"
				class="placeholder-for-promoted">
				<AccountCircle v-if="isPromoted || isSelected" fill-color="#FFFFFF" :size="36" />
			</div>
		</transition-group>
		<VideoBottomBar v-bind="$props"
			:has-shadow="hasVideo"
			:participant-name="participantName" />
		<div v-if="isSpeaking && !isStripe && !isBig" class="speaking-shadow" />
		<div v-if="mouseover && !isBig" class="hover-shadow" />
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel'
import SHA1 from 'crypto-js/sha1'
import Hex from 'crypto-js/enc-hex'
import video from '../../../mixins/video.js'
import VideoBackground from './VideoBackground'
import AccountCircle from 'vue-material-design-icons/AccountCircle'
import VideoBottomBar from './VideoBottomBar'
import Screen from './Screen'

export default {

	name: 'Video',

	components: {
		Avatar,
		VideoBackground,
		AccountCircle,
		Screen,
		VideoBottomBar,
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
		// True when this component is used as main video in the sidebar
		isSidebar: {
			type: Boolean,
			default: false,
		},
		// Calculated once in the grid component for each video background
		videoBackgroundBlur: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			mouseover: false,
		}
	},

	computed: {

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
			return this.isBig ? 128 : 128
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
			return Hex.stringify(SHA1(this.peerId))
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

		isSpeaking() {
			return this.model.attributes.speaking
		},

		hasVideo() {
			return this.model.attributes.videoAvailable && this.sharedData.videoEnabled && (typeof this.model.attributes.stream === 'object')
		},

		hasSelectedVideo() {
			return this.$store.getters.selectedVideoPeerId !== null
		},

		hasSharedScreen() {
			return this.model.attributes.screen
		},

		isSharedScreenPromoted() {
			return this.sharedData.screenVisible && (!this.hasSelectedVideo || this.isSelected)
		},

		showSharedScreen() {
			// Big screen
			if (this.isBig) {
				// Alwais show shared screen if there's one
				return this.hasSharedScreen
			// Stripe
			} else if (this.isStripe) {
				if (this.isSharedScreenPromoted) {
					return false
				} else {
					// Show the shared screen if not selected or promoted
					return !((this.isSelected) ? this.isSelected : this.isPromoted) && this.hasSharedScreen
				}

			// Grid
			} else {
				// Alwais show shared screen if there's one
				return this.hasSharedScreen
			}
		},

		showVideo() {
			// Screenshare have higher priority so return false if screenshare
			// is shown
			if (this.hasSharedScreen) {
				return !this.showSharedScreen && this.hasVideo && !this.isSelected
			} else {
				if (this.isStripe) {
					if (this.hasSelectedVideo) {
						return !this.isSelected && this.hasVideo
					} else {
						return !this.isPromoted && this.hasVideo
					}
				} else {
					return this.hasVideo
				}

			}
		},

		showPlaceholderForPromoted() {
			if (this.isStripe) {
				if (this.showVideo || this.showSharedScreen) {
					return false
				} else if (this.$store.getters.selectedVideoPeerId !== null) {
					return this.isSelected
				} else {
					return this.isPromoted
				}
			} else {
				return false
			}

		},

		showBackgroundAndAvatar() {
			if (this.showSharedScreen || this.showVideo || this.showPlaceholderForPromoted) {
				return false
			} else {
				return true
			}
		},

		peerId() {
			return this.model.attributes.peerId
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

		handleClickVideo() {
			this.$emit('click-video')
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
	width: 100%;
	height: 100%;
	position: absolute;
	display: flex;
	justify-content: center;
	align-items: center;
}

.placeholder-for-promoted {
	background: radial-gradient(146.1% 146.1% at 50% 50%, #333333 0%, #858585 100%);
	width: 100%;
	height: 100%;
	position: absolute;
	display: flex;
	justify-content: center;
	align-items: center;
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

<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div ref="videoContainer"
		class="localVideoContainer videoContainer videoView"
		:class="videoContainerClass"
		@mouseover="showShadow"
		@mouseleave="hideShadow"
		@click="handleClickVideo">
		<div v-show="localMediaModel.attributes.videoEnabled"
			:class="videoWrapperClass"
			class="videoWrapper"
			:style="videoWrapperStyle">
			<video id="localVideo"
				ref="video"
				disablePictureInPicture="true"
				:class="videoClass"
				class="video"
				@playing="updateVideoAspectRatio" />
		</div>
		<div v-if="!localMediaModel.attributes.videoEnabled && !isSidebar" class="avatar-container">
			<VideoBackground v-if="isGrid || isStripe"
				:display-name="displayName"
				:user="userId" />
			<NcAvatar v-if="userId"
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:user="userId"
				:display-name="displayName"
				:class="avatarClass" />
			<div v-if="!userId"
				:class="guestAvatarClass"
				class="avatar guest">
				{{ firstLetterOfGuestName }}
			</div>
		</div>

		<div v-if="mouseover && isSelectable" class="hover-shadow" />
		<div class="bottom-bar">
			<NcButton v-if="isBig"
				type="tertiary"
				class="bottom-bar__button"
				@click="handleStopFollowing">
				{{ stopFollowingLabel }}
			</NcButton>
		</div>
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream/attachmediastream.bundle.js'
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'

import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import VideoBackground from './VideoBackground.vue'

import video from '../../../mixins/video.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

export default {

	name: 'LocalVideo',

	components: {
		NcAvatar,
		NcButton,
		VideoBackground,
	},

	mixins: [video],

	props: {
		token: {
			type: String,
			required: true,
		},
		localMediaModel: {
			type: Object,
			required: true,
		},
		localCallParticipantModel: {
			type: Object,
			required: true,
		},
		useConstrainedLayout: {
			type: Boolean,
			default: false,
		},
		isStripe: {
			type: Boolean,
			default: false,
		},
		isSidebar: {
			type: Boolean,
			default: false,
		},
		showControls: {
			type: Boolean,
			default: true,
		},
		unSelectable: {
			type: Boolean,
			default: false,
		},
		isSmall: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			notificationHandle: null,
			videoAspectRatio: null,
			containerAspectRatio: null,
			resizeObserver: null,
		}
	},

	computed: {
		stopFollowingLabel() {
			return t('spreed', 'Back')
		},

		isNotConnected() {
			// When there is no sender participant (when the MCU is not used, or
			// if it is used but no peer object has been set yet) the local
			// video is shown as connected.
			return this.localCallParticipantModel.attributes.peerNeeded
				&& this.localCallParticipantModel.attributes.connectionState !== ConnectionState.CONNECTED && this.localCallParticipantModel.attributes.connectionState !== ConnectionState.COMPLETED
		},

		videoContainerClass() {
			return {
				'not-connected': this.isNotConnected,
				speaking: this.localMediaModel.attributes.speaking,
				'video-container-grid': this.isGrid,
				'video-container-stripe': this.isStripe,
				'video-container-big': this.isBig,
				'video-container-small': this.isSmall,
			}
		},

		videoWrapperStyle() {
			if (!this.containerAspectRatio || !this.videoAspectRatio || !this.isBig) {
				return
			}
			return (this.containerAspectRatio > this.videoAspectRatio)
				? `width: ${this.$refs.videoContainer.clientHeight * this.videoAspectRatio}px`
				: `height: ${this.$refs.videoContainer.clientWidth / this.videoAspectRatio}px`
		},

		userId() {
			return this.$store.getters.getUserId()
		},

		displayName() {
			return this.$store.getters.getDisplayName()
		},

		firstLetterOfGuestName() {
			const customName = this.guestName !== t('spreed', 'Guest') ? this.guestName : '?'
			return customName.charAt(0)
		},

		sessionHash() {
			return Hex.stringify(SHA1(this.localCallParticipantModel.attributes.peerId))
		},

		guestName() {
			return this.$store.getters.getGuestName(
				this.$store.getters.getToken(),
				this.sessionHash,
			)
		},

		videoWrapperClass() {
			return {
				'icon-loading': this.isNotConnected,
			}
		},

		avatarSize() {
			return this.useConstrainedLayout ? 64 : 128
		},

		avatarClass() {
			return {
				'icon-loading': this.isNotConnected,
			}
		},

		guestAvatarClass() {
			return Object.assign(this.avatarClass, {
				['avatar-' + this.avatarSize + 'px']: true,
			})
		},

		localStreamVideoError() {
			return this.localMediaModel.attributes.localStream && this.localMediaModel.attributes.localStreamRequestVideoError
		},

		hasLocalVideo() {
			return this.localMediaModel.attributes.videoEnabled
		},

		isSelected() {
			return this.$store.getters.selectedVideoPeerId === 'local'
		},

		isSelectable() {
			return !this.unSelectable && !this.isSidebar && this.hasLocalVideo && this.$store.getters.selectedVideoPeerId !== 'local'
		},
	},

	watch: {

		localCallParticipantModel: {
			immediate: true,

			handler(localCallParticipantModel, oldLocalCallParticipantModel) {
				if (oldLocalCallParticipantModel) {
					oldLocalCallParticipantModel.off('forcedMute', this._handleForcedMute)
				}

				if (localCallParticipantModel) {
					localCallParticipantModel.on('forcedMute', this._handleForcedMute)
				}
			},
		},

		'localMediaModel.attributes.localStream'(localStream) {
			this._setLocalStream(localStream)
		},

		localStreamVideoError: {
			immediate: true,

			handler(error) {
				if (error) {
					if (error.name === 'NotAllowedError') {
						this.notificationHandle = showError(t('spreed', 'Access to camera was denied'))
					} else if (error.name === 'NotReadableError' || error.name === 'AbortError') {
						// when camera in use, Chrome gives NotReadableError, Firefox gives AbortError
						this.notificationHandle = showError(t('spreed', 'Error while accessing camera: It is likely in use by another program'), {
							timeout: TOAST_PERMANENT_TIMEOUT,
						})
					} else {
						console.error('Error while accessing camera: ', error.message, error.name)
						this.notificationHandle = showError(t('spreed', 'Error while accessing camera'), {
							timeout: TOAST_PERMANENT_TIMEOUT,
						})
					}
				}
			},
		},

	},

	mounted() {
		// Set initial state
		this._setLocalStream(this.localMediaModel.attributes.localStream)

		if (this.isBig) {
			this.resizeObserver = new ResizeObserver(this.updateContainerAspectRatio)
			this.resizeObserver.observe(this.$refs.videoContainer)
		}
	},

	beforeDestroy() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}
	},

	destroyed() {
		if (this.notificationHandle) {
			this.notificationHandle.hideToast()
		}
		if (this.localCallParticipantModel) {
			this.localCallParticipantModel.off('forcedMute', this._handleForcedMute)
		}
	},

	methods: {

		_handleForcedMute() {
			// The default toast selector is "body-user", but as this toast can
			// be shown to guests too, a generic selector valid both for logged-in
			// users and guests needs to be used instead (undefined selects
			// the body element).
			showInfo(t('spreed', 'You have been muted by a moderator'), { selector: undefined })
		},

		_setLocalStream(localStream) {
			if (!localStream) {
				// Do not clear the srcObject of the video element, just leave
				// the previous stream as a frozen image.

				return
			}

			const options = {
				autoplay: true,
				mirror: true,
				muted: true,
			}
			attachMediaStream(localStream, this.$refs.video, options)
		},

		handleStopFollowing() {
			this.$store.dispatch('selectedVideoPeerId', null)
			this.$store.dispatch('stopPresentation')
		},

		updateContainerAspectRatio([{ target }]) {
			this.containerAspectRatio = target.clientWidth / target.clientHeight
		},

		updateVideoAspectRatio() {
			if (!this.isBig) {
				return
			}
			this.videoAspectRatio = this.localMediaModel.attributes.localStream.getVideoTracks()?.[0].getSettings().aspectRatio
				// Fallback for Firefox
				?? this.$refs.video.videoWidth / this.$refs.video.videoHeight
		},
	},

}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables';
@import '../../../assets/avatar';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.not-connected {
	video,
	.avatar-container {
		opacity: 0.5;
	}
}

// Always display the local video in the last row
.localVideoContainer {
	grid-row-end: -1;
	border-radius: calc(var(--default-clickable-area)/2);
	z-index: 1;
}

.video-container-grid {
	position:relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.video-container-stripe:not(.local-video--sidebar) {
	// aspect-ratio is set according to the maximum video resolution after applying constraints (720*540)
	--aspect-ratio: 1.33333;
	--stripe-height: 242px;
	position: relative;
	flex: 0 0 calc(var(--aspect-ratio) * var(--stripe-height));
	overflow: hidden;
	display: flex;
	flex-direction: column;
	margin-top: auto;
	height: var(--stripe-height) !important;
}

.video-container-big {
	position: absolute;
	width: calc(100% - 16px);
	height: calc(100% - 8px);
	display: flex;
	flex-direction: column;

	& .videoWrapper {
		margin: auto;
	}
}

.video-container-small {
	border-radius: var(--border-radius-large);
}

.videoWrapper,
.video {
	height: 100%;
	width: 100%;
}

.videoWrapper.icon-loading:after {
	height: 60px;
	width: 60px;
	margin: -32px 0 0 -32px;
}

.video--fit {
	/* Fit the frame */
	object-fit: contain;
}

.video--fill {
	/* Fill the frame */
	object-fit: cover;
}

.avatar-container {
	margin: auto;
}

.hover-shadow {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	left: 0;
	box-shadow: inset 0 0 0 3px white;
	cursor: pointer;
	border-radius: calc(var(--default-clickable-area)/2);
}

.bottom-bar {
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 20px 12px 24px;
	display: flex;
	justify-content: center;
	align-items: center;
	height: 40px;
	&--big {
		justify-content: center;
		height: 48px;
	}
	& &__button {
		opacity: 0.8;
		background-color: var(--color-background-dark);
		&:hover,
		&:focus {
			opacity: 1;
		}
	}
}
</style>

<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="videoContainer"
		class="localVideoContainer videoContainer videoView"
		:class="videoContainerClass"
		@mouseover="mouseover = true"
		@mouseleave="mouseover = false"
		@click="$emit('click-video')">
		<div v-show="localMediaModel.attributes.videoEnabled"
			:class="videoWrapperClass"
			class="videoWrapper"
			:style="videoWrapperStyle">
			<video id="localVideo"
				ref="video"
				disablePictureInPicture="true"
				:class="fitVideo ? 'video--fit' : 'video--fill'"
				class="video"
				@playing="updateVideoAspectRatio" />
		</div>
		<div v-if="!localMediaModel.attributes.videoEnabled && !isSidebar" class="avatar-container">
			<VideoBackground v-if="isGrid || isStripe"
				:display-name="displayName"
				:user="userId" />
			<AvatarWrapper :id="userId"
				:token="token"
				:name="displayName"
				:source="actorType"
				:size="avatarSize"
				disable-menu
				disable-tooltip
				:class="avatarClass" />
		</div>

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
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'

// eslint-disable-next-line
// import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import VideoBackground from './VideoBackground.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../../constants.js'
import { useGuestNameStore } from '../../../stores/guestName.js'
import attachMediaStream from '../../../utils/attachmediastream.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'

export default {

	name: 'LocalVideo',

	components: {
		AvatarWrapper,
		NcButton,
		VideoBackground,
	},

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
		isGrid: {
			type: Boolean,
			default: false,
		},
		isStripe: {
			type: Boolean,
			default: false,
		},
		fitVideo: {
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
		isBig: {
			type: Boolean,
			default: false,
		},
		isSmall: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click-video'],

	setup() {
		const guestNameStore = useGuestNameStore()
		return { guestNameStore }
	},

	data() {
		return {
			notificationHandle: null,
			videoAspectRatio: null,
			containerAspectRatio: null,
			resizeObserver: null,
			mouseover: false,
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
				'video-container-grid': this.isGrid,
				'video-container-stripe': this.isStripe,
				'video-container-big': this.isBig,
				'video-container-small': this.isSmall,
				'hover-shadow': this.isSelectable && this.mouseover,
				speaking: this.localMediaModel.attributes.speaking,
			}
		},

		videoWrapperStyle() {
			if (!this.containerAspectRatio || !this.videoAspectRatio || !this.isBig || this.isGrid) {
				return
			}
			return (this.containerAspectRatio > this.videoAspectRatio)
				? `width: ${this.$refs.videoContainer.clientHeight * this.videoAspectRatio}px`
				: `height: ${this.$refs.videoContainer.clientWidth / this.videoAspectRatio}px`
		},

		userId() {
			return this.$store.getters.getUserId()
		},

		actorType() {
			return this.$store.getters.getActorType()
		},

		displayName() {
			return this.$store.getters.getDisplayName()
		},

		sessionHash() {
			return Hex.stringify(SHA1(this.localCallParticipantModel.attributes.peerId))
		},

		guestName() {
			return this.guestNameStore.getGuestName(
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
			if (this.isStripe || (!this.isBig && !this.isGrid)) {
				return AVATAR.SIZE.LARGE
			} else if (!this.containerAspectRatio) {
				return AVATAR.SIZE.FULL
			} else {
				return Math.min(AVATAR.SIZE.FULL, this.$refs.videoContainer.clientHeight / 2, this.$refs.videoContainer.clientWidth / 2)
			}
		},

		avatarClass() {
			return {
				'icon-loading': this.isNotConnected,
			}
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
						this.notificationHandle = window.OCP.Toast.error(t('spreed', 'Access to camera was denied'))
					} else if (error.name === 'NotReadableError' || error.name === 'AbortError') {
						// when camera in use, Chrome gives NotReadableError, Firefox gives AbortError
						this.notificationHandle = window.OCP.Toast.error(t('spreed', 'Error while accessing camera: It is likely in use by another program'), {
							timeout: TOAST_PERMANENT_TIMEOUT,
						})
					} else {
						console.error('Error while accessing camera: ', error.message, error.name)
						this.notificationHandle = window.OCP.Toast.error(t('spreed', 'Error while accessing camera'), {
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

		if (this.isBig || this.isGrid) {
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
.not-connected {
	video,
	.avatar-container {
		opacity: 0.5;
	}
}

// Always display the local video in the last row
.localVideoContainer {
	grid-row-end: -1;
	border-radius: calc(var(--default-clickable-area) / 2);
	z-index: 1;
}

.video-container-grid {
	position: relative;
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

.localVideoContainer:after {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	left: 0;
	border-radius: calc(var(--default-clickable-area) / 2);
}

.hover-shadow:after {
	content: '';
	box-shadow: inset 0 0 0 3px white;
	cursor: pointer;
}

.speaking:after {
	content: '';
	box-shadow: inset 0 0 0 2px white;
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

<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div ref="videoContainer"
		class="localVideoContainer"
		:class="videoContainerClass"
		@mouseover="mouseover = true"
		@mouseleave="mouseover = false"
		@click="$emit('click-video')">
		<img v-if="screenshotModeUrl"
			class="dev-mode-video--self videoWrapper"
			alt="dev-mode-video--self"
			:src="screenshotModeUrl">

		<div v-show="!screenshotModeUrl && localMediaModel.attributes.videoEnabled"
			class="videoWrapper"
			:style="videoWrapperStyle">
			<video id="localVideo"
				ref="video"
				disablePictureInPicture="true"
				:class="fitVideo ? 'video--fit' : 'video--fill'"
				class="video"
				@playing="updateVideoAspectRatio" />
			<AccountOff v-if="isPresenterOverlay && mouseover"
				class="presenter-icon__hide"
				:aria-label="t('spreed', 'Hide presenter video')"
				:title="t('spreed', 'Hide presenter video')"
				:size="32"
				@click="$emit('click-presenter')" />
			<NcLoadingIcon v-if="isNotConnected"
				:size="avatarSize / 2"
				class="video-loading" />
		</div>
		<div v-if="!screenshotModeUrl && !localMediaModel.attributes.videoEnabled && !isSidebar" class="avatar-container">
			<VideoBackground v-if="isGrid || isStripe"
				:display-name="displayName"
				:user="userId" />
			<AvatarWrapper :id="userId"
				:token="token"
				:name="displayName"
				:source="actorType"
				:size="avatarSize"
				:loading="isNotConnected"
				disable-menu
				disable-tooltip />
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
import { inject, ref } from 'vue'

import AccountOff from 'vue-material-design-icons/AccountOff.vue'

import { showError, showInfo, TOAST_PERMANENT_TIMEOUT } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'

import VideoBackground from './VideoBackground.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../../constants.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import attachMediaStream from '../../../utils/attachmediastream.js'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'
import { placeholderImage } from '../Grid/gridPlaceholders.ts'

export default {

	name: 'LocalVideo',

	components: {
		AvatarWrapper,
		AccountOff,
		NcButton,
		VideoBackground,
		NcLoadingIcon,
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
		isPresenterOverlay: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click-video', 'click-presenter'],

	setup() {
		const devMode = inject('CallView:devModeEnabled', ref(false))
		const screenshotMode = inject('CallView:screenshotModeEnabled', ref(false))

		return {
			devMode,
			screenshotMode,
			callViewStore: useCallViewStore(),
		}
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
				presenter: this.isPresenterOverlay && this.mouseover,
				'presenter-overlay': this.isPresenterOverlay,
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

		avatarSize() {
			if (this.isStripe || (!this.isBig && !this.isGrid)) {
				return AVATAR.SIZE.LARGE
			} else if (!this.containerAspectRatio) {
				return AVATAR.SIZE.FULL
			} else {
				return Math.min(AVATAR.SIZE.FULL, this.$refs.videoContainer.clientHeight / 2, this.$refs.videoContainer.clientWidth / 2)
			}
		},

		localStreamVideoError() {
			return this.localMediaModel.attributes.localStream && this.localMediaModel.attributes.localStreamRequestVideoError
		},

		hasLocalVideo() {
			return this.localMediaModel.attributes.videoEnabled
		},

		isSelected() {
			return this.callViewStore.selectedVideoPeerId === 'local'
		},

		isSelectable() {
			return !this.unSelectable && !this.isSidebar && this.hasLocalVideo && this.callViewStore.selectedVideoPeerId !== 'local'
		},

		screenshotModeUrl() {
			return this.screenshotMode ? placeholderImage(8) : ''
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
		t,
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
			this.callViewStore.setSelectedVideoPeerId(null)
			this.callViewStore.stopPresentation(this.token)
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
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
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
	width: calc(100% - var(--grid-gap) * 2);
	height: calc(100% - var(--grid-gap));
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

.video-loading {
	position: absolute;
	top: 0;
	inset-inline-end: 0;
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

.avatar-container {
	margin: auto;
}

.presenter-overlay,
.presenter-overlay * {
	border-radius: 50%;
}

.localVideoContainer::after {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	inset-inline-start: 0;
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
}

.presenter-overlay::after {
	border-radius: 50%;
	z-index: 1;
}

.hover-shadow::after {
	content: '';
	box-shadow: inset 0 0 0 3px white;
	cursor: pointer;
}

.speaking::after {
	content: '';
	box-shadow: inset 0 0 0 2px white;
}

.bottom-bar {
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0 calc(var(--default-grid-baseline) * 3);
	padding-bottom: calc(var(--default-grid-baseline) * 2);
	display: flex;
	justify-content: center;
	align-items: center;

	&--big {
		justify-content: center;
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

.dev-mode-video--self {
	object-fit: cover !important;
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));

	.presenter-overlay & {
		border-radius: 50%;
	}
}

.presenter-icon__hide {
	position: absolute;
	color: white;
	inset-inline-start: calc(50% - var(--default-clickable-area) / 2);
	top: calc(100% - var(--default-grid-baseline) - var(--default-clickable-area));
	opacity: 0.7;
	background-color: rgba(0, 0, 0, 0.5);
	border-radius: 50%;
	padding: 6px;
	width: var(--default-clickable-area);
	height: var(--default-clickable-area);
	z-index: 2; // Above video and its border

	&:hover {
		cursor: pointer;
		opacity: 0.9;
	}

}
</style>

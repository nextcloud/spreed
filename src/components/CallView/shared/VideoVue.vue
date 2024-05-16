<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-show="!placeholderForPromoted || sharedData.promoted"
		:id="(placeholderForPromoted ? 'placeholder-' : '') + 'container_' + peerId + '_video_incoming'"
		ref="videoContainer"
		class="video-container"
		:class="containerClass"
		@mouseover="mouseover = true"
		@mouseleave="mouseover = false"
		@click="$emit('clickVideo')">
		<TransitionWrapper name="fade">
			<div v-show="showVideo"
				:class="videoWrapperClass"
				class="videoWrapper"
				:style="videoWrapperStyle">
				<video ref="video"
					:disablePictureInPicture="!isBig"
					:class="fitVideo ? 'video--fit' : 'video--fill'"
					class="video"
					@playing="updateVideoAspectRatio" />
				<AccountOff v-if="isPresenterOverlay && mouseover"
					class="presenter-icon__hide"
					:aria-label="t('spreed', 'Hide presenter video')"
					:title="t('spreed', 'Hide presenter video')"
					:size="32"
					@click="$emit('clickPresenter')" />
				<NcLoadingIcon v-if="isLoading"
					:size="avatarSize / 2"
					class="video-loading" />

				<img v-if="screenshotModeUrl && isPresenterOverlay"
					class="dev-mode-video--presenter"
					alt="dev-mode-video--presenter"
					:src="screenshotModeUrl">
			</div>
		</TransitionWrapper>
		<TransitionWrapper name="fade">
			<Screen v-if="showSharedScreen"
				:is-big="isBig"
				:token="token"
				:call-participant-model="model"
				:shared-data="sharedData" />
		</TransitionWrapper>
		<TransitionWrapper name="fade">
			<div v-if="showBackgroundAndAvatar"
				key="backgroundAvatar"
				class="avatar-container">
				<VideoBackground :display-name="displayName" :user="participantUserId" />
				<AvatarWrapper :id="participantUserId"
					:token="token"
					:name="displayName"
					:source="participantActorType"
					:size="avatarSize"
					:loading="isLoading"
					disable-menu
					disable-tooltip />
			</div>
		</TransitionWrapper>
		<TransitionWrapper name="fade">
			<div v-if="showPlaceholderForPromoted"
				key="placeholderForPromoted"
				class="placeholder-for-promoted">
				<AccountCircle v-if="isPromoted || isSelected" fill-color="#FFFFFF" :size="64" />
			</div>
		</TransitionWrapper>
		<div v-if="connectionMessage"
			:class="connectionMessageClass"
			class="connection-message">
			{{ connectionMessage }}
		</div>
		<slot v-if="!hideBottomBar" name="bottom-bar">
			<VideoBottomBar :has-shadow="hasVideo"
				:participant-name="participantName"
				v-bind="$props"
				@bottom-bar-hover="handleHoverEvent" />
		</slot>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import { inject, ref } from 'vue'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AccountCircle from 'vue-material-design-icons/AccountCircle.vue'
import AccountOff from 'vue-material-design-icons/AccountOff.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import TransitionWrapper from '../../UIShared/TransitionWrapper.vue'
import Screen from './Screen.vue'
import VideoBackground from './VideoBackground.vue'
import VideoBottomBar from './VideoBottomBar.vue'
import { ATTENDEE, AVATAR } from '../../../constants.ts'
import { EventBus } from '../../../services/EventBus.ts'
import { useCallViewStore } from '../../../stores/callView.ts'
import { useGuestNameStore } from '../../../stores/guestName.js'
import attachMediaStream from '../../../utils/attachmediastream.js'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'
import { ConnectionState } from '../../../utils/webrtc/models/CallParticipantModel.js'
import { placeholderImage } from '../Grid/gridPlaceholders.ts'

export default {

	name: 'VideoVue',

	components: {
		AvatarWrapper,
		TransitionWrapper,
		VideoBackground,
		Screen,
		VideoBottomBar,
		NcLoadingIcon,
		// icons
		AccountCircle,
		AccountOff,
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

		showVideoOverlay: {
			type: Boolean,
			default: true,
		},

		isGrid: {
			type: Boolean,
			default: false,
		},

		fitVideo: {
			type: Boolean,
			default: false,
		},

		isPresenterOverlay: {
			type: Boolean,
			default: false,
		},

		isBig: {
			type: Boolean,
			default: false,
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

		// True when this video component is used in one to one conversations
		isOneToOne: {
			type: Boolean,
			default: false,
		},

		unSelectable: {
			type: Boolean,
			default: false,
		},

		hideBottomBar: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['clickVideo', 'clickPresenter', 'forcePromoteVideo'],

	setup() {
		const screenshotMode = inject('CallView:screenshotModeEnabled', ref(false))

		return {
			callViewStore: useCallViewStore(),
			guestNameStore: useGuestNameStore(),
			screenshotMode,
		}
	},

	data() {
		return {
			videoAspectRatio: null,
			containerAspectRatio: null,
			resizeObserver: null,
			mouseover: false,
		}
	},

	computed: {
		videoWrapperStyle() {
			if (!this.containerAspectRatio || !this.videoAspectRatio || !this.isBig || this.isGrid) {
				return
			}

			return (this.containerAspectRatio > this.videoAspectRatio)
				? `width: ${this.$refs.videoContainer.clientHeight * this.videoAspectRatio}px`
				: `height: ${this.$refs.videoContainer.clientWidth / this.videoAspectRatio}px`
		},

		isSelectable() {
			if (this.isStripe) {
				return !this.isSelected
			} else {
				return true
			}
		},

		wasConnectedAtLeastOnce() {
			return this.model.attributes.connectedAtLeastOnce
		},

		isConnected() {
			return this.model.attributes.connectionState === ConnectionState.CONNECTED || this.model.attributes.connectionState === ConnectionState.COMPLETED
		},

		isLoading() {
			return !this.isConnected && this.model.attributes.connectionState !== ConnectionState.FAILED_NO_RESTART
		},

		isDisconnected() {
			return this.model.attributes.connectionState !== ConnectionState.NEW && this.model.attributes.connectionState !== ConnectionState.CHECKING
				&& this.model.attributes.connectionState !== ConnectionState.CONNECTED && this.model.attributes.connectionState !== ConnectionState.COMPLETED
		},

		/**
		 * Whether the connection to the participant is being tried again.
		 *
		 * The initial connection to the participant is excluded.
		 *
		 * A "failed" connection state will trigger a reconnection, but that may
		 * not immediately change the "negotiating" or "connecting" attributes
		 * (for example, while the new offer requested to the HPB was not
		 * received yet). Similarly, both "negotiating" and "connecting" need to
		 * be checked, as the negotiation will start before the connection
		 * attempt is started.
		 *
		 * If the negotiation is done while there is still a connection it is
		 * not regarded as reconnecting, as in that case it is a renegotiation
		 * to update the current connection.
		 */
		isReconnecting() {
			return this.model.attributes.connectionState === ConnectionState.FAILED
				|| (!this.model.attributes.initialConnection
					&& ((this.model.attributes.negotiating && !this.isConnected) || this.model.attributes.connecting))
		},

		isNoLongerTryingToReconnect() {
			return this.model.attributes.connectionState === ConnectionState.FAILED_NO_RESTART
		},

		connectionMessage() {
			if (!this.wasConnectedAtLeastOnce && this.isNoLongerTryingToReconnect) {
				return t('spreed', 'Connection could not be established …')
			}

			if (this.isNoLongerTryingToReconnect) {
				return t('spreed', 'Connection was lost and could not be re-established …')
			}

			if (!this.wasConnectedAtLeastOnce && this.isReconnecting) {
				return t('spreed', 'Connection could not be established. Trying again …')
			}

			if (this.isReconnecting) {
				return t('spreed', 'Connection lost. Trying to reconnect …')
			}

			if (this.isDisconnected) {
				return t('spreed', 'Connection problems …')
			}

			return null
		},

		containerClass() {
			return {
				'videoContainer-dummy': this.placeholderForPromoted,
				'not-connected': !this.placeholderForPromoted && !this.isConnected,
				speaking: !this.placeholderForPromoted && this.isSpeaking && !this.isBig,
				hover: this.mouseover && !this.unSelectable && !this.isBig,
				promoted: !this.placeholderForPromoted && this.sharedData.promoted && !this.isGrid,
				presenter: this.isPresenterOverlay && this.mouseover,
				'video-container-grid': this.isGrid,
				'video-container-big': this.isBig,
				'one-to-one': this.isOneToOne,
				'presenter-overlay': this.isPresenterOverlay,
			}
		},

		videoWrapperClass() {
			return {
				'presenter-overlay': this.isPresenterOverlay,
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

		connectionMessageClass() {
			return {
				'below-avatar': this.showBackgroundAndAvatar,
			}
		},

		sessionHash() {
			return Hex.stringify(SHA1(this.nextcloudSessionId))
		},

		peerData() {
			let peerData = this.$store.getters.getPeer(this.token, this.nextcloudSessionId, this.model.attributes.userId)
			if (!peerData.actorId) {
				EventBus.emit('refresh-peer-list')
				peerData = {
					actorType: '',
					actorId: '',
					displayName: '',
				}
			}
			return peerData
		},

		participant() {
			/**
			 * This only works for logged-in users. Guests can not load the data
			 * via the participant list
			 */
			return this.$store.getters.findParticipant(this.token, {
				sessionId: this.nextcloudSessionId,
			}) || {}
		},

		participantActorType() {
			if (this.model.attributes.actorType) {
				return this.model.attributes.actorType
			} else if (this.participant?.actorType) {
				return this.participant.actorType
			} else if (this.peerData?.actorType) {
				return this.peerData.actorType
			} else {
				return this.participantUserId
					? ATTENDEE.ACTOR_TYPE.USERS
					: ATTENDEE.ACTOR_TYPE.GUESTS
			}
		},

		participantUserId() {
			if (this.model.attributes.actorId) {
				return this.model.attributes.actorId
			}

			if (this.model.attributes.userId) {
				return this.model.attributes.userId
			}

			// Check data from participant list
			if (this.participant?.actorType) {
				if (this.participant?.actorType === ATTENDEE.ACTOR_TYPE.USERS && this.participant?.actorId) {
					return this.participant.actorId
				}

				// Not a user
				return null
			}

			// Fallback to CallController::getPeers() endpoint
			if (this.peerData.actorType === ATTENDEE.ACTOR_TYPE.USERS
				|| this.peerData.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS) {
				return this.peerData.actorId
			}

			return null
		},

		displayName() {
			if (this.model.attributes.name) {
				return this.model.attributes.name
			}

			if (this.participant?.displayName) {
				return this.participant.displayName
			}

			let participantName = this.model.attributes.name

			// The name is undefined and not shown until a connection is made
			// for registered users, so do not fall back to the guest name in
			// the store either until the connection was made.
			if (!this.model.attributes.userId && !participantName && participantName !== undefined) {
				participantName = this.guestNameStore.getGuestName(
					this.token,
					this.sessionHash,
				)
			}

			if (!participantName) {
				participantName = this.peerData.displayName
			}

			return participantName?.trim() ?? ''
		},

		participantName() {
			return getDisplayNameWithFallback(this.displayName, this.participantActorType)
		},

		isSpeaking() {
			return this.model.attributes.speaking
		},

		hasVideo() {
			return !this.model.attributes.videoBlocked && this.model.attributes.videoAvailable && this.sharedData.remoteVideoBlocker.isVideoEnabled() && (typeof this.model.attributes.stream === 'object')
		},

		hasSelectedVideo() {
			return this.callViewStore.selectedVideoPeerId !== null
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
				// Always show shared screen if there's one
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
				// Always show shared screen if there's one
				return this.hasSharedScreen && !this.isPresenterOverlay
			}
		},

		showVideo() {
			// Screenshare have higher priority so return false if screenshare
			// is shown
			if (this.hasSharedScreen) {
				return (!this.showSharedScreen && this.hasVideo && !this.isSelected) || this.isPresenterOverlay
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
				} else if (this.hasSelectedVideo) {
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

		nextcloudSessionId() {
			return this.model.attributes.nextcloudSessionId
		},

		screenshotModeUrl() {
			return this.screenshotMode ? placeholderImage(6) : ''
		},
	},

	watch: {
		'model.attributes.stream'(stream) {
			this._setStream(stream)
		},

		isSelected(bool) {
			if (bool) {
				this.mouseover = false
			}
		},

	},

	mounted() {
		this.sharedData.remoteVideoBlocker.increaseVisibleCounter()

		// Set initial state
		this._setStream(this.model.attributes.stream)

		if (this.isBig || this.isGrid) {
			this.resizeObserver = new ResizeObserver(this.updateContainerAspectRatio)
			this.resizeObserver.observe(this.$refs.videoContainer)
		}
	},

	beforeUnmount() {
		if (this.resizeObserver) {
			this.resizeObserver.disconnect()
		}
	},

	unmounted() {
		this.sharedData.remoteVideoBlocker.decreaseVisibleCounter()
	},

	methods: {
		t,
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

		updateContainerAspectRatio([{ target }]) {
			this.containerAspectRatio = target.clientWidth / target.clientHeight
		},

		updateVideoAspectRatio() {
			if (!this.isBig) {
				return
			}

			this.videoAspectRatio = this.model.attributes.stream.getVideoTracks()?.[0].getSettings().aspectRatio
				// Fallback for Firefox
				?? this.$refs.video.videoWidth / this.$refs.video.videoHeight
		},

		handleHoverEvent(value) {
			this.$emit('forcePromoteVideo', value ? this.model : null)
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

.video-container-grid {
	position: relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
}

.video-container-big {
	position: absolute;

	&.one-to-one {
		width: calc(100% - var(--grid-gap) * 2);
		height: calc(100% - var(--grid-gap));
	}

	& .videoWrapper {
		margin: auto;
	}
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
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
}

.videoWrapper,
.video {
	height: 100%;
	width: 100%;
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
}

.videoWrapper.presenter-overlay {
	& > video {
		border-radius: 50%;
	}
	& > .dev-mode-video--presenter {
		position: absolute;
		top: 0;
		inset-inline-start: 0;
		height: 100%;
		width: 100%;
		object-fit: cover;
		border-radius: 50%;
	}
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

.connection-message {
	width: 100%;

	position: absolute;
	top: calc(50% + 50px);

	text-align: center;

	z-index: 1;

	color: white;
	filter: drop-shadow(1px 1px 4px var(--color-box-shadow));

	&.below-avatar {
		top: calc(50% + 80px);
	}
}

.video-container::after {
	position: absolute;
	height: 100%;
	width: 100%;
	top: 0;
	inset-inline-start: 0;
	border-radius: var(--border-radius-element, calc(var(--default-clickable-area) / 2));
}

.video-container.presenter-overlay::after {
	border-radius: 50%;
	z-index: 1;
}

.video-container.speaking::after {
	content: '';
	box-shadow: inset 0 0 0 2px white;
}

.video-container.hover::after {
	content: '';
	box-shadow: inset 0 0 0 3px white;
	cursor: pointer;
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

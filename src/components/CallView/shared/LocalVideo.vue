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
	<div id="localVideoContainer"
		class="videoContainer videoView"
		:class="videoContainerClass"
		@mouseover="showShadow"
		@mouseleave="hideShadow"
		@click="handleClickVideo">
		<video v-show="localMediaModel.attributes.videoEnabled"
			id="localVideo"
			ref="video"
			:class="videoClass"
			class="video" />
		<div v-if="!localMediaModel.attributes.videoEnabled && !isSidebar" class="avatar-container">
			<VideoBackground
				v-if="isGrid || isStripe"
				:display-name="displayName"
				:user="userId" />
			<Avatar v-if="userId"
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:show-user-status="false"
				:user="userId"
				:display-name="displayName" />
			<div v-if="!userId"
				:class="avatarSizeClass"
				class="avatar guest">
				{{ firstLetterOfGuestName }}
			</div>
		</div>
		<div v-if="mouseover && isSelectable" class="hover-shadow" />
		<div class="bottom-bar">
			<button
				v-if="isBig"
				class="bottom-bar__button"
				@click="handleStopFollowing">
				{{ stopFollowingLabel }}
			</button>
		</div>
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Hex from 'crypto-js/enc-hex'
import SHA1 from 'crypto-js/sha1'
import {
	showError,
	showInfo,
	TOAST_PERMANENT_TIMEOUT,
} from '@nextcloud/dialogs'
import video from '../../../mixins/video.js'
import VideoBackground from './VideoBackground'

export default {

	name: 'LocalVideo',

	components: {
		Avatar,
		VideoBackground,
	},

	mixins: [video],

	props: {
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
	},

	computed: {

		videoContainerClass() {
			return {
				'speaking': this.localMediaModel.attributes.speaking,
				'video-container-grid': this.isGrid,
				'video-container-stripe': this.isStripe,
				'video-container-big': this.isBig,
			}
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

		avatarSize() {
			return this.useConstrainedLayout ? 64 : 128
		},

		avatarSizeClass() {
			return 'avatar-' + this.avatarSize + 'px'
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
			return this.hasLocalVideo && this.$store.getters.selectedVideoPeerId !== 'local'
		},
	},

	watch: {

		localCallParticipantModel: {
			immediate: true,

			handler: function(localCallParticipantModel, oldLocalCallParticipantModel) {
				if (oldLocalCallParticipantModel) {
					oldLocalCallParticipantModel.off('forcedMute', this._handleForcedMute)
				}

				if (localCallParticipantModel) {
					localCallParticipantModel.on('forcedMute', this._handleForcedMute)
				}
			},
		},

		'localMediaModel.attributes.localStream': function(localStream) {
			this._setLocalStream(localStream)
		},

		localStreamVideoError: {
			immediate: true,

			handler: function(localStreamVideoError) {
				if (localStreamVideoError) {
					showError(t('spreed', 'Error while accessing camera'), {
						timeout: TOAST_PERMANENT_TIMEOUT,
					})
				}
			},
		},

	},

	mounted() {
		// Set initial state
		this._setLocalStream(this.localMediaModel.attributes.localStream)
	},

	destroyed() {
		if (this.localCallParticipantModel) {
			this.localCallParticipantModel.off('forcedMute', this._handleForcedMute)
		}
	},

	methods: {

		_handleForcedMute() {
			// The default toast selector is "body-user", but as this toast can
			// be shown to guests too a generic selector valid both for logged
			// in users and guests needs to be used instead (undefined selects
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
		},
	},

}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables.scss';
@import '../../../assets/avatar.scss';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.video-container-grid {
	position:relative;
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.video-container-stripe {
	position:relative;
	height: 100%;
	flex: 0 0 300px;
	overflow: hidden;
	display: flex;
	flex-direction: column;
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

.avatar-container {
	margin: auto;
}

.video-container-big {
	position: absolute;
	height: 100%;
	width: 100%;
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

</style>

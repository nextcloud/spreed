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
	<div id="localVideoContainer" class="videoContainer videoView" :class="{ speaking: localMediaModel.attributes.speaking }">
		<transition name="fade">
			<span v-show="showQualityWarning"
				v-tooltip="qualityWarningTooltip"
				:aria-label="qualityWarningAriaLabel"
				class="qualityWarning forced-white icon icon-error" />
		</transition>
		<video v-show="localMediaModel.attributes.videoEnabled" id="localVideo" ref="video" />
		<div v-if="!localMediaModel.attributes.videoEnabled" class="avatar-container">
			<Avatar v-if="userId"
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:user="userId"
				:display-name="displayName" />
			<div v-else
				:class="avatarSizeClass"
				class="avatar guest">
				{{ firstLetterOfGuestName }}
			</div>
		</div>
		<LocalMediaControls ref="localMediaControls"
			:model="localMediaModel"
			:local-call-participant-model="localCallParticipantModel"
			:screen-sharing-button-hidden="useConstrainedLayout"
			@switchScreenToId="$emit('switchScreenToId', $event)" />
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import LocalMediaControls from './LocalMediaControls'
import Hex from 'crypto-js/enc-hex'
import SHA1 from 'crypto-js/sha1'
import { showError } from '@nextcloud/dialogs'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import { callAnalyzer } from '../../utils/webrtc/index'
import { CONNECTION_QUALITY } from '../../utils/webrtc/analyzers/PeerConnectionAnalyzer'

export default {

	name: 'LocalVideo',

	directives: {
		tooltip: Tooltip,
	},

	components: {
		Avatar,
		LocalMediaControls,
	},

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
	},

	data() {
		return {
			callAnalyzer: callAnalyzer,
			qualityWarningInGracePeriodTimeout: null,
		}
	},

	computed: {

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
			) || localStorage.getItem('nick') || t('spreed', 'Guest')
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

		showQualityWarning() {
			return this.senderConnectionQualityAudioIsBad || this.qualityWarningInGracePeriodTimeout
		},

		senderConnectionQualityAudioIsBad() {
			return callAnalyzer
				&& (callAnalyzer.attributes.senderConnectionQualityAudio === CONNECTION_QUALITY.VERY_BAD
				 || callAnalyzer.attributes.senderConnectionQualityAudio === CONNECTION_QUALITY.NO_TRANSMITTED_DATA)
		},

		qualityWarningAriaLabel() {
			return t('spreed', 'Bad sent audio quality')
		},

		qualityWarningTooltip() {
			if (!this.showQualityWarning) {
				return false
			}

			let message = ''
			if (this.localMediaModel.attributes.videoEnabled && this.localMediaModel.attributes.localScreen) {
				message = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video while doing a screenshare.')
			} else if (this.localMediaModel.attributes.localScreen) {
				message = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see your screen. To improve the situation try to disable your screenshare.')
			} else if (this.localMediaModel.attributes.videoEnabled) {
				message = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand and see you. To improve the situation try to disable your video.')
			} else {
				message = t('spreed', 'Your internet connection or computer are busy and other participants might be unable to understand you.')
			}

			return {
				content: message,
				show: true,
			}
		},
	},

	watch: {

		'localMediaModel.attributes.localStream': function(localStream) {
			this._setLocalStream(localStream)
		},

		localStreamVideoError: {
			immediate: true,
			handler: function(localStreamVideoError) {
				if (localStreamVideoError) {
					showError(t('spreed', 'Error while accessing camera'), {
						timeout: 0,
					})
				}
			},
		},

		senderConnectionQualityAudioIsBad: function(senderConnectionQualityAudioIsBad) {
			if (!senderConnectionQualityAudioIsBad) {
				return
			}

			if (this.qualityWarningInGracePeriodTimeout) {
				window.clearTimeout(this.qualityWarningInGracePeriodTimeout)
			}

			this.qualityWarningInGracePeriodTimeout = window.setTimeout(() => {
				this.qualityWarningInGracePeriodTimeout = null
			}, 3000)
		},

	},

	mounted() {
		// Set initial state
		this._setLocalStream(this.localMediaModel.attributes.localStream)
	},

	methods: {

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

	},

}
</script>

<style lang="scss" scoped>
@import '../../assets/avatar.scss';
@include avatar-mixin(64px);
@include avatar-mixin(128px);

.qualityWarning {
	position: absolute;
	right: 0;

	width: 44px;
	height: 44px;
	background-size: 24px;

	/* Needed to show in front of the avatar container. */
	z-index: 10;
}

</style>
